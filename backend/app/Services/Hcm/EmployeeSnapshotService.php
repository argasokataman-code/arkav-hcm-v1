<?php

namespace App\Services\Hcm;

use App\Models\EmployeeAssignment;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeBenefit;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeContract;
use App\Models\EmployeeEmploymentHistory;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EmployeeSnapshotService
{
    public function syncNormalizedRecords(EmployeeProfile $profile, array $payload, array $organization = []): void
    {
        $now = now();
        $effectiveDate = (string) ($payload['startDate'] ?? $payload['hireDate'] ?? $payload['contractStartDate'] ?? optional($profile->hire_date)->toDateString() ?? $now->toDateString());
        $employmentStatus = $this->normalizeEmploymentStatus($payload['employmentStatus'] ?? $profile->getRawOriginal('employment_status'));

        if ($this->hasAnyKey($payload, ['employmentStatus', 'employeeType', 'startDate', 'hireDate', 'probationEndDate'])) {
            DB::table('employee_employment_history')->updateOrInsert(
                ['employee_id' => $profile->id, 'start_date' => $effectiveDate],
                [
                    'employment_status' => $employmentStatus,
                    'employee_type' => $payload['employeeType'] ?? null,
                    'end_date' => null,
                    'probation_end_date' => $employmentStatus === 'probation' ? ($payload['probationEndDate'] ?? null) : null,
                    'notes' => 'Synced from employee profile form',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        if ($organization !== [] || $this->hasAnyKey($payload, ['managerUserId', 'team', 'teamId', 'startDate', 'hireDate'])) {
            $departmentId = $organization['department_id'] ?? $profile->getRawOriginal('department_id');
            $teamName = $payload['team'] ?? $profile->getRawOriginal('team');
            $teamId = isset($payload['teamId']) && is_numeric($payload['teamId']) ? (int) $payload['teamId'] : null;

            if ($teamId !== null) {
                $teamRecord = DB::table('teams')->select('id', 'name', 'department_id')->where('id', $teamId)->first();
                if ($teamRecord) {
                    $teamName = $teamRecord->name;
                    $departmentId = $departmentId ?? $teamRecord->department_id;
                } else {
                    $teamId = null;
                }
            }

            DB::table('employee_assignments')->updateOrInsert(
                ['employee_id' => $profile->id, 'start_date' => $effectiveDate, 'is_primary' => true],
                [
                    'department_id' => $departmentId,
                    'designation_id' => $organization['designation_id'] ?? $profile->getRawOriginal('designation_id'),
                    'team_id' => $teamId ?: $this->resolveTeamId($teamName, $departmentId, $profile->company_id),
                    'manager_user_id' => $payload['managerUserId'] ?? $profile->getRawOriginal('manager_user_id'),
                    'team_name' => $teamName,
                    'end_date' => null,
                    'notes' => 'Synced from employee profile form',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        if ($this->hasAnyKey($payload, ['baseSalary', 'fixedAllowance', 'salaryType', 'startDate', 'hireDate'])) {
            DB::table('employee_compensations')->updateOrInsert(
                ['employee_id' => $profile->id, 'effective_date' => $effectiveDate],
                [
                    'salary_type' => $this->normalizeSalaryType($payload['salaryType'] ?? 'monthly'),
                    'base_salary' => round((float) ($payload['baseSalary'] ?? $profile->getRawOriginal('base_salary') ?? 0), 2),
                    'currency' => 'IDR',
                    'end_date' => null,
                    'notes' => 'Synced from employee profile form',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        if ($this->hasAnyKey($payload, ['contractType', 'contractStartDate', 'contractEndDate', 'contractStatus', 'hireDate'])) {
            $contractStartDate = (string) ($payload['contractStartDate'] ?? $payload['hireDate'] ?? $effectiveDate);
            $contractEndDate = $payload['contractEndDate'] ?? $profile->getRawOriginal('contract_end_date');
            $contractType = $this->normalizeContractType($payload['contractType'] ?? ($profile->getRawOriginal('contract_type') ?: 'permanent'));
            $contractStatus = $this->normalizeContractStatus($payload['contractStatus'] ?? null, $contractEndDate);
            $latestContract = $profile->contracts()->orderByDesc('start_date')->orderByDesc('id')->first();

            if ($latestContract !== null) {
                $latestStart = optional($latestContract->start_date)->toDateString();
                $latestEnd = optional($latestContract->end_date)->toDateString();
                $sameRecord = $latestStart === $contractStartDate
                    && $latestEnd === ($contractEndDate ?: null)
                    && $latestContract->contract_type === $contractType;

                if (! $sameRecord && $latestContract->status === 'active') {
                    $closingDate = $contractEndDate ?: Carbon::parse($contractStartDate)->subDay()->toDateString();
                    DB::table('employee_contracts')->where('id', $latestContract->id)->update([
                        'end_date' => $closingDate,
                        'status' => 'ended',
                        'updated_at' => $now,
                    ]);
                }
            }

            DB::table('employee_contracts')->updateOrInsert(
                ['employee_id' => $profile->id, 'start_date' => $contractStartDate],
                [
                    'contract_type' => $contractType,
                    'end_date' => $contractEndDate,
                    'status' => $contractStatus,
                    'notes' => 'Synced from employee profile form',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        if ($this->hasAnyKey($payload, ['bankName', 'bankAccountNo', 'bankAccountHolderName', 'bankIfscCode', 'bankBranch'])) {
            DB::table('employee_bank_accounts')
                ->where('employee_id', $profile->id)
                ->update(['is_primary' => false, 'updated_at' => $now]);

            DB::table('employee_bank_accounts')->updateOrInsert(
                ['employee_id' => $profile->id, 'account_number' => $payload['bankAccountNo'] ?? $profile->getRawOriginal('bank_account_no')],
                [
                    'bank_name' => $payload['bankName'] ?? $profile->getRawOriginal('bank_name'),
                    'account_holder_name' => $payload['bankAccountHolderName'] ?? $profile->user?->name,
                    'bank_ifsc_code' => $payload['bankIfscCode'] ?? $profile->getRawOriginal('bank_ifsc_code'),
                    'bank_branch' => $payload['bankBranch'] ?? $profile->getRawOriginal('bank_branch'),
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        if ($this->hasAnyKey($payload, ['npwp', 'taxStatus', 'ptkpStatus', 'startDate', 'hireDate', 'maritalStatus'])) {
            $normalizedTaxStatus = $this->normalizeTaxStatus($payload['taxStatus'] ?? ($payload['ptkpStatus'] ?? null))
                ?? $this->inferTaxStatusFromMaritalStatus($payload['maritalStatus'] ?? $profile->getRawOriginal('marital_status'));
            $existingTaxProfileId = DB::table('employee_tax_profiles')
                ->where('employee_id', $profile->id)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->value('id');
            $existingTaxProfile = $existingTaxProfileId !== null
                ? DB::table('employee_tax_profiles')->where('id', $existingTaxProfileId)->first()
                : null;

            $taxPayload = [
                'employee_id' => $profile->id,
                'npwp' => array_key_exists('npwp', $payload) ? $payload['npwp'] : ($existingTaxProfile->npwp ?? null),
                'tax_status' => $normalizedTaxStatus,
                'ptkp_status' => $normalizedTaxStatus,
                'effective_date' => $effectiveDate,
                'end_date' => null,
                'updated_at' => $now,
            ];

            if ($existingTaxProfileId !== null) {
                DB::table('employee_tax_profiles')
                    ->where('id', $existingTaxProfileId)
                    ->update($taxPayload);
            } else {
                DB::table('employee_tax_profiles')->insert($taxPayload + [
                    'created_at' => $now,
                ]);
            }
        }

        if ($this->hasAnyKey($payload, ['bpjsKesehatanNo', 'bpjsKetenagakerjaanNo', 'startDate', 'hireDate'])) {
            DB::table('employee_benefits')->updateOrInsert(
                ['employee_id' => $profile->id, 'effective_date' => $effectiveDate],
                [
                    'bpjs_kesehatan_no' => $payload['bpjsKesehatanNo'] ?? null,
                    'bpjs_ketenagakerjaan_no' => $payload['bpjsKetenagakerjaanNo'] ?? null,
                    'end_date' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        if (array_key_exists('emergencyContacts', $payload)) {
            DB::table('employee_emergency_contacts')->where('employee_id', $profile->id)->delete();
            foreach ((array) ($payload['emergencyContacts'] ?? []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                DB::table('employee_emergency_contacts')->insert([
                    'employee_id' => $profile->id,
                    'name' => (string) ($item['name'] ?? 'Contact '.($index + 1)),
                    'relationship' => $item['relationship'] ?? null,
                    'phone' => $item['phone'] ?? null,
                    'email' => $item['email'] ?? null,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (array_key_exists('educationItems', $payload)) {
            DB::table('employee_educations')->where('employee_id', $profile->id)->delete();
            foreach ((array) ($payload['educationItems'] ?? []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                DB::table('employee_educations')->insert([
                    'employee_id' => $profile->id,
                    'institution' => $item['institution'] ?? null,
                    'degree' => $item['degree'] ?? null,
                    'field_of_study' => $item['fieldOfStudy'] ?? ($item['field_of_study'] ?? null),
                    'start_year' => isset($item['startYear']) ? (int) $item['startYear'] : null,
                    'end_year' => isset($item['endYear']) ? (int) $item['endYear'] : null,
                    'notes' => $item['notes'] ?? null,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (array_key_exists('experienceItems', $payload)) {
            DB::table('employee_experiences')->where('employee_id', $profile->id)->delete();
            foreach ((array) ($payload['experienceItems'] ?? []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                DB::table('employee_experiences')->insert([
                    'employee_id' => $profile->id,
                    'company' => $item['company'] ?? null,
                    'position' => $item['position'] ?? null,
                    'start_date' => $item['startDate'] ?? ($item['start_date'] ?? null),
                    'end_date' => $item['endDate'] ?? ($item['end_date'] ?? null),
                    'description' => $item['description'] ?? null,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function snapshotForUser(User $user, ?CarbonInterface $asOf = null): array
    {
        return $this->snapshotForProfile($user->employeeProfile, $user, $asOf);
    }

    public function snapshotForProfile(?EmployeeProfile $profile, ?User $user = null, ?CarbonInterface $asOf = null): array
    {
        $user ??= $profile?->user;
        if ($profile === null) {
            return [
                'employmentStatus' => 'active',
                'employeeType' => null,
                'startDate' => optional($user?->created_at)->toDateString(),
                'team' => null,
                'departmentId' => null,
                'departmentName' => null,
                'designationId' => null,
                'designationName' => null,
                'designation' => null,
                'managerUserId' => null,
                'baseSalary' => 0.0,
                'fixedAllowance' => 0.0,
                'compensation' => ['salaryType' => 'monthly', 'currency' => 'IDR', 'baseSalary' => 0.0, 'fixedAllowance' => 0.0, 'effectiveDate' => null],
                'contract' => ['contractType' => 'permanent', 'startDate' => null, 'endDate' => null, 'status' => null],
                'bank' => ['name' => null, 'accountNo' => null, 'accountHolderName' => null, 'ifscCode' => null, 'branch' => null],
                'taxProfile' => ['npwp' => null, 'taxStatus' => null, 'ptkpStatus' => null],
                'benefits' => ['bpjsKesehatanNo' => null, 'bpjsKetenagakerjaanNo' => null],
                'personal' => [
                    'nik' => null,
                    'ktpNo' => null,
                    'placeOfBirth' => null,
                    'dateOfBirth' => null,
                    'gender' => null,
                    'maritalStatus' => null,
                    'religion' => null,
                    'nationality' => null,
                ],
                'emergencyContacts' => [],
                'educationItems' => [],
                'experienceItems' => [],
            ];
        }

        $employment = $this->latestEmployment($profile, $asOf);
        $assignment = $this->latestAssignment($profile, $asOf);
        $compensation = $this->latestCompensation($profile, $asOf);
        $contract = $this->latestContract($profile, $asOf);
        $bank = $this->primaryBankAccount($profile);
        $tax = $this->latestTaxProfile($profile, $asOf);
        $benefit = $this->latestBenefit($profile, $asOf);
        $managerName = $assignment?->manager?->name
            ?? $profile->assignedTeam?->teamLead?->name;

        $emergencyContacts = $profile->emergencyContacts()->orderBy('sort_order')->get()->map(fn ($item) => [
            'name' => $item->name,
            'relationship' => $item->relationship,
            'phone' => $item->phone,
            'email' => $item->email,
        ])->all();
        if ($emergencyContacts === []) {
            $emergencyContacts = $this->legacyArray($profile->getRawOriginal('emergency_contacts'));
        }

        $educationItems = $profile->educations()->orderBy('sort_order')->get()->map(fn ($item) => [
            'institution' => $item->institution,
            'degree' => $item->degree,
            'fieldOfStudy' => $item->field_of_study,
            'startYear' => $item->start_year,
            'endYear' => $item->end_year,
            'notes' => $item->notes,
        ])->all();
        if ($educationItems === []) {
            $educationItems = $this->legacyArray($profile->getRawOriginal('education_items'));
        }

        $experienceItems = $profile->experiences()->orderBy('sort_order')->get()->map(fn ($item) => [
            'company' => $item->company,
            'position' => $item->position,
            'startDate' => optional($item->start_date)->toDateString(),
            'endDate' => optional($item->end_date)->toDateString(),
            'description' => $item->description,
        ])->all();
        if ($experienceItems === []) {
            $experienceItems = $this->legacyArray($profile->getRawOriginal('experience_items'));
        }

        return [
            'employmentStatus' => $employment?->employment_status ?? ($profile->getRawOriginal('employment_status') ?: 'active'),
            'employeeType' => $employment?->employee_type,
            'startDate' => optional($employment?->start_date ?: $profile->hire_date)->toDateString(),
            'team' => $assignment?->team_name ?: ($profile->getRawOriginal('team') ?: $assignment?->department?->name),
            'departmentId' => $assignment?->department_id ?? $profile->getRawOriginal('department_id'),
            'departmentName' => $assignment?->department?->name ?? $profile->department?->name,
            'designationId' => $assignment?->designation_id ?? $profile->getRawOriginal('designation_id'),
            'designationName' => $assignment?->designation?->name ?? $profile->designationRef?->name,
            'designation' => $assignment?->designation?->name ?: ($profile->designationRef?->name ?: $profile->getRawOriginal('designation')),
            'managerUserId' => $assignment?->manager_user_id ?? $profile->getRawOriginal('manager_user_id'),
            'managerName' => $managerName,
            'baseSalary' => (float) ($compensation?->base_salary ?? $profile->getRawOriginal('base_salary') ?? 0),
            'fixedAllowance' => 0.0,
            'compensation' => [
                'salaryType' => $compensation?->salary_type ?? 'monthly',
                'currency' => $compensation?->currency ?? 'IDR',
                'baseSalary' => (float) ($compensation?->base_salary ?? $profile->getRawOriginal('base_salary') ?? 0),
                'fixedAllowance' => 0.0,
                'effectiveDate' => optional($compensation?->effective_date)->toDateString(),
            ],
            'contract' => [
                'contractType' => $contract?->contract_type ?? $this->normalizeContractType($profile->getRawOriginal('contract_type') ?: 'permanent'),
                'startDate' => optional($contract?->start_date ?: $profile->contract_start_date)->toDateString(),
                'endDate' => optional($contract?->end_date ?: $profile->contract_end_date)->toDateString(),
                'status' => $contract?->status,
            ],
            'bank' => [
                'name' => $bank?->bank_name ?? $profile->bank_name,
                'accountNo' => $bank?->account_number ?? $profile->bank_account_no,
                'accountHolderName' => $bank?->account_holder_name,
                'ifscCode' => $bank?->bank_ifsc_code ?? $profile->bank_ifsc_code,
                'branch' => $bank?->bank_branch ?? $profile->bank_branch,
            ],
            'taxProfile' => [
                'npwp' => $tax?->npwp,
                'taxStatus' => $tax?->tax_status ?? $this->inferTaxStatusFromMaritalStatus($profile->getRawOriginal('marital_status')),
                'ptkpStatus' => $tax?->ptkp_status ?? $tax?->tax_status ?? $this->inferTaxStatusFromMaritalStatus($profile->getRawOriginal('marital_status')),
            ],
            'benefits' => [
                'bpjsKesehatanNo' => $benefit?->bpjs_kesehatan_no,
                'bpjsKetenagakerjaanNo' => $benefit?->bpjs_ketenagakerjaan_no,
            ],
            'personal' => [
                'nik' => $profile->nik,
                'ktpNo' => $profile->nik,
                'placeOfBirth' => $profile->getRawOriginal('place_of_birth'),
                'dateOfBirth' => optional($profile->date_of_birth)->toDateString(),
                'gender' => $profile->getRawOriginal('gender'),
                'maritalStatus' => $profile->getRawOriginal('marital_status'),
                'religion' => $profile->getRawOriginal('religion'),
                'nationality' => $profile->getRawOriginal('nationality'),
            ],
            'emergencyContacts' => $emergencyContacts,
            'educationItems' => $educationItems,
            'experienceItems' => $experienceItems,
            'employmentHistory' => $this->mapEmploymentHistory($profile),
            'assignmentHistory' => $this->mapAssignmentHistory($profile),
            'compensationHistory' => $this->mapCompensationHistory($profile),
            'contractHistory' => $this->mapContractHistory($profile),
            'bankAccounts' => $this->mapBankAccounts($profile),
            'documents' => [],
        ];
    }

    public function latestEmployment(EmployeeProfile $profile, ?CarbonInterface $asOf = null): ?EmployeeEmploymentHistory
    {
        return $this->latestEffectiveRecord(
            $profile->employmentHistories(),
            'start_date',
            $this->asOf($asOf),
        );
    }

    public function latestAssignment(EmployeeProfile $profile, ?CarbonInterface $asOf = null): ?EmployeeAssignment
    {
        return $this->latestEffectiveRecord(
            $profile->assignments()->with(['department:id,name', 'designation:id,name,department_id', 'team:id,name', 'manager:id,name,email']),
            'start_date',
            $this->asOf($asOf),
        );
    }

    public function latestCompensation(EmployeeProfile $profile, ?CarbonInterface $asOf = null): ?EmployeeCompensation
    {
        return $this->latestEffectiveRecord(
            $profile->compensations(),
            'effective_date',
            $this->asOf($asOf),
        );
    }

    public function latestContract(EmployeeProfile $profile, ?CarbonInterface $asOf = null): ?EmployeeContract
    {
        return $this->latestEffectiveRecord(
            $profile->contracts(),
            'start_date',
            $this->asOf($asOf),
        );
    }

    public function primaryBankAccount(EmployeeProfile $profile): ?EmployeeBankAccount
    {
        return $profile->bankAccounts()->orderByDesc('is_primary')->orderByDesc('id')->first();
    }

    public function latestTaxProfile(EmployeeProfile $profile, ?CarbonInterface $asOf = null): ?EmployeeTaxProfile
    {
        return $this->latestEffectiveRecord(
            $profile->taxProfiles(),
            'effective_date',
            $this->asOf($asOf),
        );
    }

    public function latestBenefit(EmployeeProfile $profile, ?CarbonInterface $asOf = null): ?EmployeeBenefit
    {
        return $this->latestEffectiveRecord(
            $profile->benefits(),
            'effective_date',
            $this->asOf($asOf),
        );
    }

    private function latestEffectiveRecord($relation, string $dateColumn, Carbon $asOf): mixed
    {
        return $relation
            ->whereDate($dateColumn, '<=', $asOf->toDateString())
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $asOf->toDateString());
            })
            ->orderByDesc($dateColumn)
            ->orderByDesc('id')
            ->first();
    }

    private function mapEmploymentHistory(EmployeeProfile $profile): array
    {
        return $profile->employmentHistories()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmployeeEmploymentHistory $item) => [
                'employmentStatus' => $item->employment_status,
                'employeeType' => $item->employee_type,
                'startDate' => optional($item->start_date)->toDateString(),
                'endDate' => optional($item->end_date)->toDateString(),
                'probationEndDate' => optional($item->probation_end_date)->toDateString(),
                'notes' => $item->notes,
            ])
            ->all();
    }

    private function mapAssignmentHistory(EmployeeProfile $profile): array
    {
        return $profile->assignments()
            ->with(['department:id,name', 'designation:id,name,department_id', 'team:id,name', 'manager:id,name,email'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmployeeAssignment $item) => [
                'departmentId' => $item->department_id,
                'departmentName' => $item->department?->name,
                'designationId' => $item->designation_id,
                'designationName' => $item->designation?->name,
                'teamId' => $item->team_id,
                'teamName' => $item->team?->name ?? $item->team_name,
                'managerUserId' => $item->manager_user_id,
                'managerName' => $item->manager?->name,
                'startDate' => optional($item->start_date)->toDateString(),
                'endDate' => optional($item->end_date)->toDateString(),
                'isPrimary' => (bool) $item->is_primary,
                'notes' => $item->notes,
            ])
            ->all();
    }

    private function mapCompensationHistory(EmployeeProfile $profile): array
    {
        return $profile->compensations()
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmployeeCompensation $item) => [
                'salaryType' => $item->salary_type,
                'baseSalary' => (float) $item->base_salary,
                'fixedAllowance' => 0.0,
                'currency' => $item->currency,
                'effectiveDate' => optional($item->effective_date)->toDateString(),
                'endDate' => optional($item->end_date)->toDateString(),
                'notes' => $item->notes,
            ])
            ->all();
    }

    private function mapContractHistory(EmployeeProfile $profile): array
    {
        return $profile->contracts()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmployeeContract $item) => [
                'contractType' => $item->contract_type,
                'startDate' => optional($item->start_date)->toDateString(),
                'endDate' => optional($item->end_date)->toDateString(),
                'status' => $item->status,
                'notes' => $item->notes,
            ])
            ->all();
    }

    private function mapBankAccounts(EmployeeProfile $profile): array
    {
        return $profile->bankAccounts()
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EmployeeBankAccount $item) => [
                'bankName' => $item->bank_name,
                'accountNumber' => $item->account_number,
                'accountHolderName' => $item->account_holder_name,
                'bankIfscCode' => $item->bank_ifsc_code,
                'bankBranch' => $item->bank_branch,
                'isPrimary' => (bool) $item->is_primary,
            ])
            ->all();
    }

    private function normalizeEmploymentStatus(?string $value): string
    {
        $raw = strtolower(trim((string) $value));
        $allowed = (array) config('hcm.employment_statuses', ['probation', 'active', 'resigned', 'terminated', 'inactive']);

        if ($raw === '' || ! in_array($raw, $allowed, true)) {
            return 'active';
        }

        return $raw;
    }

    private function normalizeSalaryType(?string $value): string
    {
        $raw = strtolower(trim((string) $value));

        return in_array($raw, (array) config('hcm.salary_types', ['monthly', 'daily', 'hourly']), true) ? $raw : 'monthly';
    }

    private function normalizeContractType(?string $value): string
    {
        $raw = strtolower(trim((string) $value));
        if ($raw === '' || $raw === 'pkwtt') {
            $raw = 'permanent';
        }

        if ($raw === 'pkwt') {
            $raw = 'contract';
        }

        return in_array($raw, (array) config('hcm.contract_types', ['contract', 'permanent']), true) ? $raw : 'permanent';
    }

    private function normalizeContractStatus(?string $status, mixed $endDate): string
    {
        $raw = strtolower(trim((string) $status));
        $allowed = (array) config('hcm.contract_statuses', ['active', 'ended', 'terminated']);
        if (in_array($raw, $allowed, true)) {
            return $raw;
        }

        if ($endDate) {
            try {
                return Carbon::parse((string) $endDate)->lt(now()->startOfDay()) ? 'ended' : 'active';
            } catch (\Throwable) {
                return 'active';
            }
        }

        return 'active';
    }

    private function normalizeTaxStatus(?string $value): ?string
    {
        $raw = strtoupper(str_replace(['/', ' '], '', trim((string) $value)));
        $allowed = (array) config('hcm.tax_statuses', ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3']);
        if ($raw === '') {
            return null;
        }
        if ($raw === 'TK') {
            $raw = 'TK0';
        }
        if ($raw === 'K') {
            $raw = 'K0';
        }

        return in_array($raw, $allowed, true) ? $raw : null;
    }

    private function inferTaxStatusFromMaritalStatus(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'married' => 'K0',
            'single', 'divorced', 'widowed' => 'TK0',
            default => null,
        };
    }

    private function resolveTeamId(mixed $teamName, mixed $departmentId, mixed $companyId): ?int
    {
        $name = trim((string) ($teamName ?? ''));
        $deptId = is_numeric($departmentId) ? (int) $departmentId : null;
        $compId = is_numeric($companyId) ? (int) $companyId : null;
        if ($name === '' || $compId === null) {
            return null;
        }

        $existing = DB::table('teams')
            ->where('company_id', $compId)
            ->where('name', $name)
            ->where('is_active', true)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('teams')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'company_id' => $compId,
            'department_id' => $deptId,
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function hasAnyKey(array $payload, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        return false;
    }

    private function asOf(?CarbonInterface $value = null): Carbon
    {
        return $value ? Carbon::parse($value) : now();
    }

    private function legacyArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
