<?php

namespace App\Http\Controllers\Api\Employee\Concerns;

use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeProfile;
use App\Modelsser;
use App\Services\EmployeeCountValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\HttpploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait HandlesEmployeeBulkOperations
{
    public function bulkTemplate(Request $request)
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
                    'message' => 'Active company context is required to download bulk template.',
                ],
            ], 422);
        }

        if ($prerequisiteError = $this->ensureBulkEmployeeOrganizationMastersReady($activeCompanyId, 'download employee bulk template')) {
            return $prerequisiteError;
        }

        $headers = [
            'employee_uuid', 'name', 'email', 'password', 'confirm_password',
            'team', 'department', 'designation', 'employment_status', 'employee_type', 'start_date', 'probation_end_date',
            'base_salary',
            'contract_type', 'contract_status', 'contract_start_date', 'contract_end_date', 'manager_user_id',
            'nik', 'phone', 'address', 'place_of_birth', 'date_of_birth', 'gender', 'marital_status', 'religion', 'nationality', 'bio',
            'bank_name', 'bank_account_no', 'bank_account_holder_name', 'bank_ifsc_code', 'bank_branch',
            'npwp', 'tax_status', 'ptkp_status', 'bpjs_kesehatan_no', 'bpjs_ketenagakerjaan_no',
        ];

        $rows = [
            [
                '', 'Budi Santoso', 'budi@company.com', 'StrongPass1!', 'StrongPass1!',
                'HR Shared Services', 'People Operations', 'HR Officer', 'active', 'permanent', '2024-01-15', '',
                5000000,
                'permanent', 'active', '2024-01-15', '', '',
                '3175010101900001', '08123456789', 'Jakarta', 'Jakarta', '1990-01-01', 'male', 'married', 'Islam', 'Indonesia', 'HR Admin',
                'BCA', '1234567890', 'Budi Santoso', 'BCA001', 'Jakarta Pusat',
                '', 'TK0', 'TK0', 'BPKES001', 'BPTK001',
            ],
            [
                '', 'Siti Aminah', 'siti@company.com', 'StrongPass1!', 'StrongPass1!',
                'Finance Operations', 'Finance', 'Finance Staff', 'probation', 'contract', '2025-02-01', '2025-05-01',
                6200000,
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

        // Use only tenant-specific masters for template (no global fallback).
        $departmentsCollection = Department::query()
            ->where('company_id', $activeCompanyId)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $departments = $departmentsCollection->map(fn (Department $department) => [$department->id, $department->name, $department->code])->values()->all();
        $departmentIds = array_column($departments, 0);

        // Load designations for the active company (direct company_id filter, consistent with departments query)
        $designations = Designation::query()->with('department:id,name')
            ->where('company_id', $activeCompanyId)
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
            ->where('teams.company_id', $activeCompanyId)
            ->orderBy('departments.name')
            ->orderBy('teams.name')
            ->get()
            ->map(fn ($team) => [$team->id, $team->department_id, $team->department_name, $team->name])
            ->values()
            ->all();

        $banks = array_map(fn (string $bank) => [$bank], $this->allowedBankNames());
        $employmentStatuses = $this->employmentStatusOptions();
        $contractTypes = $this->acceptedContractTypeInputs();
        $contractStatuses = $this->contractStatusOptions();
        $genders = ['male', 'female', 'other'];
        $maritalStatuses = $this->maritalStatusOptions();
        $religions = $this->religionOptions();
        $taxStatuses = $this->acceptedTaxStatusInputs();
        $maxEnumRows = max(count($employmentStatuses), count($contractTypes), count($contractStatuses), count($genders), count($maritalStatuses), count($religions), count($taxStatuses));
        $enumRows = [];
        for ($index = 0; $index < $maxEnumRows; $index++) {
            $enumRows[] = [
                $employmentStatuses[$index] ?? null,
                $contractTypes[$index] ?? null,
                $contractStatuses[$index] ?? null,
                $genders[$index] ?? null,
                $maritalStatuses[$index] ?? null,
                $religions[$index] ?? null,
                $taxStatuses[$index] ?? null,
            ];
        }

        $this->hydrateBulkReferenceSheet($spreadsheet->createSheet(), 'ref_departments', ['id', 'name', 'code'], $departments);
        $this->hydrateBulkReferenceSheet($spreadsheet->createSheet(), 'ref_designations', ['id', 'department_id', 'department_name', 'name', 'code'], $designations);
        $this->hydrateBulkReferenceSheet($spreadsheet->createSheet(), 'ref_teams', ['id', 'department_id', 'department_name', 'name'], $teams);
        $this->hydrateBulkReferenceSheet($spreadsheet->createSheet(), 'ref_banks', ['bank_name'], $banks);
        $this->hydrateBulkReferenceSheet($spreadsheet->createSheet(), 'ref_enums', ['employment_status', 'contract_type', 'contract_status', 'gender', 'marital_status', 'religion', 'tax_status'], $enumRows);

        $validationEndRow = 250;
        // Columns F=team, G=department, H=designation (name-only, no _id columns in template)
        $this->applyDropdownValidation($sheet, 'F2:F'.$validationEndRow, '=ref_teams!$D$2:$D$'.max(count($teams) + 1, 2), 'Team');
        $this->applyDropdownValidation($sheet, 'G2:G'.$validationEndRow, '=ref_departments!$B$2:$B$'.max(count($departments) + 1, 2), 'Department');
        $this->applyDropdownValidation($sheet, 'H2:H'.$validationEndRow, '=ref_designations!$D$2:$D$'.max(count($designations) + 1, 2), 'Designation');
        $this->applyDropdownValidation($sheet, 'I2:I'.$validationEndRow, '=ref_enums!$A$2:$A$'.max(count($employmentStatuses) + 1, 2), 'Employment Status');
        $this->applyDropdownValidation($sheet, 'N2:N'.$validationEndRow, '=ref_enums!$B$2:$B$'.max(count($contractTypes) + 1, 2), 'Contract Type');
        $this->applyDropdownValidation($sheet, 'O2:O'.$validationEndRow, '=ref_enums!$C$2:$C$'.max(count($contractStatuses) + 1, 2), 'Contract Status');
        $this->applyDropdownValidation($sheet, 'X2:X'.$validationEndRow, '=ref_enums!$D$2:$D$'.max(count($genders) + 1, 2), 'Gender');
        $this->applyDropdownValidation($sheet, 'Y2:Y'.$validationEndRow, '=ref_enums!$E$2:$E$'.max(count($maritalStatuses) + 1, 2), 'Marital Status');
        $this->applyDropdownValidation($sheet, 'Z2:Z'.$validationEndRow, '=ref_enums!$F$2:$F$'.max(count($religions) + 1, 2), 'Religion');
        $this->applyDropdownValidation($sheet, 'AC2:AC'.$validationEndRow, '=ref_banks!$A$2:$A$'.max(count($banks) + 1, 2), 'Bank Name');
        $this->applyDropdownValidation($sheet, 'AI2:AI'.$validationEndRow, '=ref_enums!$G$2:$G$'.max(count($taxStatuses) + 1, 2), 'Tax Status');
        $this->applyDropdownValidation($sheet, 'AJ2:AJ'.$validationEndRow, '=ref_enums!$G$2:$G$'.max(count($taxStatuses) + 1, 2), 'PTKP Status');

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

        if ($prerequisiteError = $this->ensureBulkEmployeeOrganizationMastersReady($activeCompanyId, 'bulk upload employee data')) {
            return $prerequisiteError;
        }

        /** @var Company $company */
        $company = Company::query()->findOrFail($activeCompanyId);
        $employeeValidator = app(EmployeeCountValidator::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $rows = $this->parseBulkRows($validated['file']);
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
            DB::transaction(function () use ($rows, &$created, &$updated, &$errors, $activeCompanyId, $company, $employeeValidator): void {
                $lockedCompany = Company::query()
                    ->whereKey($activeCompanyId)
                    ->lockForUpdate()
                    ->firstOrFail();

                foreach ($rows as $index => $row) {
                    $lineNo = $index + 2;
                    $employeeUuid = strtolower(trim((string) ($row['employee_uuid'] ?? '')));
                    $email = strtolower(trim((string) ($row['email'] ?? '')));
                    $name = trim((string) ($row['name'] ?? ''));
                    $password = (string) ($row['password'] ?? '');
                    $confirmPassword = (string) ($row['confirm_password'] ?? '');
                    $employmentStatus = strtolower(trim((string) ($row['employment_status'] ?? 'active')));
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
                    if (! is_numeric($baseSalaryRaw)) {
                        $errors[] = "Row {$lineNo}: base_salary harus angka.";
                        continue;
                    }
                    $baseSalary = (float) $baseSalaryRaw;
                    if ($baseSalary < 0) {
                        $errors[] = "Row {$lineNo}: base_salary tidak boleh negatif.";
                        continue;
                    }
                    if (! in_array($employmentStatus, $this->employmentStatusOptions(), true)) {
                        $errors[] = "Row {$lineNo}: employment_status harus salah satu dari ".implode('|', $this->employmentStatusOptions()).'.';
                        continue;
                    }
                    if ($contractTypeInput !== null && ! in_array(strtolower($contractTypeInput), $this->acceptedContractTypeInputs(), true)) {
                        $errors[] = "Row {$lineNo}: contract_type harus contract|permanent (alias pkwt|pkwtt masih diterima saat migrasi).";
                        continue;
                    }
                    if ($contractStatus !== '' && ! in_array($contractStatus, $this->contractStatusOptions(), true)) {
                        $errors[] = "Row {$lineNo}: contract_status harus active|ended|terminated.";
                        continue;
                    }
                    if ($gender !== null && ! in_array($gender, ['male', 'female', 'other'], true)) {
                        $errors[] = "Row {$lineNo}: gender harus male|female|other.";
                        continue;
                    }
                    if ($maritalStatus !== null && ! in_array($maritalStatus, $this->maritalStatusOptions(), true)) {
                        $errors[] = "Row {$lineNo}: marital_status tidak valid.";
                        continue;
                    }
                    if ($religion !== null && ! in_array($religion, $this->religionOptions(), true)) {
                        $errors[] = "Row {$lineNo}: religion harus mengikuti daftar agama Indonesia yang disediakan.";
                        continue;
                    }
                    if ($bankName !== null && ! in_array($bankName, $this->acceptedBankNames(), true)) {
                        $errors[] = "Row {$lineNo}: bank_name tidak ada dalam daftar bank Indonesia yang didukung.";
                        continue;
                    }
                    if ($taxStatus !== '' && ! in_array($taxStatus, $this->acceptedTaxStatusInputs(), true)) {
                        $errors[] = "Row {$lineNo}: tax_status harus TK0-TK3 atau K0-K3 (alias TK/K masih diterima untuk kompatibilitas).";
                        continue;
                    }

                    $userIdFromUuid = $this->parseEmployeeUuidToUserId($employeeUuid);
                    $userByUuid = $userIdFromUuid !== null ? User::query()->find($userIdFromUuid) : null;
                    $userByEmail = $email !== '' ? User::query()->where('email', $email)->first() : null;

                    if ($userByUuid && $userByEmail && $userByUuid->id !== $userByEmail->id) {
                        $errors[] = "Row {$lineNo}: employee_uuid dan email mengacu ke user yang berbeda. Perbaiki salah satu identitas sebelum import.";
                        continue;
                    }

                    $user = $userByUuid ?: $userByEmail;

                    if (! $user) {
                        $employeeValidator->validateCanAddEmployees($lockedCompany, 1);
                        if ($name === '' || $email === '') {
                            $errors[] = "Row {$lineNo}: untuk create baru, name dan email wajib diisi.";
                            continue;
                        }
                        if ($password === '' || $confirmPassword === '' || $password !== $confirmPassword) {
                            $errors[] = "Row {$lineNo}: password dan confirm_password wajib serta harus sama untuk create baru.";
                            continue;
                        }
                        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
                            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
                    $bulkDeptName = $this->nullableString($row['department'] ?? null);
                    $bulkDesigId = isset($row['designation_id']) && is_numeric($row['designation_id']) ? (int) $row['designation_id'] : null;
                    $bulkDesigName = $this->nullableString($row['designation'] ?? null);

                    if ($bulkDeptId === null && $bulkDeptName !== null) {
                        $resolvedDepartment = Department::query()
                            ->where('company_id', $activeCompanyId)
                            ->whereRaw('LOWER(name) = ?', [strtolower($bulkDeptName)])
                            ->first();
                        if (! $resolvedDepartment) {
                            $errors[] = "Row {$lineNo}: department tidak ditemukan di company ini.";
                            continue;
                        }
                        $bulkDeptId = (int) $resolvedDepartment->id;
                        $bulkDeptName = $resolvedDepartment->name;
                    } elseif ($bulkDeptId !== null) {
                        // department_id is authoritative; ignore name column even if it differs
                        $resolvedDepartmentName = (string) (Department::query()
                            ->whereKey($bulkDeptId)
                            ->where('company_id', $activeCompanyId)
                            ->value('name') ?? '');
                        if ($resolvedDepartmentName === '') {
                            $errors[] = "Row {$lineNo}: department_id tidak ditemukan di company ini.";
                            continue;
                        }
                        $bulkDeptName = $resolvedDepartmentName;
                    }

                    if ($bulkDesigId === null && $bulkDesigName !== null) {
                        $designationQuery = Designation::query()
                            ->whereRaw('LOWER(name) = ?', [strtolower($bulkDesigName)])
                            ->where('company_id', $activeCompanyId);

                        if ($bulkDeptId !== null) {
                            $designationQuery->where('department_id', $bulkDeptId);
                        }

                        $resolvedDesignations = $designationQuery
                            ->orderBy('id')
                            ->get(['id', 'department_id', 'name']);

                        if ($resolvedDesignations->isEmpty()) {
                            $errors[] = "Row {$lineNo}: designation tidak ditemukan di company ini.";
                            continue;
                        }

                        if ($resolvedDesignations->count() > 1 && $bulkDeptId === null) {
                            $errors[] = "Row {$lineNo}: designation ditemukan di lebih dari satu department. Isi kolom department atau designation_id.";
                            continue;
                        }

                        $resolvedDesignation = $resolvedDesignations->first();
                        $bulkDesigId = (int) $resolvedDesignation->id;
                        $bulkDesigName = (string) $resolvedDesignation->name;

                        if ($bulkDeptId === null && $resolvedDesignation->department_id) {
                            $bulkDeptId = (int) $resolvedDesignation->department_id;
                        }
                    } elseif ($bulkDesigId !== null) {
                        // designation_id is authoritative; ignore name column even if it differs
                        $resolvedDesignationName = (string) (Designation::query()
                            ->whereKey($bulkDesigId)
                            ->where('company_id', $activeCompanyId)
                            ->value('name') ?? '');
                        if ($resolvedDesignationName === '') {
                            $errors[] = "Row {$lineNo}: designation_id tidak ditemukan di company ini.";
                            continue;
                        }
                        $bulkDesigName = $resolvedDesignationName;
                    }

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
                        // team_id is authoritative; ignore name column even if it differs
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
                    if ($bulkDeptId && ! Department::query()->whereKey($bulkDeptId)->where('company_id', $activeCompanyId)->exists()) {
                        $errors[] = "Row {$lineNo}: department_id tidak ditemukan di company ini.";
                        continue;
                    }
                    if ($bulkDesigId) {
                        $desigBelongsToCompanyDept = Designation::query()
                            ->whereKey($bulkDesigId)
                            ->whereHas('department', function ($query) use ($activeCompanyId) {
                                $query->where('company_id', $activeCompanyId);
                            })
                            ->exists();
                        if (! $desigBelongsToCompanyDept) {
                            $errors[] = "Row {$lineNo}: designation_id tidak ditemukan di company ini.";
                            continue;
                        }
                    }
                    $orgBulk = $this->resolveOrganizationForWrite($bulkDeptId, $bulkDesigId, $bulkDesigName);
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
                    $profile->fixed_allowance = 0;
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
                        'fixedAllowance' => 0,
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
        } catch (\RuntimeException $exception) {
            if ($errors === []) {
                throw $exception;
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
        $filePath = $file->getRealPath();
        if ($filePath === false) {
            return [];
        }

        // Prefer CSV-specific reader when extension indicates CSV/txt to handle
        // different delimiters (Excel in some locales uses semicolon).
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?? pathinfo($filePath, PATHINFO_EXTENSION) ?? ''));

        try {
            if (in_array($extension, ['csv', 'txt'], true)) {
                $sample = @file_get_contents($filePath, false, null, 0, 4096) ?: '';
                $firstLine = strtok($sample, "
");
                $commaCount = $firstLine !== false ? substr_count((string) $firstLine, ',') : 0;
                $semiCount = $firstLine !== false ? substr_count((string) $firstLine, ';') : 0;
                $delimiter = $commaCount >= $semiCount ? ',' : ';';

                $reader = IOFactory::createReader('Csv');
                if (method_exists($reader, 'setDelimiter')) {
                    $reader->setDelimiter($delimiter);
                }
                if (method_exists($reader, 'setEnclosure')) {
                    $reader->setEnclosure('"');
                }
                $spreadsheet = $reader->load($filePath);
            } else {
                $spreadsheet = IOFactory::load($filePath);
            }
        } catch (\Throwable $e) {
            // Loading failed (corrupt file / unsupported format)
            return [];
        }

        $sheet = $spreadsheet->getSheet(0);
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        $highestRow = $sheet->getHighestRow();
        if ($highestRow < 2) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            return [];
        }

        $headers = [];
        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $rawHeader = (string) $sheet->getCell([$column, 1])->getCalculatedValue();
            $rawHeader = preg_replace('/{FEFF}/u', '', $rawHeader); // strip BOM if present
            $headers[$column] = strtolower(trim($rawHeader));
        }

        $rows = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $item = [];
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $key = $headers[$column] ?? '';
                if ($key === '') {
                    continue;
                }
                $item[$key] = $sheet->getCell([$column, $row])->getCalculatedValue();
            }
            if (($item['employee_uuid'] ?? '') === '' && ($item['email'] ?? '') === '' && ($item['name'] ?? '') === '') {
                continue;
            }
            $rows[] = $item;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    private function parseEmployeeUuidToUserId(string $employeeUuid): ?int
    {
        if ($employeeUuid === '') {
            return null;
        }

        if (! Str::isUuid($employeeUuid)) {
            return null;
        }

        $userId = User::query()
            ->where('uuid', $employeeUuid)
            ->value('id');

        return is_numeric($userId) ? (int) $userId : null;
    }

    private function ensureBulkEmployeeOrganizationMastersReady(int $activeCompanyId, string $action): ?JsonResponse
    {
        $departmentCountCompany = Department::query()
            ->where('company_id', $activeCompanyId)
            ->count();

        $designationCountCompany = Designation::query()
            ->where('company_id', $activeCompanyId)
            ->count();

        // Require tenant-owned masters only (no global fallback allowed)
        $departmentCount = max(0, $departmentCountCompany);
        $designationCount = max(0, $designationCountCompany);

        if ($departmentCount > 0 && $designationCount > 0) {
            return null;
        }

        $missing = [];
        if ($departmentCount === 0) {
            $missing[] = 'department';
        }
        if ($designationCount === 0) {
            $missing[] = 'designation';
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'EMPLOYEE_BULK_ORG_SETUP_REQUIRED',
                'message' => 'Isi minimal satu department dan satu designation sebelum ' . $action . '.',
            ],
            'data' => [
                'departmentCount' => $departmentCount,
                'designationCount' => $designationCount,
                'missing' => $missing,
            ],
        ], 422);
    }
}
