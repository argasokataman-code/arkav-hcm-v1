<?php

namespace App\Http\Controllers\Api\Employee\Concerns;

use App\Events\EmployeeProfileUpdated;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeProfile;
use App\Models\HcmRole;
use App\Models\HcmScheduleTiming;
use App\Models\HcmUserRole;
use App\Models\User;
use App\Services\EmployeeCountValidator;
use Database\Seeders\HcmUserManagementSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

trait HandlesEmployeeCoreEndpoints
{
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
            'taxFilter' => ['nullable', Rule::in(['missing_npwp', 'missing_ptkp', 'incomplete', 'complete'])],
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

        if ($departmentId && ! Department::query()->whereKey((int) $departmentId)->where('company_id', $activeCompanyId)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEPARTMENT_NOT_FOUND',
                    'message' => 'Department not found in active company context.',
                ],
            ], 422);
        }

        if ($designationId) {
            $designationInCompany = Designation::query()
                ->whereKey((int) $designationId)
                ->whereHas('department', fn ($query) => $query->where('company_id', $activeCompanyId))
                ->exists();
            if (! $designationInCompany) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DESIGNATION_NOT_FOUND',
                        'message' => 'Designation not found in active company context.',
                    ],
                ], 422);
            }
        }

        if ($teamId && ! DB::table('teams')->where('id', (int) $teamId)->where('company_id', $activeCompanyId)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEAM_NOT_FOUND',
                    'message' => 'Team not found in active company context.',
                ],
            ], 422);
        }

        $taxFilter = $validated['taxFilter'] ?? null;

        $scopeCompanyId = $activeCompanyId;

        $query = User::query()
            ->with([
                'employeeProfile' => function ($query) {
                    $query->select(
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
            ->when($scopeCompanyId, function ($query) use ($scopeCompanyId): void {
                $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('company_id', $scopeCompanyId));
            })
            ->when(! $scopeCompanyId, function ($query): void {
                $query->whereHas('employeeProfile');
            })
            ->whereDoesntHave('companyMemberships', function ($query) use ($scopeCompanyId): void {
                $query->where('status', 'active')->where('role', 'owner');
                if ($scopeCompanyId !== null) {
                    $query->where('company_id', $scopeCompanyId);
                }
            })
            ->select(['id', 'uuid', 'name', 'email', 'created_at']);

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
                    $profileQuery->where(function ($query) use ($term): void {
                        $query->where('phone', 'like', '%'.$term.'%')
                            ->orWhere('nik', 'like', '%'.$term.'%');
                    });
                });
            });
        }

        $statusScope = function ($profileQuery) use ($scopeCompanyId) {
            if ($scopeCompanyId) {
                $profileQuery->where('company_id', $scopeCompanyId);
            }

            return $profileQuery;
        };

        if ($statusFilter === 'active') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $statusScope($profileQuery)->where('employment_status', 'active'));
        } elseif ($statusFilter === 'inactive') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $statusScope($profileQuery)->where('employment_status', 'inactive'));
        } elseif ($statusFilter === 'probation') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $statusScope($profileQuery)->where('employment_status', 'probation'));
        } elseif ($statusFilter === 'resigned') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $statusScope($profileQuery)->where('employment_status', 'resigned'));
        } elseif ($statusFilter === 'terminated') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $statusScope($profileQuery)->where('employment_status', 'terminated'));
        }

        if ($departmentId) {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $statusScope($profileQuery)->where('department_id', (int) $departmentId));
        }

        if ($designationId) {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $statusScope($profileQuery)->where('designation_id', (int) $designationId));
        }

        if ($teamId) {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $statusScope($profileQuery)->where('team_id', (int) $teamId));
        }

        if ($taxFilter) {
            $query->whereHas('employeeProfile', function ($profileQuery) use ($scopeCompanyId, $taxFilter): void {
                if ($scopeCompanyId) {
                    $profileQuery->where('company_id', $scopeCompanyId);
                }

                $profileQuery->where(function ($employeeProfileQuery) use ($taxFilter): void {
                    $employeeProfileQuery->whereHas('taxProfile', function ($taxProfileQuery) use ($taxFilter): void {
                        if ($taxFilter === 'missing_npwp') {
                            $taxProfileQuery->where(function ($query): void {
                                $query->whereNull('npwp')->orWhere('npwp', '');
                            });

                            return;
                        }

                        if ($taxFilter === 'missing_ptkp') {
                            $taxProfileQuery->where(function ($query): void {
                                $query->whereNull('tax_status')->orWhere('tax_status', '');
                            });

                            return;
                        }

                        if ($taxFilter === 'incomplete') {
                            $taxProfileQuery->where(function ($query): void {
                                $query->whereNull('npwp')
                                    ->orWhere('npwp', '')
                                    ->orWhereNull('tax_status')
                                    ->orWhere('tax_status', '');
                            });

                            return;
                        }

                        if ($taxFilter === 'complete') {
                            $taxProfileQuery->whereNotNull('npwp')
                                ->where('npwp', '!=', '')
                                ->whereNotNull('tax_status')
                                ->where('tax_status', '!=', '');
                        }
                    });

                    if (in_array($taxFilter, ['missing_npwp', 'missing_ptkp', 'incomplete'], true)) {
                        $employeeProfileQuery->orWhereDoesntHave('taxProfile');
                    }
                });
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        $rows = $paginator->getCollection()->map(function (User $user) {
            $profile = $user->employeeProfile;
            $snapshot = $this->employeeSnapshotService->snapshotForUser($user);
            $employmentStatus = $snapshot['employmentStatus'] ?? 'active';
            $designationLabel = $snapshot['designation'] ?: 'Employee';
            $pkwtSummary = $this->pkwtCompensationService->summarizeProfile($profile);
            $taxProfile = is_array($snapshot['taxProfile'] ?? null) ? $snapshot['taxProfile'] : [];
            $personalProfile = is_array($snapshot['personal'] ?? null) ? $snapshot['personal'] : [];
            $teamName = $profile?->assignedTeam?->name ?: ($snapshot['team'] ?: '—');
            $teamLeaderName = $snapshot['managerName']
                ?? $profile?->assignedTeam?->teamLead?->name
                ?? null;

            $ptkpStatusRaw = $taxProfile['ptkpStatus'] ?? $taxProfile['taxStatus'] ?? null;
            $ptkpStatus = $this->normalizeTaxStatusInput($ptkpStatusRaw);

            return [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'employeeProfileId' => $profile?->id,
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
                'fixedAllowance' => 0.0,
                'employmentStatus' => $employmentStatus,
                'hireDate' => optional($profile?->hire_date)->toDateString(),
                'joinDate' => $this->effectiveJoinDate($user, $profile),
                'contractType' => $pkwtSummary['contractType'],
                'contractStartDate' => $pkwtSummary['contractStartDate'],
                'contractEndDate' => $pkwtSummary['contractEndDate'],
                'pkwtDueThisMonth' => (bool) $pkwtSummary['isDueThisMonth'],
                'estimatedPkwtCompensationThisMonth' => (float) $pkwtSummary['estimatedCompensationThisMonth'],
                'maritalStatus' => $personalProfile['maritalStatus'] ?? $profile?->getRawOriginal('marital_status'),
                'marital_status' => $personalProfile['maritalStatus'] ?? $profile?->getRawOriginal('marital_status'),
                'npwp' => $taxProfile['npwp'] ?? null,
                'taxStatus' => $taxProfile['taxStatus'] ?? null,
                'tax_status' => $taxProfile['taxStatus'] ?? null,
                'ptkpStatus' => $ptkpStatus,
                'ptkp_status' => $ptkpStatus,
                'ptkpAnnualNominal' => $this->resolvePtkpAnnualNominal($ptkpStatus),
                'ptkp_annual_nominal' => $this->resolvePtkpAnnualNominal($ptkpStatus),
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
        $validated = $request->validate($this->employeeWriteRules($request, true) + [
            'data_disclosure_acknowledged' => ['required', 'accepted'],
        ]);

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
        $actorUuid = (string) ($request->user()?->uuid ?? '');
        $disclosureIp = $request->ip();

        DB::transaction(function () use (&$user, &$profile, $validated, $org, $activeCompanyId, $actorId, $actorUuid, $disclosureIp, $isGlobalAdmin, $company): void {
            $lockedCompany = Company::query()
                ->whereKey($activeCompanyId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $isGlobalAdmin && ! ((defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) && str_starts_with((string) $company->code, 'TST'))) {
                app(EmployeeCountValidator::class)->validateCanAddEmployees($lockedCompany, 1);
            }

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
                'fixed_allowance' => 0,
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
                'data_disclosed_at' => now(),
                'data_disclosed_by_uuid' => $actorUuid,
                'data_disclosed_ip' => $disclosureIp,
            ]);

            $this->employeeSnapshotService->syncNormalizedRecords($profile, $validated, $org);
        });

        $this->logHcmActivity($request, 'employee', (string) ($user->uuid ?? ''), 'created');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'employeeProfileId' => $profile?->id,
                'fullName' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function exportEmployees(Request $request)
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
                    'message' => 'Active company context is required to export employees.',
                ],
            ], 422);
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

        if ($departmentId && ! Department::query()->whereKey((int) $departmentId)->where('company_id', $activeCompanyId)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEPARTMENT_NOT_FOUND',
                    'message' => 'Department not found in active company context.',
                ],
            ], 422);
        }

        if ($designationId) {
            $designationInCompany = Designation::query()
                ->whereKey((int) $designationId)
                ->whereHas('department', fn ($query) => $query->where('company_id', $activeCompanyId))
                ->exists();
            if (! $designationInCompany) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DESIGNATION_NOT_FOUND',
                        'message' => 'Designation not found in active company context.',
                    ],
                ], 422);
            }
        }

        if ($teamId && ! DB::table('teams')->where('id', (int) $teamId)->where('company_id', $activeCompanyId)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TEAM_NOT_FOUND',
                    'message' => 'Team not found in active company context.',
                ],
            ], 422);
        }

        $query = User::query()
            ->with([
                'employeeProfile' => function ($query) {
                    $query->select(
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
            ->select(['id', 'uuid', 'name', 'email', 'created_at'])
            ->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('company_id', $activeCompanyId));

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
            $query->where(function ($query) {
                $query->whereDoesntHave('employeeProfile')
                    ->orWhereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employment_status', 'active'));
            });
        } elseif ($statusFilter === 'inactive') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employment_status', 'inactive'));
        } elseif ($statusFilter === 'probation') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employment_status', 'probation'));
        } elseif ($statusFilter === 'resigned') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employment_status', 'resigned'));
        } elseif ($statusFilter === 'terminated') {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employment_status', 'terminated'));
        }

        if ($departmentId) {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('department_id', (int) $departmentId));
        }

        if ($designationId) {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('designation_id', (int) $designationId));
        }

        if ($teamId) {
            $query->whereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('team_id', (int) $teamId));
        }

        $users = $query->orderByDesc('id')->get();

        $headers = ['Employee UUID', 'Name', 'Email', 'Team', 'Phone', 'Department', 'Designation', 'Status', 'Join Date'];
        $rows = $users->map(function (User $user): array {
            $profile = $user->employeeProfile;
            $snapshot = $this->employeeSnapshotService->snapshotForUser($user);
            $teamName = $profile?->assignedTeam?->name ?: ($snapshot['team'] ?: '');

            return [
                (string) $user->uuid,
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

        $this->logExportAuditTrail($request, 'export_employees', $this->normalizeExportFormat($request), count($rows));

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

        $activeCompanyId = $this->activeCompanyId($request);
        if ($activeCompanyId) {
            $isOwner = CompanyUser::query()
                ->where('company_id', $activeCompanyId)
                ->where('user_id', $user->id)
                ->where('role', 'owner')
                ->exists();
            if ($isOwner) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'EMPLOYEE_NOT_FOUND',
                        'message' => 'Employee not found.',
                    ],
                ], 404);
            }
        }

        $auth = $request->user();
        if ($this->canManageEmployee($request)) {
            if ((int) $auth->id !== (int) $user->id && ! $this->canManageEmployeeTarget($request, $user)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'EMPLOYEE_NOT_FOUND',
                        'message' => 'Employee not found.',
                    ],
                ], 404);
            }

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

    public function show(Request $request, int $id): JsonResponse
    {
        $auth = $request->user();
        $canManage = $this->canManageEmployee($request);
        if (! $canManage && $auth->id !== $id) {
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

        $activeCompanyId = $this->activeCompanyId($request);
        if ($activeCompanyId) {
            $isOwner = CompanyUser::query()
                ->where('company_id', $activeCompanyId)
                ->where('user_id', $user->id)
                ->where('role', 'owner')
                ->exists();
            if ($isOwner) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'EMPLOYEE_NOT_FOUND',
                        'message' => 'Employee not found.',
                    ],
                ], 404);
            }
        }

        if ($canManage && (int) $auth->id !== (int) $user->id && ! $this->canManageEmployeeTarget($request, $user)) {
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
                'fixedAllowance' => 0.0,
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

    private function resolveEmployeeRoleIdForCompany(int $companyId): ?int
    {
        $roleId = HcmRole::query()
            ->where('company_id', $companyId)
            ->where('code', 'EMPLOYEE')
            ->value('id');

        if (is_numeric($roleId)) {
            return (int) $roleId;
        }

        app(HcmUserManagementSeeder::class)->run();

        $roleId = HcmRole::query()
            ->where('company_id', $companyId)
            ->where('code', 'EMPLOYEE')
            ->value('id');

        return is_numeric($roleId) ? (int) $roleId : null;
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
            $changedFields = array_keys($profile->getDirty());
            $profile->save();
            if ($changedFields !== []) {
                EmployeeProfileUpdated::dispatch(
                    $profile,
                    $changedFields,
                    (string) ($request->user()?->uuid ?? ''),
                );
            }
        }

        $this->employeeSnapshotService->syncNormalizedRecords($profile, $validated, $touchesOrg ? $org : []);

        $this->logHcmActivity($request, 'employee', (string) ($user->uuid ?? ''), 'updated', $changedFields ?? []);

        return $this->show($request, $user->id);
    }

    private function updateEmployeeSelf(Request $request, User $user): JsonResponse
    {
        $selfKeys = ['nik', 'ktpNo', 'phone', 'address', 'addressDetail', 'placeOfBirth', 'dateOfBirth', 'gender', 'maritalStatus', 'religion', 'nationality', 'bio', 'emergencyContacts', 'educationItems', 'experienceItems'];
        $present = collect(array_keys($request->all()))
            ->filter(fn (string $key) => ! in_array($key, ['_token'], true))
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
}
