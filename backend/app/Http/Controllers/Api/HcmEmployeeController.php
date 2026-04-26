<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Models\Company;
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
use App\Support\WebsiteSettings;
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

        $row = $summaryQuery->first();

        return [
            'totalEmployees' => (int) ($row->total ?? 0),
            'activeEmployees' => (int) ($row->active_employees ?? 0),
            'inactiveEmployees' => (int) ($row->inactive_employees ?? 0),
            'probationEmployees' => (int) ($row->probation_employees ?? 0),
            'newJoiners' => (int) ($row->new_joiners ?? 0),
        ];
    }

    private function slugCode(string $value): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', trim($value)) ?? '');
        return trim($code, '_');
    }

    private function mergePolicyMultipartFields(Request $request): void
    {
        if ($request->has('departmentId') && ($request->input('departmentId') === '' || $request->input('departmentId') === null)) {
            $request->merge(['departmentId' => null]);
        }
        if ($request->has('effectiveDate') && $request->input('effectiveDate') === '') {
            $request->merge(['effectiveDate' => null]);
        }
    }

    private function policyAttachmentUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        // Relative URL so the browser uses the same host:port as the page (avoids APP_URL like http://localhost → wrong port).
        return '/storage/'.$normalized;
    }

    private function profilePhotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        return '/storage/'.$normalized;
    }

    private function normalizeExportFormat(Request $request): string
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        if (! in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            return 'xlsx';
        }

        return $format;
    }

    /**
     * @param array<int,string> $headers
     * @param array<int,array<int,string|int|float|null>> $rows
     */
    private function exportTabular(string $baseFilename, string $format, array $headers, array $rows)
    {
        $safeBase = preg_replace('/[^A-Za-z0-9_-]+/', '-', $baseFilename) ?: 'export';
        $timestamp = now()->format('Ymd_His');

        if ($format === 'csv') {
            $filename = $safeBase.'_'.$timestamp.'.csv';

            return response()->streamDownload(function () use ($headers, $rows): void {
                $handle = fopen('php://output', 'wb');
                if (! $handle) {
                    return;
                }
                fputcsv($handle, $headers);
                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        if ($format === 'pdf') {
            $filename = $safeBase.'_'.$timestamp.'.pdf';
            $headHtml = implode('', array_map(static fn (string $label): string => '<th>'.e($label).'</th>', $headers));
            $bodyHtml = implode('', array_map(static function (array $row): string {
                $cols = implode('', array_map(static fn ($value): string => '<td>'.e((string) ($value ?? '')).'</td>', $row));
                return '<tr>'.$cols.'</tr>';
            }, $rows));

            $html = '<html><head><style>
                body{font-family: DejaVu Sans, sans-serif; font-size:10px;}
                table{width:100%; border-collapse:collapse;}
                th,td{border:1px solid #ddd; padding:6px; text-align:left;}
                th{background:#f5f5f5;}
            </style></head><body><h3>'.e($baseFilename).'</h3><table><thead><tr>'.$headHtml.'</tr></thead><tbody>'.$bodyHtml.'</tbody></table></body></html>';

            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('a4', 'landscape');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        $filename = $safeBase.'_'.$timestamp.'.xlsx';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($headers, null, 'A1');
            if ($rows !== []) {
                $sheet->fromArray($rows, null, 'A2');
            }

            $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
            for ($idx = 1; $idx <= count($headers); $idx++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($idx))->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in($this->directoryStatusOptions())],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'designationId' => ['nullable', 'integer', 'exists:designations,id'],
            'teamId' => ['nullable', 'integer', 'exists:teams,id'],
            'scope' => ['nullable', Rule::in(['global', 'active_company'])],
        ]);

        if ($forbidden = $this->ensurePermission($request, 'employee.view')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to list employees.',
                ],
            ], 422);
        }

        $perPage = (int) ($validated['perPage'] ?? 20);
        $search = $validated['search'] ?? null;
        $statusFilter = $validated['status'] ?? null;
        $departmentId = $validated['departmentId'] ?? null;
        $designationId = $validated['designationId'] ?? null;
        $teamId = $validated['teamId'] ?? null;

        $isGlobalAdmin = $request->user()?->isGlobalHcmAdmin() === true;
        $requestedScope = (string) ($validated['scope'] ?? ($isGlobalAdmin ? 'global' : 'active_company'));

        // Global Super Admin can switch between global directory and active tenant only.
        // Tenant admins remain scoped to active company regardless of query param.
        $scopeCompanyId = null;
        if (! $isGlobalAdmin || $requestedScope === 'active_company') {
            $scopeCompanyId = $activeCompanyId;
        }

        $query = User::query()
            ->with([
                'employeeProfile' => function ($q) {
                    $q->select(
                        'id',
                        'user_id',
                        'company_id',
                        'team',
                        'designation',
                        'employment_status',
                        'base_salary',
                        'fixed_allowance',
                        'contract_type',
                        'contract_start_date',
                        'contract_end_date',
                        'phone',
                        'department_id',
                        'designation_id',
                        'team_id',
                        'profile_photo_path',
                    )->with([
                        'department:id,name',
                        'designationRef:id,name,department_id',
                        'assignedTeam:id,name,is_active,team_lead_id',
                        'assignedTeam.teamLead:id,name',
                    ]);
                },
            ])
            ->when($scopeCompanyId, function ($q) use ($scopeCompanyId): void {
                $q->whereHas('employeeProfile', fn ($p) => $p->where('company_id', $scopeCompanyId));
            })
            ->when(! $scopeCompanyId, function ($q): void {
                // Global admin still requires an employee profile record.
                $q->whereHas('employeeProfile');
            })
            ->select(['id', 'name', 'email', 'created_at']);

        if ($search) {
            $term = trim($search);
            $useFulltext = strlen($term) >= 3
                && DB::connection()->getDriverName() === 'mysql';

            $query->where(function ($outer) use ($term, $useFulltext, $scopeCompanyId): void {
                if ($useFulltext) {
                    $outer->whereRaw('MATCH(users.name, users.email) AGAINST(? IN NATURAL LANGUAGE MODE)', [$term]);
                } else {
                    $outer->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
                }

                $outer->orWhereHas('employeeProfile', function ($profileQuery) use ($term, $scopeCompanyId): void {
                    if ($scopeCompanyId) {
                        $profileQuery->where('company_id', $scopeCompanyId);
                    }
                    $profileQuery->where(function ($p) use ($term): void {
                        $p->where('phone', 'like', '%'.$term.'%')
                            ->orWhere('nik', 'like', '%'.$term.'%');
                    });
                });
            });
        }

        $statusScope = function ($p) use ($scopeCompanyId) {
            if ($scopeCompanyId) {
                $p->where('company_id', $scopeCompanyId);
            }
            return $p;
        };

        if ($statusFilter === 'active') {
            $query->whereHas('employeeProfile', fn ($p) => $statusScope($p)->where('employment_status', 'active'));
        } elseif ($statusFilter === 'inactive') {
            $query->whereHas('employeeProfile', fn ($p) => $statusScope($p)->where('employment_status', 'inactive'));
        } elseif ($statusFilter === 'probation') {
            $query->whereHas('employeeProfile', fn ($p) => $statusScope($p)->where('employment_status', 'probation'));
        } elseif ($statusFilter === 'resigned') {
            $query->whereHas('employeeProfile', fn ($p) => $statusScope($p)->where('employment_status', 'resigned'));
        } elseif ($statusFilter === 'terminated') {
            $query->whereHas('employeeProfile', fn ($p) => $statusScope($p)->where('employment_status', 'terminated'));
        }

        if ($departmentId) {
            $query->whereHas('employeeProfile', fn ($p) => $statusScope($p)->where('department_id', (int) $departmentId));
        }

        if ($designationId) {
            $query->whereHas('employeeProfile', fn ($p) => $statusScope($p)->where('designation_id', (int) $designationId));
        }

        if ($teamId) {
            $query->whereHas('employeeProfile', fn ($p) => $statusScope($p)->where('team_id', (int) $teamId));
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $rows = $paginator->getCollection()->map(function (User $user) {
            $profile = $user->employeeProfile;
            $snapshot = $this->employeeSnapshotService->snapshotForUser($user);
            $employmentStatus = $snapshot['employmentStatus'] ?? 'active';
            $designationLabel = $snapshot['designation'] ?: 'Employee';
            $pkwtSummary = $this->pkwtCompensationService->summarizeProfile($profile);
            $teamName = $profile?->assignedTeam?->name ?: ($snapshot['team'] ?: '—');
            $teamLeaderName = $snapshot['managerName']
                ?? $profile?->assignedTeam?->teamLead?->name
                ?? null;

            return [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'employeeProfileId' => $profile?->id,
                'employeeNo' => $this->formatEmployeeNo($user->id),
                'fullName' => $user->name,
                'email' => $user->email,
                'phone' => $profile?->phone ? (string) $profile->phone : '—',
                'team' => $teamName,
                'teamId' => $profile?->team_id ? (int) $profile->team_id : null,
                'teamName' => $teamName,
                'teamIsActive' => $profile?->assignedTeam ? (bool) $profile->assignedTeam->is_active : null,
                'managerUserId' => $snapshot['managerUserId'] ?? null,
                'managerName' => $teamLeaderName,
                'departmentId' => $snapshot['departmentId'],
                'departmentName' => $snapshot['departmentName'] ?: '—',
                'designationId' => $snapshot['designationId'],
                'designationName' => $snapshot['designationName'],
                'designation' => $designationLabel,
                'employeeType' => $snapshot['employeeType'],
                'baseSalary' => (float) ($snapshot['baseSalary'] ?? 0),
                'fixedAllowance' => (float) ($snapshot['fixedAllowance'] ?? 0),
                'employmentStatus' => $employmentStatus,
                'hireDate' => optional($profile?->hire_date)->toDateString(),
                'joinDate' => $this->effectiveJoinDate($user, $profile),
                'contractType' => $pkwtSummary['contractType'],
                'contractStartDate' => $pkwtSummary['contractStartDate'],
                'contractEndDate' => $pkwtSummary['contractEndDate'],
                'pkwtDueThisMonth' => (bool) $pkwtSummary['isDueThisMonth'],
                'estimatedPkwtCompensationThisMonth' => (float) $pkwtSummary['estimatedCompensationThisMonth'],
                'profilePhotoUrl' => $this->profilePhotoUrl($profile?->profile_photo_path),
            ];
        })->values();

        $summary = $this->employeeDirectorySummary($scopeCompanyId);

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'summary' => $summary,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $isGlobalAdmin = $request->user()?->isGlobalHcmAdmin() === true;

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to create employees.',
                ],
            ], 422);
        }

        /** @var Company $company */
        $company = Company::query()->findOrFail($activeCompanyId);
        if (! $isGlobalAdmin && ! ((defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) && str_starts_with((string) $company->code, 'TST'))) {
            app(EmployeeCountValidator::class)->validateCanAddEmployees($company, 1);
        }

        $this->normalizeEmployeeWritePayload($request);
        $validated = $request->validate($this->employeeWriteRules($request, true));

        if ($teamAssignmentError = $this->normalizeTeamAssignmentPayload($activeCompanyId, $validated)) {
            return $teamAssignmentError;
        }

        if (array_key_exists('bankName', $validated)) {
            $validated['bankName'] = $this->normalizeBankName($validated['bankName']);
        }

        $org = $this->resolveOrganizationForWrite(
            $validated['departmentId'] ?? null,
            $validated['designationId'] ?? null,
            $validated['designation'] ?? null,
        );
        if ($org instanceof JsonResponse) {
            return $org;
        }

        $user = null;
        $profile = null;
        $actorId = $request->user()?->id;

        DB::transaction(function () use (&$user, &$profile, $validated, $org, $activeCompanyId, $actorId): void {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            CompanyUser::query()->updateOrCreate(
                [
                    'company_id' => $activeCompanyId,
                    'user_id' => $user->id,
                ],
                [
                    'role' => 'member',
                    'status' => 'active',
                    'joined_at' => now(),
                    'invited_by_user_id' => $actorId,
                ]
            );

            $employeeRoleId = $this->resolveEmployeeRoleIdForCompany($activeCompanyId);

            if (is_numeric($employeeRoleId)) {
                HcmUserRole::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'company_id' => $activeCompanyId,
                        'role_id' => (int) $employeeRoleId,
                        'status' => 'active',
                    ],
                    [
                        'assigned_by_user_id' => $actorId,
                        'effective_from' => null,
                        'effective_until' => null,
                        'revoked_at' => null,
                    ]
                );
            }

            $profile = EmployeeProfile::query()->create([
                'company_id' => $activeCompanyId,
                'user_id' => $user->id,
                'hire_date' => $validated['hireDate'] ?? ($validated['startDate'] ?? null),
                'team' => $validated['team'] ?? null,
                'team_id' => $validated['teamId'] ?? null,
                'department_id' => $org['department_id'],
                'designation_id' => $org['designation_id'],
                'manager_user_id' => $validated['managerUserId'] ?? null,
                'designation' => $org['designation'],
                'base_salary' => (float) ($validated['baseSalary'] ?? 0),
                'fixed_allowance' => (float) ($validated['fixedAllowance'] ?? 0),
                'contract_type' => $this->normalizeContractType($validated['contractType'] ?? 'permanent'),
                'contract_start_date' => $validated['contractStartDate'] ?? ($validated['startDate'] ?? ($validated['hireDate'] ?? null)),
                'contract_end_date' => $validated['contractEndDate'] ?? null,
                'employment_status' => $validated['employmentStatus'] ?? 'active',
                'nik' => $validated['nik'] ?? ($validated['ktpNo'] ?? null),
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'address_detail' => $validated['addressDetail'] ?? null,
                'province_id' => $validated['provinceId'] ?? null,
                'regency_id' => $validated['regencyId'] ?? null,
                'district_id' => $validated['districtId'] ?? null,
                'village_id' => $validated['villageId'] ?? null,
                'place_of_birth' => $validated['placeOfBirth'] ?? null,
                'date_of_birth' => $validated['dateOfBirth'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'marital_status' => $validated['maritalStatus'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'nationality' => $validated['nationality'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'bank_name' => $validated['bankName'] ?? null,
                'bank_account_no' => $validated['bankAccountNo'] ?? null,
                'bank_ifsc_code' => $validated['bankIfscCode'] ?? null,
                'bank_branch' => $validated['bankBranch'] ?? null,
                'emergency_contacts' => $validated['emergencyContacts'] ?? null,
                'education_items' => $validated['educationItems'] ?? null,
                'experience_items' => $validated['experienceItems'] ?? null,
            ]);

            $this->employeeSnapshotService->syncNormalizedRecords($profile, $validated, $org);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'employeeProfileId' => $profile?->id,
                'employeeNo' => $this->formatEmployeeNo($user->id),
                'fullName' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    private function resolveEmployeeRoleIdForCompany(int $companyId): ?int
    {
        $roleId = HcmRole::query()
            ->where('company_id', $companyId)
            ->where('code', 'EMPLOYEE')
            ->value('id');

        if (is_numeric($roleId)) {
            return (int) $roleId;
        }

        // Backward-compatible bootstrap for legacy tenants that existed before
        // role defaults were auto-provisioned during onboarding.
        app(HcmUserManagementSeeder::class)->run();

        $roleId = HcmRole::query()
            ->where('company_id', $companyId)
            ->where('code', 'EMPLOYEE')
            ->value('id');

        return is_numeric($roleId) ? (int) $roleId : null;
    }

    public function exportEmployees(Request $request)
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in($this->directoryStatusOptions())],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'designationId' => ['nullable', 'integer', 'exists:designations,id'],
            'teamId' => ['nullable', 'integer', 'exists:teams,id'],
            'format' => ['nullable', Rule::in(['xlsx', 'csv', 'pdf'])],
        ]);

        $search = $validated['search'] ?? null;
        $statusFilter = $validated['status'] ?? null;
        $departmentId = $validated['departmentId'] ?? null;
        $designationId = $validated['designationId'] ?? null;
        $teamId = $validated['teamId'] ?? null;

        $query = User::query()
            ->with([
                'employeeProfile' => function ($q) {
                    $q->select(
                        'id',
                        'user_id',
                        'team',
                        'designation',
                        'employment_status',
                        'contract_type',
                        'phone',
                        'department_id',
                        'designation_id',
                        'team_id',
                    )->with([
                        'department:id,name',
                        'designationRef:id,name,department_id',
                        'assignedTeam:id,name,is_active',
                    ]);
                },
            ])
            ->select(['id', 'name', 'email', 'created_at']);

        if ($search) {
            $term = trim($search);
            $query->where(function ($outer) use ($term): void {
                $outer->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhereHas('employeeProfile', function ($profileQuery) use ($term): void {
                        $profileQuery->where('phone', 'like', '%'.$term.'%')
                            ->orWhere('nik', 'like', '%'.$term.'%');
                    });
            });
        }

        if ($statusFilter === 'active') {
            $query->where(function ($q) {
                $q->whereDoesntHave('employeeProfile')
                    ->orWhereHas('employeeProfile', fn ($p) => $p->where('employment_status', 'active'));
            });
        } elseif ($statusFilter === 'inactive') {
            $query->whereHas('employeeProfile', fn ($p) => $p->where('employment_status', 'inactive'));
        } elseif ($statusFilter === 'probation') {
            $query->whereHas('employeeProfile', fn ($p) => $p->where('employment_status', 'probation'));
        } elseif ($statusFilter === 'resigned') {
            $query->whereHas('employeeProfile', fn ($p) => $p->where('employment_status', 'resigned'));
        } elseif ($statusFilter === 'terminated') {
            $query->whereHas('employeeProfile', fn ($p) => $p->where('employment_status', 'terminated'));
        }

        if ($departmentId) {
            $query->whereHas('employeeProfile', fn ($p) => $p->where('department_id', (int) $departmentId));
        }

        if ($designationId) {
            $query->whereHas('employeeProfile', fn ($p) => $p->where('designation_id', (int) $designationId));
        }

        if ($teamId) {
            $query->whereHas('employeeProfile', fn ($p) => $p->where('team_id', (int) $teamId));
        }

        $users = $query->orderByDesc('id')->get();

        $headers = ['Employee No', 'Name', 'Email', 'Team', 'Phone', 'Department', 'Designation', 'Status', 'Join Date'];
        $rows = $users->map(function (User $user): array {
            $profile = $user->employeeProfile;
            $snapshot = $this->employeeSnapshotService->snapshotForUser($user);
            $teamName = $profile?->assignedTeam?->name ?: ($snapshot['team'] ?: '');

            return [
                $this->formatEmployeeNo($user->id),
                (string) $user->name,
                (string) $user->email,
                (string) $teamName,
                (string) ($profile?->phone ?: ''),
                (string) ($snapshot['departmentName'] ?: ''),
                (string) ($snapshot['designation'] ?: 'Employee'),
                (string) ($snapshot['employmentStatus'] ?? 'active'),
                (string) ($this->effectiveJoinDate($user, $profile) ?: ''),
            ];
        })->values()->all();

        return $this->exportTabular('employees', $this->normalizeExportFormat($request), $headers, $rows);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EMPLOYEE_NOT_FOUND',
                    'message' => 'Employee not found.',
                ],
            ], 404);
        }

        $auth = $request->user();
        if ($this->canManageEmployee($request)) {
            return $this->updateEmployeeAsAdmin($request, $user);
        }

        if ($auth->id !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'You can only update your own profile.',
                ],
            ], 403);
        }

        return $this->updateEmployeeSelf($request, $user);
    }

    private function updateEmployeeAsAdmin(Request $request, User $user): JsonResponse
    {
        $this->normalizeEmployeeWritePayload($request);
        $validated = $request->validate($this->employeeWriteRules($request, false, $user));

        if (array_key_exists('teamId', $validated) || array_key_exists('team', $validated)) {
            if ($teamAssignmentError = $this->normalizeTeamAssignmentPayload($this->activeCompanyId($request), $validated)) {
                return $teamAssignmentError;
            }
        }

        if (array_key_exists('bankName', $validated)) {
            $validated['bankName'] = $this->normalizeBankName($validated['bankName']);
        }

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }
        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }
        $user->save();

        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['employment_status' => 'active', 'contract_type' => 'permanent'],
        );

        $profilePayload = [];
        $touchesOrg = array_key_exists('departmentId', $validated)
            || array_key_exists('designationId', $validated)
            || array_key_exists('designation', $validated);
        if ($touchesOrg) {
            $nextDeptId = $profile->department_id;
            if (array_key_exists('departmentId', $validated)) {
                $nextDeptId = $validated['departmentId'];
            }
            $nextDesigId = $profile->designation_id;
            if (array_key_exists('designationId', $validated)) {
                $nextDesigId = $validated['designationId'];
            }
            $legacyDesignation = $profile->designation;
            if (array_key_exists('designation', $validated)) {
                $legacyDesignation = $validated['designation'];
            }
            $org = $this->resolveOrganizationForWrite($nextDeptId, $nextDesigId, $legacyDesignation);
            if ($org instanceof JsonResponse) {
                return $org;
            }
            $profilePayload['department_id'] = $org['department_id'];
            $profilePayload['designation_id'] = $org['designation_id'];
            $profilePayload['designation'] = $org['designation'];
        }
        $fieldMap = [
            'team' => 'team',
            'teamId' => 'team_id',
            'managerUserId' => 'manager_user_id',
            'phone' => 'phone',
            'address' => 'address',
            'addressDetail' => 'address_detail',
            'provinceId' => 'province_id',
            'regencyId' => 'regency_id',
            'districtId' => 'district_id',
            'villageId' => 'village_id',
            'placeOfBirth' => 'place_of_birth',
            'dateOfBirth' => 'date_of_birth',
            'gender' => 'gender',
            'maritalStatus' => 'marital_status',
            'religion' => 'religion',
            'nationality' => 'nationality',
            'bio' => 'bio',
            'emergencyContacts' => 'emergency_contacts',
            'educationItems' => 'education_items',
            'experienceItems' => 'experience_items',
        ];
        foreach ($fieldMap as $requestKey => $column) {
            if (array_key_exists($requestKey, $validated)) {
                $profilePayload[$column] = $validated[$requestKey];
            }
        }
        if (array_key_exists('employmentStatus', $validated)) {
            $profilePayload['employment_status'] = $validated['employmentStatus'];
        }
        if (array_key_exists('nik', $validated) || array_key_exists('ktpNo', $validated)) {
            $profilePayload['nik'] = $validated['nik'] ?? ($validated['ktpNo'] ?? null);
        }
        if (array_key_exists('bankName', $validated)) {
            $profilePayload['bank_name'] = $validated['bankName'];
        }
        if (array_key_exists('bankAccountNo', $validated)) {
            $profilePayload['bank_account_no'] = $validated['bankAccountNo'];
        }
        if (array_key_exists('bankIfscCode', $validated)) {
            $profilePayload['bank_ifsc_code'] = $validated['bankIfscCode'];
        }
        if (array_key_exists('bankBranch', $validated)) {
            $profilePayload['bank_branch'] = $validated['bankBranch'];
        }
        if (array_key_exists('baseSalary', $validated)) {
            $profilePayload['base_salary'] = (float) ($validated['baseSalary'] ?? 0);
        }
        if (array_key_exists('fixedAllowance', $validated)) {
            $profilePayload['fixed_allowance'] = (float) ($validated['fixedAllowance'] ?? 0);
        }
        if (array_key_exists('hireDate', $validated)) {
            $profilePayload['hire_date'] = $validated['hireDate'];
        } elseif (array_key_exists('startDate', $validated)) {
            $profilePayload['hire_date'] = $validated['startDate'];
        }
        if (array_key_exists('contractType', $validated)) {
            $profilePayload['contract_type'] = $this->normalizeContractType($validated['contractType'] ?? 'permanent');
        }
        if (array_key_exists('contractStartDate', $validated)) {
            $profilePayload['contract_start_date'] = $validated['contractStartDate'];
        }
        if (array_key_exists('contractEndDate', $validated)) {
            $profilePayload['contract_end_date'] = $validated['contractEndDate'];
        }

        if ($profilePayload !== []) {
            $profile->fill($profilePayload);
            $profile->save();
        }

        $this->employeeSnapshotService->syncNormalizedRecords($profile, $validated, $touchesOrg ? $org : []);

        return $this->show($request, $user->id);
    }

    private function updateEmployeeSelf(Request $request, User $user): JsonResponse
    {
        $selfKeys = ['nik', 'ktpNo', 'phone', 'address', 'addressDetail', 'placeOfBirth', 'dateOfBirth', 'gender', 'maritalStatus', 'religion', 'nationality', 'bio', 'emergencyContacts', 'educationItems', 'experienceItems'];
        $present = collect(array_keys($request->all()))
            ->filter(fn (string $k) => ! in_array($k, ['_token'], true))
            ->values()
            ->all();
        $forbidden = array_diff($present, $selfKeys);
        if ($forbidden !== []) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Only personal/contact fields plus emergency/education/experience data can be updated on your own profile.',
                ],
            ], 403);
        }

        $this->normalizeEmployeeWritePayload($request);
        $validated = $request->validate($this->employeeWriteRules($request, false, $user, true));

        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['employment_status' => 'active', 'contract_type' => 'permanent'],
        );

        $fieldMap = [
            'phone' => 'phone',
            'address' => 'address',
            'addressDetail' => 'address_detail',
            'provinceId' => 'province_id',
            'regencyId' => 'regency_id',
            'districtId' => 'district_id',
            'villageId' => 'village_id',
            'placeOfBirth' => 'place_of_birth',
            'dateOfBirth' => 'date_of_birth',
            'gender' => 'gender',
            'maritalStatus' => 'marital_status',
            'religion' => 'religion',
            'nationality' => 'nationality',
            'bio' => 'bio',
            'emergencyContacts' => 'emergency_contacts',
            'educationItems' => 'education_items',
            'experienceItems' => 'experience_items',
        ];
        $profilePayload = [];
        foreach ($fieldMap as $requestKey => $column) {
            if (array_key_exists($requestKey, $validated)) {
                $profilePayload[$column] = $validated[$requestKey];
            }
        }
        if (array_key_exists('nik', $validated) || array_key_exists('ktpNo', $validated)) {
            $profilePayload['nik'] = $validated['nik'] ?? ($validated['ktpNo'] ?? null);
        }
        if ($profilePayload !== []) {
            $profile->fill($profilePayload);
            $profile->save();
        }

        $this->employeeSnapshotService->syncNormalizedRecords($profile, $validated);

        return $this->show($request, $user->id);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $auth = $request->user();
        if (! $this->canManageEmployee($request) && $auth->id !== $id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'You can only view your own employee profile.',
                ],
            ], 403);
        }

        $user = User::query()->find($id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EMPLOYEE_NOT_FOUND',
                    'message' => 'Employee not found.',
                ],
            ], 404);
        }

        $profile = EmployeeProfile::query()
            ->where('user_id', $user->id)
            ->with(['department:id,name', 'designationRef:id,name,department_id', 'province:id,name', 'regency:id,name,province_id', 'district:id,name,regency_id', 'village:id,name,district_id'])
            ->first();
        $snapshot = $this->employeeSnapshotService->snapshotForProfile($profile, $user);
        $employmentStatus = $snapshot['employmentStatus'] ?? 'active';
        $designationDisplay = $snapshot['designation'] ?: 'Employee';
        $schedule = HcmScheduleTiming::query()
            ->with('shift:id,name')
            ->where('user_id', $user->id)
            ->first();
        $pkwtSummary = $this->pkwtCompensationService->summarizeProfile($profile);
        $scheduleSource = $schedule?->source ?: 'auto';
        $scheduleStart = $schedule?->start_time ? substr((string) $schedule->start_time, 0, 5) : null;
        $scheduleEnd = $schedule?->end_time ? substr((string) $schedule->end_time, 0, 5) : null;
        $scheduleLabel = ($scheduleStart && $scheduleEnd)
            ? ($scheduleStart.' - '.$scheduleEnd)
            : 'Auto (follow attendance default)';

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'employeeNo' => $this->formatEmployeeNo($user->id),
                'fullName' => $user->name,
                'email' => $user->email,
                'departmentId' => $snapshot['departmentId'],
                'departmentName' => $snapshot['departmentName'] ?? '—',
                'designationId' => $snapshot['designationId'],
                'designationName' => $snapshot['designationName'],
                'designation' => $designationDisplay,
                'team' => $snapshot['team'] ?: 'HCM',
                'employeeType' => $snapshot['employeeType'],
                'baseSalary' => (float) ($snapshot['baseSalary'] ?? 0),
                'fixedAllowance' => (float) ($snapshot['fixedAllowance'] ?? 0),
                'employmentStatus' => $employmentStatus,
                'hireDate' => optional($profile?->hire_date)->toDateString(),
                'startDate' => $snapshot['startDate'],
                'joinDate' => $this->effectiveJoinDate($user, $profile),
                'contractType' => $pkwtSummary['contractType'],
                'contractStartDate' => $pkwtSummary['contractStartDate'],
                'contractEndDate' => $pkwtSummary['contractEndDate'],
                'pkwtDueThisMonth' => (bool) $pkwtSummary['isDueThisMonth'],
                'estimatedPkwtCompensationThisMonth' => (float) $pkwtSummary['estimatedCompensationThisMonth'],
                'reportOffice' => '-',
                'phone' => $profile?->phone ?: '-',
                'address' => $profile?->address ?: '-',
                'addressDetail' => $profile?->address_detail ?: '-',
                'addressRegion' => [
                    'provinceId' => $profile?->province_id,
                    'provinceName' => $profile?->province?->name,
                    'regencyId' => $profile?->regency_id,
                    'regencyName' => $profile?->regency?->name,
                    'districtId' => $profile?->district_id,
                    'districtName' => $profile?->district?->name,
                    'villageId' => $profile?->village_id,
                    'villageName' => $profile?->village?->name,
                ],
                'bio' => $profile?->bio ?: '-',
                'nik' => $snapshot['personal']['nik'] ?? null,
                'ktpNo' => $snapshot['personal']['ktpNo'] ?? null,
                'placeOfBirth' => $snapshot['personal']['placeOfBirth'] ?? null,
                'dateOfBirth' => $snapshot['personal']['dateOfBirth'] ?? null,
                'gender' => $snapshot['personal']['gender'] ?? null,
                'maritalStatus' => $snapshot['personal']['maritalStatus'] ?? null,
                'religion' => $snapshot['personal']['religion'] ?? null,
                'nationality' => $snapshot['personal']['nationality'] ?? null,
                'personal' => $snapshot['personal'] ?? [],
                'assignment' => [
                    'team' => $snapshot['team'],
                    'departmentId' => $snapshot['departmentId'],
                    'departmentName' => $snapshot['departmentName'],
                    'designationId' => $snapshot['designationId'],
                    'designationName' => $snapshot['designationName'],
                    'managerUserId' => $snapshot['managerUserId'],
                    'managerName' => $snapshot['managerName'] ?? null,
                ],
                'compensation' => $snapshot['compensation'],
                'contract' => [
                    'contractType' => $snapshot['contract']['contractType'] ?? $pkwtSummary['contractType'],
                    'startDate' => $snapshot['contract']['startDate'] ?? $pkwtSummary['contractStartDate'],
                    'endDate' => $snapshot['contract']['endDate'] ?? $pkwtSummary['contractEndDate'],
                    'status' => $snapshot['contract']['status'] ?? null,
                ],
                'employmentHistory' => $snapshot['employmentHistory'] ?? [],
                'assignmentHistory' => $snapshot['assignmentHistory'] ?? [],
                'compensationHistory' => $snapshot['compensationHistory'] ?? [],
                'contractHistory' => $snapshot['contractHistory'] ?? [],
                'bankAccounts' => $snapshot['bankAccounts'] ?? [],
                'documents' => $snapshot['documents'] ?? [],
                'bank' => [
                    'name' => $snapshot['bank']['name'] ?: '-',
                    'accountNo' => $snapshot['bank']['accountNo'] ?: '-',
                    'accountHolderName' => $snapshot['bank']['accountHolderName'] ?: '-',
                    'ifscCode' => $snapshot['bank']['ifscCode'] ?: '-',
                    'branch' => $snapshot['bank']['branch'] ?: '-',
                ],
                'taxProfile' => $snapshot['taxProfile'],
                'benefits' => $snapshot['benefits'],
                'emergencyContacts' => $snapshot['emergencyContacts'],
                'educationItems' => $snapshot['educationItems'],
                'experienceItems' => $snapshot['experienceItems'],
                'schedule' => [
                    'source' => $scheduleSource,
                    'sourceLabel' => $scheduleSource === 'manual' ? 'Manual override' : 'Auto',
                    'startTime' => $scheduleStart,
                    'endTime' => $scheduleEnd,
                    'display' => $scheduleLabel,
                    'shiftId' => $schedule?->hcm_shift_id,
                    'shiftName' => $schedule?->shift?->name,
                ],
                'profilePhotoUrl' => $this->profilePhotoUrl($profile?->profile_photo_path),
            ],
        ]);
    }

    public function uploadProfilePhoto(Request $request, int $id): JsonResponse
    {
        $auth = $request->user();
        if (! $this->canManageEmployee($request) && $auth->id !== $id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'You are not allowed to update this employee photo.',
                ],
            ], 403);
        }

        $user = User::query()->find($id);
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EMPLOYEE_NOT_FOUND',
                    'message' => 'Employee not found.',
                ],
            ], 404);
        }

        $validated = $request->validate([
            'photo' => ['required', 'file'],
        ]);

        $profile = EmployeeProfile::query()->firstOrCreate(['user_id' => $user->id]);

        try {
            $stored = $this->avatarStorage->replace(
                $profile->profile_photo_path,
                $validated['photo'],
                $user->id,
            );
        } catch (InvalidMediaException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_MEDIA',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }

        $profile->update(['profile_photo_path' => $stored->path]);

        return response()->json([
            'success' => true,
            'data' => [
                'profilePhotoUrl' => $this->profilePhotoUrl($stored->path),
            ],
        ]);
    }

    public function bulkTemplate(Request $request)
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $headers = [
            'employee_no', 'name', 'email', 'password', 'confirm_password',
            'team_id', 'team', 'department_id', 'designation_id', 'designation', 'employment_status', 'employee_type', 'start_date', 'probation_end_date',
            'base_salary', 'fixed_allowance', 'salary_type',
            'contract_type', 'contract_status', 'contract_start_date', 'contract_end_date', 'manager_user_id',
            'nik', 'phone', 'address', 'place_of_birth', 'date_of_birth', 'gender', 'marital_status', 'religion', 'nationality', 'bio',
            'bank_name', 'bank_account_no', 'bank_account_holder_name', 'bank_ifsc_code', 'bank_branch',
            'npwp', 'tax_status', 'ptkp_status', 'bpjs_kesehatan_no', 'bpjs_ketenagakerjaan_no',
        ];

        $rows = [
            [
                $this->formatEmployeeNo(1), 'Budi Santoso', 'budi@company.com', '', '',
                '', 'HR Shared Services', 1, 1, 'HR Officer', 'active', 'permanent', '2024-01-15', '',
                5000000, 750000, 'monthly',
                'permanent', 'active', '2024-01-15', '', '',
                '3175010101900001', '08123456789', 'Jakarta', 'Jakarta', '1990-01-01', 'male', 'married', 'Islam', 'Indonesia', 'HR Admin',
                'BCA', '1234567890', 'Budi Santoso', 'BCA001', 'Jakarta Pusat',
                '', 'TK0', 'TK0', 'BPKES001', 'BPTK001',
            ],
            [
                '', 'Siti Aminah', 'siti@company.com', 'StrongPass1', 'StrongPass1',
                '', 'Finance Operations', 2, 3, 'Finance Staff', 'probation', 'contract', '2025-02-01', '2025-05-01',
                6200000, 1000000, 'monthly',
                'contract', 'active', '2025-02-01', '2026-01-31', '',
                '3174010101900001', '08129876543', 'Bandung', 'Bandung', '1990-01-01', 'female', 'single', 'Islam', 'Indonesia', '',
                'Bank Mandiri', '9876543210', 'Siti Aminah', 'MDR001', 'Bandung',
                '', 'TK0', 'TK0', 'BPKES002', 'BPTK002',
            ],
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('employee_bulk_data');
        $sheet->fromArray(array_merge([$headers], $rows), null, 'A1');

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastColumn.'1');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF8F9FA');

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $width = in_array($header, ['address', 'bio', 'bank_account_holder_name'], true) ? 28 : 18;
            if (in_array($header, ['name', 'email', 'team', 'designation'], true)) {
                $width = 24;
            }
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Department $department) => [$department->id, $department->name, $department->code])
            ->values()
            ->all();

        $designations = Designation::query()
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'department_id', 'name', 'code'])
            ->map(fn (Designation $designation) => [
                $designation->id,
                $designation->department_id,
                $designation->department?->name,
                $designation->name,
                $designation->code,
            ])
            ->values()
            ->all();

        $teams = DB::table('teams')
            ->leftJoin('departments', 'departments.id', '=', 'teams.department_id')
            ->select('teams.id', 'teams.department_id', 'teams.name', 'departments.name as department_name')
            ->orderBy('departments.name')
            ->orderBy('teams.name')
            ->get()
            ->map(fn ($team) => [$team->id, $team->department_id, $team->department_name, $team->name])
            ->values()
            ->all();

        $banks = array_map(fn (string $bank) => [$bank], $this->allowedBankNames());
        $employmentStatuses = $this->employmentStatusOptions();
        $salaryTypes = $this->salaryTypeOptions();
        $contractTypes = $this->acceptedContractTypeInputs();
        $contractStatuses = $this->contractStatusOptions();
        $genders = ['male', 'female', 'other'];
        $maritalStatuses = $this->maritalStatusOptions();
        $religions = $this->religionOptions();
        $taxStatuses = $this->acceptedTaxStatusInputs();
        $maxEnumRows = max(count($employmentStatuses), count($salaryTypes), count($contractTypes), count($contractStatuses), count($genders), count($maritalStatuses), count($religions), count($taxStatuses));
        $enumRows = [];
        for ($i = 0; $i < $maxEnumRows; $i++) {
            $enumRows[] = [
                $employmentStatuses[$i] ?? null,
                $salaryTypes[$i] ?? null,
                $contractTypes[$i] ?? null,
                $contractStatuses[$i] ?? null,
                $genders[$i] ?? null,
                $maritalStatuses[$i] ?? null,
                $religions[$i] ?? null,
                $taxStatuses[$i] ?? null,
            ];
        }

        $this->hydrateBulkReferenceSheet($spreadsheet->createSheet(), 'ref_departments', ['id', 'name', 'code'], $departments);
        $this->hydrateBulkReferenceSheet($spreadsheet->createSheet(), 'ref_designations', ['id', 'department_id', 'department_name', 'name', 'code'], $designations);
        $this->hydrateBulkReferenceSheet($spreadsheet->createSheet(), 'ref_teams', ['id', 'department_id', 'department_name', 'name'], $teams);
        $this->hydrateBulkReferenceSheet($spreadsheet->createSheet(), 'ref_banks', ['bank_name'], $banks);
        $this->hydrateBulkReferenceSheet($spreadsheet->createSheet(), 'ref_enums', ['employment_status', 'salary_type', 'contract_type', 'contract_status', 'gender', 'marital_status', 'religion', 'tax_status'], $enumRows);

        $validationEndRow = 250;
        $this->applyDropdownValidation($sheet, 'F2:F'.$validationEndRow, '=ref_teams!$A$2:$A$'.max(count($teams) + 1, 2), 'Team ID');
        $this->applyDropdownValidation($sheet, 'H2:H'.$validationEndRow, '=ref_departments!$A$2:$A$'.max(count($departments) + 1, 2), 'Department ID');
        $this->applyDropdownValidation($sheet, 'I2:I'.$validationEndRow, '=ref_designations!$A$2:$A$'.max(count($designations) + 1, 2), 'Designation ID');
        $this->applyDropdownValidation($sheet, 'K2:K'.$validationEndRow, '=ref_enums!$A$2:$A$'.max(count($employmentStatuses) + 1, 2), 'Employment Status');
        $this->applyDropdownValidation($sheet, 'Q2:Q'.$validationEndRow, '=ref_enums!$B$2:$B$'.max(count($salaryTypes) + 1, 2), 'Salary Type');
        $this->applyDropdownValidation($sheet, 'R2:R'.$validationEndRow, '=ref_enums!$C$2:$C$'.max(count($contractTypes) + 1, 2), 'Contract Type');
        $this->applyDropdownValidation($sheet, 'S2:S'.$validationEndRow, '=ref_enums!$D$2:$D$'.max(count($contractStatuses) + 1, 2), 'Contract Status');
        $this->applyDropdownValidation($sheet, 'AB2:AB'.$validationEndRow, '=ref_enums!$E$2:$E$'.max(count($genders) + 1, 2), 'Gender');
        $this->applyDropdownValidation($sheet, 'AC2:AC'.$validationEndRow, '=ref_enums!$F$2:$F$'.max(count($maritalStatuses) + 1, 2), 'Marital Status');
        $this->applyDropdownValidation($sheet, 'AD2:AD'.$validationEndRow, '=ref_enums!$G$2:$G$'.max(count($religions) + 1, 2), 'Religion');
        $this->applyDropdownValidation($sheet, 'AG2:AG'.$validationEndRow, '=ref_banks!$A$2:$A$'.max(count($banks) + 1, 2), 'Bank Name');
        $this->applyDropdownValidation($sheet, 'AM2:AM'.$validationEndRow, '=ref_enums!$H$2:$H$'.max(count($taxStatuses) + 1, 2), 'Tax Status');
        $this->applyDropdownValidation($sheet, 'AN2:AN'.$validationEndRow, '=ref_enums!$H$2:$H$'.max(count($taxStatuses) + 1, 2), 'PTKP Status');

        $tmp = tempnam(sys_get_temp_dir(), 'employee-bulk-template-');
        if ($tmp === false) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEMPLATE_GENERATION_FAILED',
                    'message' => 'Failed to prepare employee bulk template.',
                ],
            ], 500);
        }
        $tmpPath = $tmp.'.xlsx';
        @rename($tmp, $tmpPath);

        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpPath);
        $spreadsheet->disconnectWorksheets();
        unset($writer, $spreadsheet);

        return response()->download($tmpPath, 'employee-bulk-template.xlsx')->deleteFileAfterSend(true);
    }

    public function bulkUpload(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to bulk upload employees.',
                ],
            ], 422);
        }

        /** @var Company $company */
        $company = Company::query()->findOrFail($activeCompanyId);
        $employeeValidator = app(EmployeeCountValidator::class);
        $planLimit = $employeeValidator->getPlanEmployeeLimit($company);
        $currentEmployeeCount = $employeeValidator->getActiveEmployeeCount($company->id);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $file = $validated['file'];
        $rows = $this->parseBulkRows($file);
        if ($rows === []) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'EMPTY_FILE',
                    'message' => 'No employee rows found in uploaded file.',
                ],
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        try {
            DB::transaction(function () use (
                $rows,
                &$created,
                &$updated,
                &$errors,
                $activeCompanyId,
                $planLimit,
                $currentEmployeeCount,
            ): void {
                foreach ($rows as $index => $row) {
                    $lineNo = $index + 2;
                    $employeeNo = strtoupper(trim((string) ($row['employee_no'] ?? '')));
                    $email = strtolower(trim((string) ($row['email'] ?? '')));
                    $name = trim((string) ($row['name'] ?? ''));
                    $password = (string) ($row['password'] ?? '');
                    $confirmPassword = (string) ($row['confirm_password'] ?? '');
                    $employmentStatus = strtolower(trim((string) ($row['employment_status'] ?? 'active')));
                    $salaryType = strtolower(trim((string) ($row['salary_type'] ?? 'monthly')));
                    $contractTypeInput = $this->nullableString($row['contract_type'] ?? null);
                    $contractStatus = strtolower(trim((string) ($row['contract_status'] ?? '')));
                    $gender = $this->nullableString($row['gender'] ?? null);
                    $maritalStatus = $this->nullableString($row['marital_status'] ?? null);
                    $religion = $this->nullableString($row['religion'] ?? null);
                    $teamId = isset($row['team_id']) && is_numeric($row['team_id']) ? (int) $row['team_id'] : null;
                    $teamNameInput = $this->nullableString($row['team'] ?? null);
                    $bankName = $this->normalizeBankName($this->nullableString($row['bank_name'] ?? null));
                    $taxStatus = strtoupper((string) ($this->nullableString($row['tax_status'] ?? ($row['ptkp_status'] ?? null)) ?? ''));
                    $probationEndDate = $this->nullableString($row['probation_end_date'] ?? null);

            $baseSalaryRaw = $row['base_salary'] ?? 0;
            $fixedAllowanceRaw = $row['fixed_allowance'] ?? 0;
            if (!is_numeric($baseSalaryRaw) || !is_numeric($fixedAllowanceRaw)) {
                $errors[] = "Row {$lineNo}: base_salary/fixed_allowance harus angka.";
                continue;
            }
            $baseSalary = (float) $baseSalaryRaw;
            $fixedAllowance = (float) $fixedAllowanceRaw;
            if ($baseSalary < 0 || $fixedAllowance < 0) {
                $errors[] = "Row {$lineNo}: salary tidak boleh negatif.";
                continue;
            }
            if (!in_array($employmentStatus, $this->employmentStatusOptions(), true)) {
                $errors[] = "Row {$lineNo}: employment_status harus salah satu dari ".implode('|', $this->employmentStatusOptions()).'.';
                continue;
            }
            if (!in_array($salaryType, $this->salaryTypeOptions(), true)) {
                $errors[] = "Row {$lineNo}: salary_type harus monthly|daily|hourly.";
                continue;
            }
            if ($contractTypeInput !== null && !in_array(strtolower($contractTypeInput), $this->acceptedContractTypeInputs(), true)) {
                $errors[] = "Row {$lineNo}: contract_type harus contract|permanent (alias pkwt|pkwtt masih diterima saat migrasi).";
                continue;
            }
            if ($contractStatus !== '' && !in_array($contractStatus, $this->contractStatusOptions(), true)) {
                $errors[] = "Row {$lineNo}: contract_status harus active|ended|terminated.";
                continue;
            }
            if ($gender !== null && !in_array($gender, ['male', 'female', 'other'], true)) {
                $errors[] = "Row {$lineNo}: gender harus male|female|other.";
                continue;
            }
            if ($maritalStatus !== null && !in_array($maritalStatus, $this->maritalStatusOptions(), true)) {
                $errors[] = "Row {$lineNo}: marital_status tidak valid.";
                continue;
            }
            if ($religion !== null && !in_array($religion, $this->religionOptions(), true)) {
                $errors[] = "Row {$lineNo}: religion harus mengikuti daftar agama Indonesia yang disediakan.";
                continue;
            }
            if ($bankName !== null && !in_array($bankName, $this->acceptedBankNames(), true)) {
                $errors[] = "Row {$lineNo}: bank_name tidak ada dalam daftar bank Indonesia yang didukung.";
                continue;
            }
            if ($taxStatus !== '' && !in_array($taxStatus, $this->acceptedTaxStatusInputs(), true)) {
                $errors[] = "Row {$lineNo}: tax_status harus TK0-TK3 atau K0-K3 (alias TK/K masih diterima untuk kompatibilitas).";
                continue;
            }

            $user = null;
            $userIdFromNo = $this->parseEmployeeNoToUserId($employeeNo);
            $userByEmployeeNo = $userIdFromNo !== null ? User::query()->find($userIdFromNo) : null;
            $userByEmail = $email !== '' ? User::query()->where('email', $email)->first() : null;

            if ($userByEmployeeNo && $userByEmail && $userByEmployeeNo->id !== $userByEmail->id) {
                $errors[] = "Row {$lineNo}: employee_no dan email mengacu ke user yang berbeda. Perbaiki salah satu identitas sebelum import.";
                continue;
            }

            $user = $userByEmployeeNo ?: $userByEmail;

            if (! $user) {
                if ($planLimit !== null && ($currentEmployeeCount + $created + 1) > (int) $planLimit) {
                    throw new \App\Exceptions\SubscriptionValidationException(
                        'EMPLOYEE_COUNT_EXCEEDED',
                        "Cannot add more employees. Current: {$currentEmployeeCount}, trying to add: ".($created + 1).", plan limit: {$planLimit}",
                        422
                    );
                }
                if ($name === '' || $email === '') {
                    $errors[] = "Row {$lineNo}: untuk create baru, name dan email wajib diisi.";
                    continue;
                }
                if ($password === '' || $confirmPassword === '' || $password !== $confirmPassword) {
                    $errors[] = "Row {$lineNo}: password dan confirm_password wajib serta harus sama untuk create baru.";
                    continue;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row {$lineNo}: email tidak valid.";
                    continue;
                }
                if (User::query()->where('email', $email)->exists()) {
                    $errors[] = "Row {$lineNo}: email {$email} sudah digunakan.";
                    continue;
                }

                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                ]);
                $created++;
            } else {
                if ($name !== '') {
                    $user->name = $name;
                }
                if ($email !== '' && $email !== strtolower((string) $user->email)) {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Row {$lineNo}: email tidak valid.";
                        continue;
                    }
                    $exists = User::query()->where('email', $email)->where('id', '!=', $user->id)->exists();
                    if ($exists) {
                        $errors[] = "Row {$lineNo}: email {$email} sudah dipakai user lain.";
                        continue;
                    }
                    $user->email = $email;
                }
                if ($password !== '') {
                    if ($password !== $confirmPassword) {
                        $errors[] = "Row {$lineNo}: confirm_password harus sama dengan password.";
                        continue;
                    }
                    $user->password = Hash::make($password);
                }
                $user->save();
                $updated++;
            }

            $profile = EmployeeProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'company_id' => $activeCompanyId,
                    'employment_status' => 'active',
                    'contract_type' => 'permanent',
                ],
            );
            $profile->company_id = $activeCompanyId;
            $bulkDeptId = isset($row['department_id']) && is_numeric($row['department_id']) ? (int) $row['department_id'] : null;
            $bulkDesigId = isset($row['designation_id']) && is_numeric($row['designation_id']) ? (int) $row['designation_id'] : null;
            $resolvedTeam = null;
            if ($teamId !== null) {
                $resolvedTeam = DB::table('teams')
                    ->select('id', 'department_id', 'name', 'is_active')
                    ->where('company_id', $activeCompanyId)
                    ->where('id', $teamId)
                    ->first();
                if (! $resolvedTeam) {
                    $errors[] = "Row {$lineNo}: team_id tidak ditemukan pada master teams.";
                    continue;
                }
                if (! (bool) ($resolvedTeam->is_active ?? false)) {
                    $errors[] = "Row {$lineNo}: team_id mengacu ke team inactive dan tidak boleh dipakai assignment.";
                    continue;
                }
                if ($bulkDeptId === null && $resolvedTeam->department_id !== null) {
                    $bulkDeptId = (int) $resolvedTeam->department_id;
                }
                if ($bulkDeptId !== null && $resolvedTeam->department_id !== null && (int) $resolvedTeam->department_id !== (int) $bulkDeptId) {
                    $errors[] = "Row {$lineNo}: team_id tidak sesuai dengan department_id yang dipilih.";
                    continue;
                }
                if ($teamNameInput !== null && strcasecmp((string) $teamNameInput, (string) $resolvedTeam->name) !== 0) {
                    $errors[] = "Row {$lineNo}: team_id dan team mengacu ke nama yang berbeda.";
                    continue;
                }
                $teamNameInput = (string) $resolvedTeam->name;
            } elseif ($teamNameInput !== null) {
                $resolvedByName = DB::table('teams')
                    ->select('id', 'department_id', 'name', 'is_active')
                    ->where('company_id', $activeCompanyId)
                    ->whereRaw('LOWER(name) = ?', [strtolower((string) $teamNameInput)])
                    ->first();

                if ($resolvedByName) {
                    if (! (bool) ($resolvedByName->is_active ?? false)) {
                        $errors[] = "Row {$lineNo}: team mengacu ke team inactive dan tidak boleh dipakai assignment.";
                        continue;
                    }
                    $teamId = (int) $resolvedByName->id;
                    $teamNameInput = (string) $resolvedByName->name;
                    if ($bulkDeptId === null && $resolvedByName->department_id !== null) {
                        $bulkDeptId = (int) $resolvedByName->department_id;
                    }
                    if ($bulkDeptId !== null && $resolvedByName->department_id !== null && (int) $resolvedByName->department_id !== (int) $bulkDeptId) {
                        $errors[] = "Row {$lineNo}: team tidak sesuai dengan department_id yang dipilih.";
                        continue;
                    }
                }
            }

            $profile->team = $teamNameInput;
            $profile->team_id = $teamId;
            if ($bulkDeptId && ! Department::query()->whereKey($bulkDeptId)->exists()) {
                $errors[] = "Row {$lineNo}: department_id tidak ditemukan.";
                continue;
            }
            if ($bulkDesigId && ! Designation::query()->whereKey($bulkDesigId)->exists()) {
                $errors[] = "Row {$lineNo}: designation_id tidak ditemukan.";
                continue;
            }
            $orgBulk = $this->resolveOrganizationForWrite(
                $bulkDeptId,
                $bulkDesigId,
                $this->nullableString($row['designation'] ?? null),
            );
            if ($orgBulk instanceof JsonResponse) {
                $errors[] = "Row {$lineNo}: kombinasi department/jabatan tidak valid.";
                continue;
            }
            $profile->department_id = $orgBulk['department_id'];
            $profile->designation_id = $orgBulk['designation_id'];
            $profile->designation = $orgBulk['designation'];
            $profile->employment_status = $employmentStatus;
            $profile->hire_date = $this->nullableString($row['start_date'] ?? null) ?? $profile->hire_date;
            $profile->base_salary = $baseSalary;
            $profile->fixed_allowance = $fixedAllowance;
            $profile->contract_type = $this->normalizeContractType($contractTypeInput ?? ($profile->contract_type ?? 'permanent'));
            $profile->contract_start_date = $this->nullableString($row['contract_start_date'] ?? null) ?? $profile->contract_start_date;
            $profile->contract_end_date = $this->nullableString($row['contract_end_date'] ?? null) ?? $profile->contract_end_date;
            $profile->nik = $this->nullableString($row['nik'] ?? ($row['ktp_no'] ?? null));
            $profile->phone = $this->nullableString($row['phone'] ?? null);
            $profile->address = $this->nullableString($row['address'] ?? null);
            $profile->address_detail = $this->nullableString($row['address_detail'] ?? null);
            $profile->province_id = isset($row['province_id']) && is_numeric($row['province_id']) ? (int) $row['province_id'] : $profile->province_id;
            $profile->regency_id = isset($row['regency_id']) && is_numeric($row['regency_id']) ? (int) $row['regency_id'] : $profile->regency_id;
            $profile->district_id = isset($row['district_id']) && is_numeric($row['district_id']) ? (int) $row['district_id'] : $profile->district_id;
            $profile->village_id = isset($row['village_id']) && is_numeric($row['village_id']) ? (int) $row['village_id'] : $profile->village_id;
            $profile->place_of_birth = $this->nullableString($row['place_of_birth'] ?? null);
            $profile->date_of_birth = $this->nullableString($row['date_of_birth'] ?? null);
            $profile->gender = $gender;
            $profile->marital_status = $maritalStatus;
            $profile->religion = $religion;
            $profile->nationality = $this->nullableString($row['nationality'] ?? null);
            $profile->bio = $this->nullableString($row['bio'] ?? null);
            $profile->bank_name = $bankName;
            $profile->bank_account_no = $this->nullableString($row['bank_account_no'] ?? null);
            $profile->bank_ifsc_code = $this->nullableString($row['bank_ifsc_code'] ?? null);
            $profile->bank_branch = $this->nullableString($row['bank_branch'] ?? null);
                    $profile->save();

                    $this->employeeSnapshotService->syncNormalizedRecords($profile, [
                        'teamId' => $teamId,
                        'team' => $profile->team,
                        'employmentStatus' => $employmentStatus,
                        'probationEndDate' => $probationEndDate,
                        'startDate' => optional($profile->hire_date)->toDateString(),
                        'baseSalary' => $baseSalary,
                        'fixedAllowance' => $fixedAllowance,
                        'salaryType' => $salaryType,
                        'contractType' => $contractTypeInput,
                        'contractStatus' => $contractStatus !== '' ? $contractStatus : null,
                        'contractStartDate' => $this->nullableString($row['contract_start_date'] ?? null),
                        'contractEndDate' => $this->nullableString($row['contract_end_date'] ?? null),
                        'bankName' => $profile->bank_name,
                        'bankAccountNo' => $profile->bank_account_no,
                        'bankAccountHolderName' => $this->nullableString($row['bank_account_holder_name'] ?? null),
                        'bankIfscCode' => $profile->bank_ifsc_code,
                        'bankBranch' => $profile->bank_branch,
                        'employeeType' => $this->nullableString($row['employee_type'] ?? null),
                        'npwp' => $this->nullableString($row['npwp'] ?? null),
                        'taxStatus' => $taxStatus !== '' ? $taxStatus : null,
                        'ptkpStatus' => $taxStatus !== '' ? $taxStatus : null,
                        'bpjsKesehatanNo' => $this->nullableString($row['bpjs_kesehatan_no'] ?? null),
                        'bpjsKetenagakerjaanNo' => $this->nullableString($row['bpjs_ketenagakerjaan_no'] ?? null),
                    ], $orgBulk);
                }

                if ($errors !== []) {
                    throw new \RuntimeException('BULK_UPLOAD_VALIDATION_FAILED');
                }
            });
        } catch (\RuntimeException $e) {
            if ($errors === []) {
                throw $e;
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BULK_UPLOAD_VALIDATION_FAILED',
                    'message' => 'Bulk upload dibatalkan karena ada baris yang tidak valid. Tidak ada perubahan yang disimpan.',
                ],
                'data' => [
                    'createdRows' => 0,
                    'updatedRows' => 0,
                    'failedRows' => count($errors),
                    'errors' => $errors,
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'createdRows' => $created,
                'updatedRows' => $updated,
                'failedRows' => count($errors),
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function hydrateBulkReferenceSheet(Worksheet $sheet, string $title, array $headers, array $rows): void
    {
        $sheet->setTitle($title);
        $sheet->fromArray(array_merge([$headers], $rows), null, 'A1');
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastColumn.'1');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF8F9FA');

        foreach ($headers as $index => $header) {
            $width = strlen((string) $header) > 14 ? 22 : 18;
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }
    }

    private function applyDropdownValidation(Worksheet $sheet, string $range, string $formula, string $label): void
    {
        [$startCell, $endCell] = array_pad(explode(':', $range, 2), 2, $range);
        [$startColumn, $startRow] = Coordinate::coordinateFromString($startCell);
        [$endColumn, $endRow] = Coordinate::coordinateFromString($endCell);
        $startIndex = Coordinate::columnIndexFromString($startColumn);
        $endIndex = Coordinate::columnIndexFromString($endColumn);

        $template = new DataValidation();
        $template->setType(DataValidation::TYPE_LIST);
        $template->setErrorStyle(DataValidation::STYLE_STOP);
        $template->setAllowBlank(true);
        $template->setShowDropDown(true);
        $template->setShowInputMessage(true);
        $template->setShowErrorMessage(true);
        $template->setErrorTitle('Nilai tidak valid');
        $template->setError('Gunakan nilai dari sheet referensi template bulk employee.');
        $template->setPromptTitle($label);
        $template->setPrompt('Pilih nilai yang tersedia dari daftar referensi template.');
        $template->setFormula1($formula);

        for ($row = (int) $startRow; $row <= (int) $endRow; $row++) {
            for ($column = $startIndex; $column <= $endIndex; $column++) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row)
                    ->setDataValidation(clone $template);
            }
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parseBulkRows(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheet(0);
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        $highestRow = $sheet->getHighestRow();
        if ($highestRow < 2) {
            return [];
        }

        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $rawHeader = (string) $sheet->getCell([$col, 1])->getCalculatedValue();
            $headers[$col] = strtolower(trim($rawHeader));
        }

        $rows = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $item = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $key = $headers[$col] ?? '';
                if ($key === '') {
                    continue;
                }
                $item[$key] = $sheet->getCell([$col, $row])->getCalculatedValue();
            }
            if (($item['employee_no'] ?? '') === '' && ($item['email'] ?? '') === '' && ($item['name'] ?? '') === '') {
                continue;
            }
            $rows[] = $item;
        }

        return $rows;
    }

    private function parseEmployeeNoToUserId(string $employeeNo): ?int
    {
        if ($employeeNo === '') {
            return null;
        }

        $prefix = preg_quote($this->employeeNoPrefix(), '/');
        if (!preg_match('/^'.$prefix.'\d+$/', $employeeNo)) {
            return null;
        }

        $idPart = ltrim(substr($employeeNo, strlen($this->employeeNoPrefix())), '0');
        $userId = $idPart === '' ? 0 : (int) $idPart;

        return $userId > 0 ? $userId : null;
    }

    private function employeeNoPrefix(): string
    {
        return WebsiteSettings::prefixEmployee();
    }

    private function formatEmployeeNo(int $userId): string
    {
        return sprintf('%s%04d', $this->employeeNoPrefix(), $userId);
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

    public function departments(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:200'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 20);
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $activeCompanyId = $this->activeCompanyId($request);

        $query = Department::query();
        $this->applyTenantScope($query, $activeCompanyId);

        $paginator = $query
            ->withCount('designations')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->when($status, function ($query) use ($status) {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('name')
            ->paginate($perPage);

        $rows = $paginator->getCollection()
            ->map(function (Department $department) {
                return [
                    'id' => $department->id,
                    'code' => $department->code,
                    'name' => $department->name,
                    'designationCount' => $department->designations_count,
                    'isActive' => (bool) $department->is_active,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->where(function ($query) use ($activeCompanyId): void {
                    if ($activeCompanyId) {
                        $query->where('company_id', $activeCompanyId);
                    }
                }),
            ],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $department = Department::query()->create([
            'company_id' => $activeCompanyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $this->slugCode($validated['name']),
            'is_active' => (bool) ($validated['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => $department], 201);
    }

    public function exportDepartments(Request $request)
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'format' => ['nullable', Rule::in(['xlsx', 'csv', 'pdf'])],
        ]);

        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $activeCompanyId = $this->activeCompanyId($request);

        $query = Department::query();
        $this->applyTenantScope($query, $activeCompanyId);

        $rows = $query
            ->withCount('designations')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->when($status, function ($query) use ($status) {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('name')
            ->get()
            ->map(static fn (Department $department): array => [
                (string) $department->name,
                (string) $department->code,
                (int) $department->designations_count,
                $department->is_active ? 'Active' : 'Inactive',
            ])
            ->values()
            ->all();

        return $this->exportTabular('departments', $this->normalizeExportFormat($request), ['Name', 'Code', 'Designations Linked', 'Status'], $rows);
    }

    public function updateDepartment(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        $departmentQuery = Department::query();
        $this->applyIdentifierScope($departmentQuery, $id);
        $this->applyTenantScope($departmentQuery, $activeCompanyId);
        $department = $departmentQuery->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments', 'code')
                    ->ignore($department->id)
                    ->where(function ($query) use ($activeCompanyId): void {
                        if ($activeCompanyId) {
                            $query->where('company_id', $activeCompanyId);
                        }
                    }),
            ],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $department->update([
            'company_id' => $department->company_id ?: $activeCompanyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $this->slugCode($validated['name']),
            'is_active' => (bool) ($validated['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => $department]);
    }

    public function designations(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:200'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 20);
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $departmentId = $validated['departmentId'] ?? null;
        $activeCompanyId = $this->activeCompanyId($request);

        $query = Designation::query();
        $this->applyTenantScope($query, $activeCompanyId);

        $paginator = $query
            ->with('department:id,name')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->when($status, function ($query) use ($status) {
                $query->where('is_active', $status === 'active');
            })
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('department_id', (int) $departmentId);
            })
            ->orderBy('name')
            ->paginate($perPage);

        $rows = $paginator->getCollection()
            ->map(function (Designation $designation) {
                return [
                    'id' => $designation->id,
                    'code' => $designation->code,
                    'name' => $designation->name,
                    'departmentId' => $designation->department_id,
                    'department' => optional($designation->department)->name ?? 'Unassigned',
                    'employeeCount' => 0,
                    'isActive' => (bool) $designation->is_active,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storeDesignation(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('designations', 'code')->where(function ($query) use ($activeCompanyId): void {
                    if ($activeCompanyId) {
                        $query->where('company_id', $activeCompanyId);
                    }
                }),
            ],
            'departmentId' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(function ($query) use ($activeCompanyId): void {
                    if ($activeCompanyId) {
                        $query->where(function ($inner) use ($activeCompanyId): void {
                            $inner->where('company_id', $activeCompanyId)->orWhereNull('company_id');
                        });
                    }
                }),
            ],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $designation = Designation::query()->create([
            'company_id' => $activeCompanyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $this->slugCode($validated['name']),
            'department_id' => $validated['departmentId'] ?? null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => $designation], 201);
    }

    public function exportDesignations(Request $request)
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'format' => ['nullable', Rule::in(['xlsx', 'csv', 'pdf'])],
        ]);

        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $departmentId = $validated['departmentId'] ?? null;
        $activeCompanyId = $this->activeCompanyId($request);

        $query = Designation::query();
        $this->applyTenantScope($query, $activeCompanyId);

        $rows = $query
            ->with('department:id,name')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->when($status, function ($query) use ($status) {
                $query->where('is_active', $status === 'active');
            })
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('department_id', (int) $departmentId);
            })
            ->orderBy('name')
            ->get()
            ->map(static fn (Designation $designation): array => [
                (string) $designation->name,
                (string) ($designation->department?->name ?: 'Unassigned'),
                (string) $designation->code,
                $designation->is_active ? 'Active' : 'Inactive',
            ])
            ->values()
            ->all();

        return $this->exportTabular('designations', $this->normalizeExportFormat($request), ['Name', 'Department', 'Code', 'Status'], $rows);
    }

    public function updateDesignation(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        $designationQuery = Designation::query();
        $this->applyIdentifierScope($designationQuery, $id);
        $this->applyTenantScope($designationQuery, $activeCompanyId);
        $designation = $designationQuery->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('designations', 'code')
                    ->ignore($designation->id)
                    ->where(function ($query) use ($activeCompanyId): void {
                        if ($activeCompanyId) {
                            $query->where('company_id', $activeCompanyId);
                        }
                    }),
            ],
            'departmentId' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(function ($query) use ($activeCompanyId): void {
                    if ($activeCompanyId) {
                        $query->where(function ($inner) use ($activeCompanyId): void {
                            $inner->where('company_id', $activeCompanyId)->orWhereNull('company_id');
                        });
                    }
                }),
            ],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $designation->update([
            'company_id' => $designation->company_id ?: $activeCompanyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $this->slugCode($validated['name']),
            'department_id' => $validated['departmentId'] ?? null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => $designation]);
    }

    public function policies(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to list policies.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:200'],
            'search' => ['nullable', 'string', 'max:100'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 20);
        $search = $validated['search'] ?? null;
        $departmentId = $validated['departmentId'] ?? null;

        $hasPolicyCompanyColumn = $this->tableHasColumn('policies', 'company_id');

        $policyQuery = Policy::query()
            ->with('department:id,name')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            })
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('department_id', (int) $departmentId);
            })
            ->orderByDesc('id');

        if ($hasPolicyCompanyColumn) {
            $policyQuery->where('company_id', $activeCompanyId);
        }

        $paginator = $policyQuery->paginate($perPage);

        $rows = $paginator->getCollection()
            ->map(function (Policy $policy) {
                return [
                    'id' => $policy->id,
                    'name' => $policy->name,
                    'departmentId' => $policy->department_id,
                    'department' => optional($policy->department)->name ?? 'All Department',
                    'description' => $policy->description,
                    'effectiveDate' => optional($policy->effective_date)->toDateString(),
                    'createdDate' => optional($policy->effective_date)->toDateString() ?? optional($policy->created_at)->toDateString(),
                    'attachmentUrl' => $this->policyAttachmentUrl($policy->attachment_path),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storePolicy(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to create policies.',
                ],
            ], 422);
        }

        $this->mergePolicyMultipartFields($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,uuid'],
            'effectiveDate' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'max:12288', 'mimetypes:application/pdf,image/jpeg,image/png,image/gif,image/webp'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'department_id' => $validated['departmentId'] ?? null,
            'effective_date' => $validated['effectiveDate'] ?? now()->toDateString(),
        ];

        if ($this->tableHasColumn('policies', 'company_id')) {
            $payload['company_id'] = $activeCompanyId;
        }

        $policy = Policy::query()->create($payload);

        if ($request->hasFile('attachment')) {
            try {
                $stored = $this->policyAttachmentStorage->store($request->file('attachment'), $policy->id);
                $policy->update(['attachment_path' => $stored->path]);
            } catch (InvalidMediaException $e) {
                $policy->delete();

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        $policy->refresh();

        return response()->json(['success' => true, 'data' => $policy], 201);
    }

    public function exportPolicies(Request $request)
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,uuid'],
            'format' => ['nullable', Rule::in(['xlsx', 'csv', 'pdf'])],
        ]);

        $search = $validated['search'] ?? null;
        $departmentId = $validated['departmentId'] ?? null;

        $policyQuery = Policy::query()->with('department:id,name');
        if ($this->tableHasColumn('policies', 'company_id')) {
            $activeCompanyId = $this->activeCompanyId($request);
            if (! $activeCompanyId) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TENANT_CONTEXT_REQUIRED',
                        'message' => 'Active company context is required to export policies.',
                    ],
                ], 422);
            }

            $policyQuery->where('company_id', $activeCompanyId);
        }

        $rows = $policyQuery
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            })
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('department_id', (int) $departmentId);
            })
            ->orderByDesc('id')
            ->get()
            ->map(static fn (Policy $policy): array => [
                (string) $policy->name,
                (string) ($policy->department?->name ?: 'All Department'),
                (string) $policy->description,
                (string) (optional($policy->effective_date)->toDateString() ?: optional($policy->created_at)->toDateString() ?: ''),
            ])
            ->values()
            ->all();

        return $this->exportTabular('policies', $this->normalizeExportFormat($request), ['Name', 'Department', 'Description', 'Effective Date'], $rows);
    }

    public function updatePolicy(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to update policies.',
                ],
            ], 422);
        }

        $policyQuery = Policy::query();
        if ($this->tableHasColumn('policies', 'company_id')) {
            $policyQuery->where('company_id', $activeCompanyId);
        }
        $this->applyIdentifierScope($policyQuery, $id);
        $policy = $policyQuery->firstOrFail();
        $this->mergePolicyMultipartFields($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,uuid'],
            'effectiveDate' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'max:12288', 'mimetypes:application/pdf,image/jpeg,image/png,image/gif,image/webp'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'department_id' => $validated['departmentId'] ?? null,
        ];

        if (array_key_exists('effectiveDate', $validated)) {
            $payload['effective_date'] = $validated['effectiveDate'] ?? $policy->effective_date;
        }

        if ($request->hasFile('attachment')) {
            try {
                $stored = $this->policyAttachmentStorage->replace(
                    $policy->attachment_path,
                    $request->file('attachment'),
                    $policy->id,
                );
                $payload['attachment_path'] = $stored->path;
            } catch (InvalidMediaException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        $policy->update($payload);
        $policy->refresh();

        return response()->json(['success' => true, 'data' => $policy]);
    }

    public function destroyDepartment(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $departmentQuery = Department::query();
        $this->applyIdentifierScope($departmentQuery, $id);
        $this->applyTenantScope($departmentQuery, $this->activeCompanyId($request));
        $department = $departmentQuery->firstOrFail();
        $department->delete();
        return response()->json(['success' => true]);
    }

    public function destroyDesignation(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $designationQuery = Designation::query();
        $this->applyIdentifierScope($designationQuery, $id);
        $this->applyTenantScope($designationQuery, $this->activeCompanyId($request));
        $designation = $designationQuery->firstOrFail();
        $designation->delete();
        return response()->json(['success' => true]);
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

    public function destroyPolicy(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to delete policies.',
                ],
            ], 422);
        }

        $policyQuery = Policy::query();
        if ($this->tableHasColumn('policies', 'company_id')) {
            $policyQuery->where('company_id', $activeCompanyId);
        }
        $this->applyIdentifierScope($policyQuery, $id);
        $policy = $policyQuery->firstOrFail();
        $this->mediaFileDeleter->delete($policy->attachment_path);
        $policy->delete();

        return response()->json(['success' => true]);
    }

    private function canManageEmployee(Request $request): bool
    {
        return $this->hasAnyPermission($request, ['employee.manage', 'employee.admin']);
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
            $phone = preg_replace('/\D+/', '', (string) $request->input('phone')) ?? '';
            $request->merge(['phone' => $phone !== '' ? $phone : null]);
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
        $existingContractType = $this->normalizeContractType(optional($user?->employeeProfile)->contract_type);
        $rawContractType = $this->nullableString($request->input('contractType'));
        $contractType = $rawContractType !== null
            ? $this->normalizeContractType($rawContractType)
            : $existingContractType;
        $nameRules = $isCreate ? ['required', 'string', 'min:2', 'max:150'] : ['sometimes', 'string', 'min:2', 'max:150'];
        $emailRules = [
            $isCreate ? 'required' : 'sometimes',
            'string',
            'email:rfc',
            'max:255',
            Rule::unique('users', 'email')->ignore($user?->id),
        ];
        $phoneRules = [($isCreate && ! $selfService) ? 'required' : 'nullable', 'regex:/^[0-9]{10,13}$/'];
        $nikRules = [($isCreate && ! $selfService) ? 'required' : 'nullable', 'regex:/^[0-9]{16}$/'];
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
                    && preg_match('/^[0-9]{10,13}$/', (string) ($contact['phone'] ?? '')) === 1;
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
            'salaryType' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', Rule::in($this->salaryTypeOptions())],
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
            'probationEndDate' => ['sometimes', 'nullable', 'date', 'after_or_equal:startDate'],
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
                $isCreate && ! $selfService ? 'required' : 'sometimes',
                'nullable',
                'integer',
                'required_with:provinceId,regencyId,districtId',
                Rule::exists('wilayah_villages', 'id')->where(fn ($query) => $query->where('district_id', $this->nullableInteger($request->input('districtId')))),
            ],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'addressDetail' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'placeOfBirth' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'string', 'max:150'],
            'dateOfBirth' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'date'],
            'gender' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'in:male,female,other'],
            'maritalStatus' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', Rule::in($this->maritalStatusOptions())],
            'religion' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', Rule::in($this->religionOptions())],
            'nationality' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'string', 'max:100', $nationalityRule],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'bankName' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', Rule::in($this->acceptedBankNames())],
            'bankAccountNo' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'string', 'max:100'],
            'bankAccountHolderName' => [$isCreate && ! $selfService ? 'required' : 'sometimes', 'nullable', 'string', 'max:150'],
            'bankIfscCode' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bankBranch' => ['sometimes', 'nullable', 'string', 'max:150'],
            'npwp' => ['sometimes', 'nullable', 'string', 'max:100'],
            'taxStatus' => ['sometimes', 'nullable', Rule::in($this->acceptedTaxStatusInputs())],
            'ptkpStatus' => ['sometimes', 'nullable', Rule::in($this->acceptedTaxStatusInputs())],
            'bpjsKesehatanNo' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bpjsKetenagakerjaanNo' => ['sometimes', 'nullable', 'string', 'max:100'],
            'emergencyContacts' => [($isCreate && ! $selfService) ? 'required' : 'sometimes', 'array', 'min:1', $emergencyContactRule],
            'emergencyContacts.*.name' => ['nullable', 'string', 'max:150'],
            'emergencyContacts.*.relationship' => ['nullable', 'string', 'max:100'],
            'emergencyContacts.*.phone' => ['nullable', 'regex:/^[0-9]{10,13}$/'],
            'educationItems' => ['sometimes', 'nullable', 'array'],
            'experienceItems' => ['sometimes', 'nullable', 'array'],
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
