<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Api\Employee\Concerns\HandlesEmployeeBulkOperations;
use App\Http\Controllers\Api\Employee\Concerns\HandlesEmployeeCoreBulk;
use App\Http\Controllers\Api\Employee\Concerns\HandlesEmployeeCoreEndpoints;
use App\Http\Controllers\Api\Employee\Concerns\HandlesEmployeeCoreExport;
use App\Http\Controllers\Api\Employee\Concerns\HandlesEmployeeOrganizationEndpoints;
use App\Http\Controllers\Api\Employee\Concerns\HandlesEmployeeProfilePhotoEndpoints;
use App\Http\Controllers\Api\Employee\Concerns\HandlesEmployeeSharedUtilities;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Services\Media\AvatarStorageService;
use App\Services\Media\Exceptions\InvalidMediaException;
use App\Services\Media\MediaFileDeleter;
use App\Services\Media\PolicyAttachmentStorageService;
use App\Models\Designation;
use App\Models\EmployeeProfile;
use App\Models\HcmRole;
use App\Models\HcmScheduleTiming;
use App\Models\HcmUserRole;
use App\Models\Policy;
use App\Models\Team;
use App\Models\User;
use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Models\WilayahVillage;
use App\Services\EmployeeCountValidator;
use App\Services\Hcm\EmployeeSnapshotService;
use App\Services\Hcm\PkwtCompensationService;
use Database\Seeders\HcmUserManagementSeeder;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class HcmEmployeeController extends Controller
{
    use ChecksPermissions;
    use HandlesEmployeeBulkOperations;
    use HandlesEmployeeCoreBulk;
    use HandlesEmployeeCoreEndpoints;
    use HandlesEmployeeCoreExport;
    use HandlesEmployeeCoreBulk;
    use HandlesEmployeeCoreExport;
    use HandlesEmployeeOrganizationEndpoints;
    use HandlesEmployeeProfilePhotoEndpoints;
    use HandlesEmployeeSharedUtilities;
    use \App\Http\Controllers\Api\Concerns\LogsHcmActivity;

    public function __construct(
        private readonly AvatarStorageService $avatarStorage,
        private readonly PolicyAttachmentStorageService $policyAttachmentStorage,
        private readonly MediaFileDeleter $mediaFileDeleter,
        private readonly PkwtCompensationService $pkwtCompensationService,
        private readonly EmployeeSnapshotService $employeeSnapshotService,
    ) {
    }

    /**
     * Single aggregate query for directory summary (avoids N separate COUNT(*) on large tables).
     *
     * @return array{totalEmployees: int, activeEmployees: int, inactiveEmployees: int, probationEmployees: int, newJoiners: int}
     */
    private function employeeDirectorySummary(?int $scopeCompanyId): array
    {
        $since = now()->subDays(30);
        $summaryQuery = DB::table('employee_profiles')
            ->join('users', 'users.id', '=', 'employee_profiles.user_id')
            ->selectRaw(
                'COUNT(*) as total, '.
                'SUM(CASE WHEN employee_profiles.employment_status IS NULL OR employee_profiles.employment_status = ? THEN 1 ELSE 0 END) as active_employees, '.
                'SUM(CASE WHEN employee_profiles.employment_status IN (?, ?, ?) THEN 1 ELSE 0 END) as inactive_employees, '.
                'SUM(CASE WHEN employee_profiles.employment_status = ? THEN 1 ELSE 0 END) as probation_employees, '.
                'SUM(CASE WHEN users.created_at >= ? THEN 1 ELSE 0 END) as new_joiners',
                ['active', 'inactive', 'resigned', 'terminated', 'probation', $since]
            );

        if ($scopeCompanyId) {
            $summaryQuery->where('employee_profiles.company_id', $scopeCompanyId);
        }

        $summaryQuery->whereNotExists(function ($sub) use ($scopeCompanyId): void {
            $sub->from('company_users')
                ->whereColumn('company_users.user_id', 'employee_profiles.user_id')
                ->where('company_users.status', 'active')
                ->where('company_users.role', 'owner');
            if ($scopeCompanyId) {
                $sub->where('company_users.company_id', $scopeCompanyId);
            }
        });

        $row = $summaryQuery->first();

        return [
            'totalEmployees' => (int) ($row->total ?? 0),
            'activeEmployees' => (int) ($row->active_employees ?? 0),
            'inactiveEmployees' => (int) ($row->inactive_employees ?? 0),
            'probationEmployees' => (int) ($row->probation_employees ?? 0),
            'newJoiners' => (int) ($row->new_joiners ?? 0),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        return is_numeric($text) ? (int) $text : null;
    }

    private function normalizePhoneForStorage(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        // Canonicalize Indonesian numbers so 08xx, 62xx, and +62xx map to the same value.
        if (str_starts_with($digits, '0')) {
            $digits = '62'.ltrim($digits, '0');
        }

        return $digits !== '' ? $digits : null;
    }

    private function composeWilayahAddress(?int $provinceId, ?int $regencyId, ?int $districtId, ?int $villageId): ?string
    {
        if (! $provinceId && ! $regencyId && ! $districtId && ! $villageId) {
            return null;
        }

        $province = $provinceId ? WilayahProvince::query()->find($provinceId) : null;
        $regency = $regencyId ? WilayahRegency::query()->find($regencyId) : null;
        $district = $districtId ? WilayahDistrict::query()->find($districtId) : null;
        $village = $villageId ? WilayahVillage::query()->find($villageId) : null;

        if (! $province && ! $regency && ! $district && ! $village) {
            return null;
        }

        return collect([
            $village?->name,
            $district?->name,
            $regency?->name,
            $province?->name,
        ])->filter()->implode(', ');
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        // Global Super Admin bypasses tenant scoping across employee queries.
        if (auth()->user()?->isGlobalHcmAdmin()) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        if (! $this->tableHasColumn($table, 'company_id')) {
            return $query;
        }

        if (! $companyId) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($companyId): void {
            $inner->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }

    private function applyIdentifierScope(Builder $query, string $identifier): Builder
    {
        if (Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier);
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier);
        }

        return $query->where('uuid', $identifier);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private function canManageEmployee(Request $request): bool
    {
        return $this->hasAnyPermission($request, ['employee.manage', 'employee.admin']);
    }

    private function canManageEmployeeTarget(Request $request, User $user): bool
    {
        if ($request->user()?->isGlobalHcmAdmin()) {
            return true;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return false;
        }

        return EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->where('company_id', $activeCompanyId)
            ->exists();
    }

    private function normalizeEmployeeWritePayload(Request $request): void
    {
        $nik = $this->nullableString($request->input('nik') ?? $request->input('ktpNo'));
        if ($nik !== null) {
            $digits = preg_replace('/\D+/', '', $nik) ?? '';
            $request->merge([
                'nik' => $digits !== '' ? $digits : null,
                'ktpNo' => $digits !== '' ? $digits : null,
            ]);
        }

        if ($request->has('phone')) {
            $request->merge(['phone' => $this->normalizePhoneForStorage($request->input('phone'))]);
        }

        foreach (['bpjsKesehatanNo', 'bpjsKetenagakerjaanNo'] as $bpjsKey) {
            if ($request->has($bpjsKey)) {
                $bpjsValue = preg_replace('/\D+/', '', (string) $request->input($bpjsKey)) ?? '';
                $request->merge([$bpjsKey => $bpjsValue !== '' ? $bpjsValue : null]);
            }
        }

        if ($request->has('contractType')) {
            $request->merge(['contractType' => $this->normalizeContractType($request->input('contractType'))]);
        }

        foreach (['provinceId', 'regencyId', 'districtId', 'villageId'] as $key) {
            if ($request->has($key)) {
                $request->merge([$key => $this->nullableInteger($request->input($key))]);
            }
        }

        $provinceId = $this->nullableInteger($request->input('provinceId'));
        $regencyId = $this->nullableInteger($request->input('regencyId'));
        $districtId = $this->nullableInteger($request->input('districtId'));
        $villageId = $this->nullableInteger($request->input('villageId'));
        $address = $this->nullableString($request->input('address'));
        if ($request->has('addressDetail')) {
            $request->merge(['addressDetail' => $this->nullableString($request->input('addressDetail'))]);
        }
        if ($address === null) {
            $composedAddress = $this->composeWilayahAddress($provinceId, $regencyId, $districtId, $villageId);
            if ($composedAddress !== null) {
                $request->merge(['address' => $composedAddress]);
            }
        }

        $nationality = $this->nullableString($request->input('nationality'));
        if ($nationality === null) {
            $request->merge(['nationality' => 'Indonesia']);
        } else {
            $request->merge(['nationality' => $nationality]);
        }

        if (($request->has('contractType') || $request->has('contractEndDate'))
            && $this->normalizeContractType($request->input('contractType')) === 'permanent'
            && $this->nullableString($request->input('contractEndDate')) === null) {
            $request->merge(['contractEndDate' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeWriteRules(Request $request, bool $isCreate = false, ?User $user = null, bool $selfService = false): array
    {
        $activeCompanyId = $this->activeCompanyId($request);
        $profileId = $user?->employeeProfile?->id;
        $districtId = $this->nullableInteger($request->input('districtId'));
        $districtHasVillages = $districtId
            ? WilayahVillage::query()->where('district_id', $districtId)->exists()
            : false;

        $existingContractType = $this->normalizeContractType(optional($user?->employeeProfile)->contract_type);
        $rawContractType = $this->nullableString($request->input('contractType'));
        $contractType = $rawContractType !== null
            ? $this->normalizeContractType($rawContractType)
            : $existingContractType;
        $nameRules = $isCreate
            ? ['required', 'string', 'min:2', 'max:150', 'regex:/^[\p{L}\p{M} .,\'-]{2,150}$/u']
            : ['sometimes', 'string', 'min:2', 'max:150', 'regex:/^[\p{L}\p{M} .,\'-]{2,150}$/u'];
        $emailRules = [
            $isCreate ? 'required' : 'sometimes',
            'string',
            'email:rfc',
            'max:255',
            Rule::unique('users', 'email')->ignore($user?->id),
        ];
        $phoneRules = [($isCreate && ! $selfService) ? 'required' : 'nullable', 'regex:/^\+?[0-9]{10,15}$/'];
        $nikRules = [($isCreate && ! $selfService) ? 'required' : 'nullable', 'regex:/^[0-9]{16}$/'];
        if ($activeCompanyId) {
            $nikRules[] = function (string $attribute, mixed $value, \Closure $fail) use ($activeCompanyId, $profileId): void {
                $normalizedInput = preg_replace('/\D+/', '', (string) ($value ?? '')) ?? '';
                if ($normalizedInput === '') {
                    return;
                }

                $query = EmployeeProfile::query()
                    ->where('company_id', $activeCompanyId);

                if (is_numeric($profileId)) {
                    $query->where('id', '!=', (int) $profileId);
                }

                $alreadyExists = $query->get(['id', 'nik'])->contains(function (EmployeeProfile $profile) use ($normalizedInput): bool {
                    $existingNik = preg_replace('/\D+/', '', (string) ($profile->nik ?? '')) ?? '';
                    return $existingNik !== '' && $existingNik === $normalizedInput;
                });

                if ($alreadyExists) {
                    $fail('NIK sudah terdaftar untuk employee lain di tenant aktif.');
                }
            };
        }
        $nationalityRule = function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || trim((string) $value) === '') {
                $fail('Nationality wajib diisi dan harus Indonesia.');
                return;
            }
            if (strcasecmp(trim((string) $value), 'Indonesia') !== 0) {
                $fail('Nationality harus Indonesia.');
            }
        };
        $emergencyContactRule = function (string $attribute, mixed $value, \Closure $fail): void {
            $contacts = is_array($value) ? $value : [];
            $hasValid = collect($contacts)->contains(function ($contact): bool {
                if (! is_array($contact)) {
                    return false;
                }
                return filled($contact['name'] ?? null)
                    && filled($contact['relationship'] ?? null)
                    && preg_match('/^\+?[0-9]{10,15}$/', (string) ($contact['phone'] ?? '')) === 1;
            });

            if (! $hasValid) {
                $fail('Minimal satu kontak darurat dengan nama, hubungan, dan nomor telepon valid wajib diisi.');
            }
        };

        $rules = [
            'name' => $nameRules,
            'email' => $emailRules,
            'password' => $isCreate ? ['required', 'string', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/'] : ['sometimes', 'nullable', 'string', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/'],
            'confirmPassword' => $isCreate ? ['required', 'same:password'] : ['sometimes', 'nullable', 'same:password'],
            'team' => ['nullable', 'string', 'max:100'],
            'teamId' => ['nullable', 'integer', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('company_id', $this->activeCompanyId($request)))],
            'departmentId' => array_values(array_filter([
                $isCreate && ! $selfService ? 'required' : 'sometimes',
                'nullable',
                'integer',
                'exists:departments,id',
            ])),
            'designationId' => array_values(array_filter([
                $isCreate && ! $selfService ? Rule::requiredIf(fn () => $this->nullableString($request->input('designation')) === null) : 'sometimes',
                'nullable',
                'integer',
                'exists:designations,id',
            ])),
            'designation' => array_values(array_filter([
                $isCreate && ! $selfService ? Rule::requiredIf(fn () => ! $request->filled('designationId')) : 'sometimes',
                'nullable',
                'string',
                'max:150',
            ])),
            'managerUserId' => ['sometimes', 'nullable', 'integer', Rule::notIn([$user?->id]), 'exists:users,id'],
            'employeeType' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'string', 'max:50'],
            'startDate' => ['sometimes', 'nullable', 'date'],
            'baseSalary' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'numeric', 'min:0', 'regex:/^[0-9]+$/'],
            'fixedAllowance' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salaryType' => ['sometimes', 'nullable', Rule::in($this->salaryTypeOptions())],
            'contractType' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', Rule::in($this->acceptedContractTypeInputs())],
            'contractStatus' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', Rule::in($this->contractStatusOptions())],
            'contractStartDate' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'date'],
            'contractEndDate' => [
                'nullable',
                'date',
                'after_or_equal:contractStartDate',
                Rule::requiredIf(fn () => $contractType === 'contract'),
                Rule::prohibitedIf(fn () => $contractType === 'permanent' && $this->nullableString($request->input('contractEndDate')) !== null),
            ],
            'probationEndDate' => [
                'sometimes', 'nullable', 'date', 'after_or_equal:startDate',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $startDate = request()->input('startDate') ?? request()->input('contractStartDate');
                    if ($startDate && $value) {
                        $max = \Carbon\Carbon::parse((string) $startDate)->addMonths(12);
                        if (\Carbon\Carbon::parse((string) $value)->gt($max)) {
                            $fail('Tanggal akhir probasi tidak boleh lebih dari 12 bulan sejak tanggal mulai kerja.');
                        }
                    }
                },
            ],
            'employmentStatus' => ['sometimes', 'nullable', Rule::in($this->employmentStatusOptions())],
            'hireDate' => ['sometimes', 'nullable', 'date'],
            'nik' => $nikRules,
            'ktpNo' => ['sometimes', 'nullable', 'regex:/^[0-9]{16}$/'],
            'phone' => $phoneRules,
            'provinceId' => [
                $isCreate && ! $selfService ? 'required' : 'sometimes',
                'nullable',
                'integer',
                'required_with:regencyId,districtId,villageId',
                Rule::exists('wilayah_provinces', 'id'),
            ],
            'regencyId' => [
                $isCreate && ! $selfService ? 'required' : 'sometimes',
                'nullable',
                'integer',
                'required_with:provinceId,districtId,villageId',
                Rule::exists('wilayah_regencies', 'id')->where(fn ($query) => $query->where('province_id', $this->nullableInteger($request->input('provinceId')))),
            ],
            'districtId' => [
                $isCreate && ! $selfService ? 'required' : 'sometimes',
                'nullable',
                'integer',
                'required_with:provinceId,regencyId,villageId',
                Rule::exists('wilayah_districts', 'id')->where(fn ($query) => $query->where('regency_id', $this->nullableInteger($request->input('regencyId')))),
            ],
            'villageId' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::requiredIf(fn () =>
                    (($isCreate && ! $selfService) || $request->filled('districtId'))
                    && ($districtId === null || $districtHasVillages)
                ),
                Rule::exists('wilayah_villages', 'id')->where(fn ($query) => $query->where('district_id', $this->nullableInteger($request->input('districtId')))),
            ],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'addressDetail' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn () =>
                    (($isCreate && ! $selfService) || $request->filled('districtId'))
                    && $districtId !== null
                    && ! $districtHasVillages
                ),
            ],
            'placeOfBirth' => [
                $isCreate && ! $selfService ? 'required' : 'sometimes',
                'nullable',
                'string',
                'min:2',
                'max:150',
                'regex:/^[\p{L}\p{M} .,\'-]{2,150}$/u',
            ],
            'dateOfBirth' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'date'],
            'gender' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'in:male,female,other'],
            'maritalStatus' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', Rule::in($this->maritalStatusOptions())],
            'religion' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', Rule::in($this->religionOptions())],
            'nationality' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'string', 'max:100', $nationalityRule],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'bankName' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', Rule::in($this->acceptedBankNames())],
            'bankAccountNo' => [
                $isCreate && ! $selfService ? 'required' : 'sometimes',
                'nullable',
                'string',
                'regex:/^[0-9]{8,30}$/',
            ],
            'bankAccountHolderName' => [
                $isCreate && ! $selfService ? 'required' : 'sometimes',
                'nullable',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\p{L}\p{M} .,\'-]{2,100}$/u',
            ],
            'bankIfscCode' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bankBranch' => ['sometimes', 'nullable', 'string', 'max:150'],
            'npwp' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $raw = trim((string) $value);
                    if ($raw === '') {
                        return;
                    }

                    $normalized = preg_replace('/[^0-9]/', '', $raw) ?? '';
                    if (! preg_match('/^[0-9]{15,16}$/', $normalized)) {
                        $fail($attribute . ' harus berisi NPWP valid (15-16 digit, titik/strip diperbolehkan).');
                    }
                },
            ],
            'taxStatus' => ['sometimes', 'nullable', Rule::in($this->acceptedTaxStatusInputs())],
            'ptkpStatus' => ['sometimes', 'nullable', Rule::in($this->acceptedTaxStatusInputs())],
            'bpjsKesehatanNo' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{13}$/'],
            'bpjsKetenagakerjaanNo' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{11}$/'],
            'emergencyContacts' => [($isCreate && ! $selfService) ? 'required' : 'sometimes', 'array', 'min:1', $emergencyContactRule],
            'emergencyContacts.*.name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\p{L}\p{M}\' .,-]{2,100}$/u'],
            'emergencyContacts.*.relationship' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}\p{M}\' .\/-]{2,50}$/u'],
            'emergencyContacts.*.phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'educationItems' => ['sometimes', 'nullable', 'array'],
            'educationItems.*.institution' => ['required', 'string', 'min:2', 'max:100'],
            'educationItems.*.degree' => ['required', 'string', 'min:2', 'max:50'],
            'educationItems.*.startYear' => ['required', 'integer', 'min:1900', 'max:2100'],
            'educationItems.*.endYear' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'experienceItems' => ['sometimes', 'nullable', 'array'],
            'experienceItems.*.company' => ['required', 'string', 'min:2', 'max:100'],
            'experienceItems.*.position' => ['required', 'string', 'min:2', 'max:100'],
            'experienceItems.*.startDate' => ['required', 'date', 'before_or_equal:today'],
            'experienceItems.*.endDate' => ['nullable', 'date'],
        ];

        if ($selfService) {
            unset(
                $rules['name'],
                $rules['email'],
                $rules['password'],
                $rules['confirmPassword'],
                $rules['team'],
                $rules['departmentId'],
                $rules['designationId'],
                $rules['designation'],
                $rules['managerUserId'],
                $rules['employeeType'],
                $rules['startDate'],
                $rules['baseSalary'],
                $rules['fixedAllowance'],
                $rules['salaryType'],
                $rules['contractType'],
                $rules['contractStatus'],
                $rules['contractStartDate'],
                $rules['contractEndDate'],
                $rules['probationEndDate'],
                $rules['employmentStatus'],
                $rules['hireDate'],
                $rules['provinceId'],
                $rules['regencyId'],
                $rules['districtId'],
                $rules['villageId'],
                $rules['bankName'],
                $rules['bankAccountNo'],
                $rules['bankAccountHolderName'],
                $rules['bankIfscCode'],
                $rules['bankBranch'],
                $rules['npwp'],
                $rules['taxStatus'],
                $rules['ptkpStatus'],
                $rules['bpjsKesehatanNo'],
                $rules['bpjsKetenagakerjaanNo'],
                $rules['departmentId'],
                $rules['designationId']
            );
        }

        return $rules;
    }

    private function ensureAssignableTeamIsActive(?int $activeCompanyId, mixed $teamId): ?JsonResponse
    {
        if ($activeCompanyId === null || $teamId === null || (int) $teamId <= 0) {
            return null;
        }

        $team = Team::query()
            ->where('company_id', $activeCompanyId)
            ->whereKey((int) $teamId)
            ->first();

        if (! $team) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEAM_NOT_FOUND',
                    'message' => 'Selected team is not available in active company.',
                ],
            ], 422);
        }

        if (! (bool) $team->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEAM_INACTIVE_NOT_ASSIGNABLE',
                    'message' => 'Inactive team cannot receive member assignments.',
                ],
            ], 422);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function normalizeTeamAssignmentPayload(?int $activeCompanyId, array &$validated): ?JsonResponse
    {
        $teamName = array_key_exists('team', $validated)
            ? $this->nullableString($validated['team'])
            : null;
        $hasTeamIdKey = array_key_exists('teamId', $validated);

        if ($hasTeamIdKey) {
            $teamId = $validated['teamId'];
            if ($teamId === null || (int) $teamId <= 0) {
                if ($teamName !== null) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'TEAM_MASTER_SELECTION_REQUIRED',
                            'message' => 'Team assignment must use teamId from team master. Leave team empty to keep unassigned.',
                        ],
                    ], 422);
                }

                $validated['teamId'] = null;
                $validated['team'] = null;

                return null;
            }

            if ($teamAssignmentError = $this->ensureAssignableTeamIsActive($activeCompanyId, $teamId)) {
                return $teamAssignmentError;
            }

            $team = Team::query()
                ->where('company_id', $activeCompanyId)
                ->whereKey((int) $teamId)
                ->first();

            if (! $team) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TEAM_NOT_FOUND',
                        'message' => 'Selected team is not available in active company.',
                    ],
                ], 422);
            }

            $validated['teamId'] = (int) $team->id;
            $validated['team'] = (string) $team->name;

            return null;
        }

        if ($teamName !== null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEAM_MASTER_SELECTION_REQUIRED',
                    'message' => 'Team assignment must use teamId from team master. Leave team empty to keep unassigned.',
                ],
            ], 422);
        }

        if (array_key_exists('team', $validated)) {
            $validated['team'] = null;
        }

        return null;
    }

    /**
     * @return array{department_id: ?int, designation_id: ?int, designation: ?string}|JsonResponse
     */
    private function resolveOrganizationForWrite(mixed $departmentId, mixed $designationId, mixed $legacyDesignation): array|JsonResponse
    {
        $resolvedDeptId = ($departmentId !== null && $departmentId !== '' && (int) $departmentId > 0)
            ? (int) $departmentId
            : null;
        $resolvedDesigId = ($designationId !== null && $designationId !== '' && (int) $designationId > 0)
            ? (int) $designationId
            : null;

        $label = $legacyDesignation === null ? null : trim((string) $legacyDesignation);
        if ($label === '') {
            $label = null;
        }

        if ($resolvedDesigId) {
            $designation = Designation::query()->find($resolvedDesigId);
            if (! $designation) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DESIGNATION_NOT_FOUND',
                        'message' => 'Designation not found.',
                    ],
                ], 422);
            }
            if ($designation->department_id) {
                if ($resolvedDeptId === null) {
                    $resolvedDeptId = (int) $designation->department_id;
                } elseif ((int) $resolvedDeptId !== (int) $designation->department_id) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'DESIGNATION_DEPARTMENT_MISMATCH',
                            'message' => 'designationId does not belong to departmentId.',
                        ],
                    ], 422);
                }
            }
            $label = $designation->name;
        }

        return [
            'department_id' => $resolvedDeptId,
            'designation_id' => $resolvedDesigId,
            'designation' => $label,
        ];
    }

    private function directoryStatusOptions(): array
    {
        return ['active', 'inactive', 'probation', 'resigned', 'terminated'];
    }

    private function employmentStatusOptions(): array
    {
        return (array) config('hcm.employment_statuses', ['probation', 'active', 'resigned', 'terminated', 'inactive']);
    }

    private function salaryTypeOptions(): array
    {
        return (array) config('hcm.salary_types', ['monthly', 'daily', 'hourly']);
    }

    private function acceptedContractTypeInputs(): array
    {
        return array_values(array_unique(array_merge($this->contractTypeOptions(), ['pkwt', 'pkwtt'])));
    }

    private function contractTypeOptions(): array
    {
        return (array) config('hcm.contract_types', ['contract', 'permanent']);
    }

    private function contractStatusOptions(): array
    {
        return (array) config('hcm.contract_statuses', ['active', 'ended', 'terminated']);
    }

    private function maritalStatusOptions(): array
    {
        return (array) config('hcm.marital_statuses', ['single', 'married', 'divorced', 'widowed']);
    }

    private function religionOptions(): array
    {
        return (array) config('hcm.religions', ['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Konghucu']);
    }

    private function taxStatusOptions(): array
    {
        return (array) config('hcm.tax_statuses', ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3']);
    }

    private function acceptedTaxStatusInputs(): array
    {
        return array_values(array_unique(array_merge($this->taxStatusOptions(), ['TK', 'K'])));
    }

    private function normalizeTaxStatusInput(?string $value): ?string
    {
        $raw = strtoupper(str_replace(['/', ' '], '', trim((string) $value)));
        if ($raw === '') {
            return null;
        }

        $normalized = match ($raw) {
            'TK' => 'TK0',
            'K' => 'K0',
            default => $raw,
        };

        return in_array($normalized, $this->taxStatusOptions(), true) ? $normalized : null;
    }

    private function resolvePtkpAnnualNominal(?string $taxStatus): ?float
    {
        $normalized = $this->normalizeTaxStatusInput($taxStatus);
        if ($normalized === null) {
            return null;
        }

        return match ($normalized) {
            'TK1', 'K0' => 58_500_000.0,
            'TK2', 'K1' => 63_000_000.0,
            'TK3', 'K2' => 67_500_000.0,
            'K3' => 72_000_000.0,
            default => 54_000_000.0,
        };
    }

    private function allowedBankNames(): array
    {
        return (array) config('hcm.allowed_bank_names', ['BCA', 'Bank Mandiri', 'BNI', 'BRI', 'BTN', 'CIMB Niaga', 'Permata Bank', 'Danamon', 'BSI', 'OCBC NISP', 'Panin Bank', 'Maybank Indonesia', 'Bank Mega', 'Bank Sinarmas', 'Jenius / BTPN', 'SeaBank', 'Bank Jago']);
    }

    private function acceptedBankNames(): array
    {
        return array_values(array_unique(array_merge($this->allowedBankNames(), ['Mandiri'])));
    }

    private function normalizeBankName(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = strtolower($raw);
        foreach ($this->allowedBankNames() as $allowed) {
            if (strtolower($allowed) === $normalized) {
                return $allowed;
            }
        }

        return $normalized === 'mandiri' ? 'Bank Mandiri' : $raw;
    }

    private function normalizeContractType(?string $value): string
    {
        $raw = strtolower(trim((string) $value));
        if ($raw === '' || $raw === 'pkwtt') {
            return 'permanent';
        }

        if ($raw === 'pkwt') {
            return 'contract';
        }

        return in_array($raw, $this->contractTypeOptions(), true) ? $raw : 'permanent';
    }

    private function effectiveJoinDate(User $user, ?EmployeeProfile $profile): ?string
    {
        if ($profile !== null) {
            $snapshot = $this->employeeSnapshotService->snapshotForProfile($profile, $user);
            if (! empty($snapshot['startDate'])) {
                return (string) $snapshot['startDate'];
            }
        }

        if ($profile?->hire_date) {
            return $profile->hire_date->format('Y-m-d');
        }

        return optional($user->created_at)->toDateString();
    }
}
