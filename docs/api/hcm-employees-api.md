# Core HCM — Employees API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmEmployeeController.php`.

## Base path

`/v1/hcm`

Tenant context:
- Endpoint employee/organization membaca `activeCompany` dari middleware tenant context.
- Header opsional untuk override company aktif: `X-Company-Id` atau `X-Company-Code`.
- Jika company yang dipilih bukan membership aktif user, API mengembalikan `403 TENANT_FORBIDDEN`.
- **Global Super Admin bypass:** user dengan `users.is_super_admin = 1` melewati filter `company_id` pada `/v1/hcm/employees` sehingga dapat melihat seluruh employee lintas tenant tanpa `company_users` membership.

## Employees

### GET `/employees`

RBAC:
- HCM Admin only
- Global Super Admin (`users.is_super_admin = 1`) tetap memerlukan tenant context aktif (`activeCompanyId`) tetapi **tidak diblok** oleh validasi limit employee berbasis subscription saat create employee lintas tenant.

Query:
- `page` optional int >= 1
- `perPage` optional int 1..100 (default 20)
- `search` optional string max 100 (name/email/phone/NIK). Pada **MySQL**, jika panjang term ≥ 3 karakter, dipakai `FULLTEXT` untuk `users.name` + `users.email`; pencarian `phone` / `nik` tetap ikut via filter profil. Driver lain fallback ke `LIKE`.
- `status` optional `active|inactive|probation|resigned|terminated`
- `teamId` optional int (filter employee berdasarkan `team_id`)
- `scope` optional `global|active_company`
  - `global` (default untuk Global Super Admin): directory lintas semua tenant.
  - `active_company`: paksa scope ke tenant aktif (`activeCompanyId`) walaupun user adalah Global Super Admin.
  - user non-global-admin tetap selalu scope tenant aktif (parameter ini diabaikan).

Success `200`:
- `data[]`: `{ id, uuid, fullName, email, phone, team, teamId, teamName, teamIsActive, departmentId, departmentName, designationId, designationName, designation, employeeType, baseSalary, fixedAllowance, employmentStatus, hireDate, joinDate, contractType, contractStartDate, contractEndDate, pkwtDueThisMonth, estimatedPkwtCompensationThisMonth, npwp, taxStatus, ptkpStatus, ptkpAnnualNominal }`
- `team`: label backward-compatible untuk UI lama.
- `teamId` + `teamName`: field canonical untuk UI list/report baru.
- `teamIsActive`: `true/false/null` untuk indikator UX assignment team inactive.
- `uuid` disertakan sebagai identifier user stabil untuk modul yang mengirim payload UUID ke endpoint lain, termasuk Termination.
- `designation` = label tampilan (prioritas nama master `designation_id` jika ada).
- `employeeType`, gaji, pajak, assignment, dan kontrak di-resolve dari tabel riwayat relasional (`employee_employment_history`, `employee_assignments`, `employee_compensations`, `employee_contracts`, `employee_tax_profiles`) dengan fallback legacy agar backward-compatible.
- `fixedAllowance` dipertahankan di payload untuk kompatibilitas UI lama, tetapi runtime saat ini selalu `0`; tunjangan tetap operasional sudah dipindahkan ke allowance governance.
- `ptkpAnnualNominal` = nominal PTKP tahunan berdasarkan `ptkpStatus` ter-normalisasi (`TK/K` otomatis dipetakan ke `TK0/K0`); bernilai `null` jika status pajak tidak valid/kosong.
- `contractType` distandardkan ke `pkwt|pkwtt` (alias legacy `permanent` masih diterima pada input lama, tetapi disimpan/ditampilkan sebagai `pkwtt`).
- `hireDate` = tanggal bergabung di profil (nullable ISO). `joinDate` = `startDate` / `hireDate` jika ada, jika tidak → tanggal `created_at` user.
- `phone` kosong → `"—"`; `departmentName` kosong → `"—"`.
- `meta.page`, `meta.perPage`, `meta.total` (pagination)
- `meta.summary`: agregat mengikuti scope request saat ini:
  - scope `global` → ringkasan lintas tenant
  - scope `active_company` / non-global-admin → ringkasan tenant aktif
  - field tetap: `totalEmployees`, `activeEmployees`, `inactiveEmployees`, `probationEmployees`, `newJoiners`

### POST `/employees`

RBAC:
- HCM Admin only

Body:
- `name` required string min 2 max 150
- `email` required email:rfc max 255 unique
- `password` required regex `password_strong`
- `confirmPassword` required same:password
- `team` optional string max 100 (legacy read compatibility; untuk write admin tidak boleh jadi satu-satunya sumber assignment)
- `teamId` optional int exists `teams.id` pada tenant aktif (canonical field untuk assignment)
- `departmentId` **required** int exists `departments.id`
- `designationId` **required** int exists `designations.id` (atau kirim `designation` label legacy jika memang belum memakai master, tetapi UI produksi sekarang mewajibkan master designation)
- `designation` optional string max 150 (fallback legacy jika tanpa `designationId`)
- `employeeType` **required** string (`permanent|contract|intern|...`)
- `startDate` optional `date` — effective start untuk employment history
- `baseSalary` **required** angka non-negatif dengan pola digit `^[0-9]+$`
- `fixedAllowance` optional numeric min 0, tetapi diabaikan runtime (accepted for backward compatibility only)
- `salaryType` **required** `monthly|daily|hourly`
- `contractType` **required** `pkwt|pkwtt` (alias `permanent` masih diterima untuk kompatibilitas lalu dinormalisasi ke `pkwtt`)
- `contractStatus` **required** `active|ended|terminated`
- `contractStartDate` **required** `date`
- `contractEndDate` **required hanya untuk `pkwt`** dan ditolak untuk `pkwtt`
- `probationEndDate` optional `date` (dipakai jika `employmentStatus = probation`)
- `managerUserId` optional int exists `users.id` — masih didukung API admin, tetapi field ini sudah disembunyikan dari UI employee modal
- `employmentStatus` optional `active|inactive|probation|resigned|terminated`
- `nik` / alias `ktpNo` **required** regex `^[0-9]{16}$` — satu sumber data untuk **NIK / nomor KTP**
- `phone` **required** regex `^[0-9]{10,13}$`
- `placeOfBirth`, `dateOfBirth`, `gender` (`male|female|other`), `maritalStatus` (`single|married|divorced|widowed`), `religion`, dan `address` **required**
- `provinceId`, `regencyId`, `districtId`, `villageId` **required** integer (ID master wilayah) dengan relasi hirarki valid:
  - `regencyId` harus milik `provinceId`
  - `districtId` harus milik `regencyId`
  - `villageId` harus milik `districtId`
- Validasi lookup wilayah menggunakan kolom numeric `id` (bukan `uuid`) agar konsisten dengan payload UI/API yang mengirim ID integer.
- `nationality` otomatis dinormalisasi ke **`Indonesia`** dan input selain Indonesia ditolak
- `bankName`, `bankAccountNo`, `bankAccountHolderName` **required**; `bankIfscCode`, `bankBranch` optional
- `npwp`, `taxStatus`, `ptkpStatus`, `bpjsKesehatanNo`, `bpjsKetenagakerjaanNo` optional
- `taxStatus` / `ptkpStatus` mengikuti enum Indonesia `TK0..TK3` / `K0..K3` (alias `TK` → `TK0`, `K` → `K0` masih diterima)
- `emergencyContacts` **required** array `min:1`, dan minimal satu item harus memiliki `name`, `relationship`, dan `phone` valid
- `educationItems`, `experienceItems` optional array

Success `201`:

```json
{
  "success": true,
  "data": { "id": 10, "uuid": "9ad3476b-9d62-4a4d-8c48-7f44322edc7f", "fullName": "Budi", "email": "budi@company.com" }
}
```

Error `422`:
- `DESIGNATION_DEPARTMENT_MISMATCH` — `designationId` tidak termasuk `departmentId` yang dikirim.
- `TEAM_INACTIVE_NOT_ASSIGNABLE` — `teamId` mengacu ke team inactive.
- `TEAM_MASTER_SELECTION_REQUIRED` — payload mengirim free-text `team` tanpa `teamId` master.

### GET `/employees/{id}`

RBAC:
- HCM Admin: any employee
- Non-admin: hanya self (`id == auth.id`)

Errors:
- `404 EMPLOYEE_NOT_FOUND`
- `403 AUTH_FORBIDDEN`

Success `200`:
- mengembalikan detail profile + `uuid`, `departmentId`, `departmentName`, `designationId`, `designationName`, `designation`, `employeeType`, `startDate`, serta field personal top-level: `nik`, `ktpNo`, `placeOfBirth`, `dateOfBirth`, `gender`, `maritalStatus`, `religion`, `nationality`
- nested blocks:
  - `personal { nik, ktpNo, placeOfBirth, dateOfBirth, gender, maritalStatus, religion, nationality }`
  - `assignment { team, departmentId, departmentName, designationId, designationName, managerUserId }`
  - `compensation { salaryType, currency, baseSalary, fixedAllowance, effectiveDate }`
  - `compensation.fixedAllowance` selalu `0` pada runtime sekarang; source tunjangan tetap legacy sudah dipensiunkan.
  - `contract { contractType, startDate, endDate, status }`
  - `bank { name, accountNo, accountHolderName, ifscCode, branch }`
  - `taxProfile { npwp, taxStatus, ptkpStatus }`
  - `benefits { bpjsKesehatanNo, bpjsKetenagakerjaanNo }`
  - `schedule` (timing override/auto)
- history/detail collections yang sekarang juga ikut dikembalikan:
  - `employmentHistory[]`
  - `assignmentHistory[]`
  - `compensationHistory[]`
  - `contractHistory[]`
  - `bankAccounts[]`
  - `documents[]` (saat ini placeholder kosong sampai modul dokumen karyawan ditutup penuh)
  - `emergencyContacts[]`, `educationItems[]`, `experienceItems[]`

### PUT `/employees/{id}`

RBAC:
- HCM Admin: can update many fields
- Non-admin: hanya self, dan hanya subset field (phone/address/bio/emergency/education/experience)

Admin body (semua `sometimes`):
- `name` string min 2 max 150
- `email` email:rfc unique ignore current id
- `team` string max 100 (legacy compatibility; jika dipakai tanpa `teamId` akan ditolak)
- `teamId` nullable int exists `teams.id` pada tenant aktif
- `departmentId` nullable int exists `departments.id`
- `designationId` nullable int exists `designations.id` (aturan sinkron sama seperti POST)
- `designation` string max 150
- `managerUserId` integer exists users.id, different:id
- `employeeType` optional string
- `startDate` optional `date`
- `baseSalary` numeric min 0
- `fixedAllowance` numeric min 0, tetapi diabaikan runtime (accepted for backward compatibility only)
- `salaryType` optional `monthly|daily|hourly`
- `contractType` optional `pkwt|pkwtt` (alias `permanent` diterima)
- `contractStatus` optional `active|ended|terminated`
- `contractStartDate`, `contractEndDate`, `probationEndDate`
- `employmentStatus` enum `active|inactive|probation|resigned|terminated`
- `nik` / `ktpNo` regex `^[0-9]{16}$`
- `phone` regex `^[0-9]{10,13}$` (numeric only)
- `address` string max 2000
- `placeOfBirth` string max 150
- `dateOfBirth` `date`
- `gender` enum `male|female|other`
- `maritalStatus` enum `single|married|divorced|widowed`
- `religion` string max 50
- `nationality` string max 100
- `bio` string max 5000
- `bankName` max 150, `bankAccountNo` max 100, `bankAccountHolderName` max 150, `bankIfscCode` max 100, `bankBranch` max 150
- `npwp`, `taxStatus`, `ptkpStatus`, `bpjsKesehatanNo`, `bpjsKetenagakerjaanNo`
- `emergencyContacts` array
- `educationItems` array
- `experienceItems` array
- `hireDate` / `startDate` sometimes nullable `date` — tanggal mulai bekerja / effective start

Self update body:
- hanya boleh kirim keys: `nik`, `ktpNo`, `phone`, `address`, `placeOfBirth`, `dateOfBirth`, `gender`, `maritalStatus`, `religion`, `nationality`, `bio`, `emergencyContacts`, `educationItems`, `experienceItems`
- validasi format tetap berlaku (`nik` 16 digit, `phone` 10–13 digit, `nationality` hanya `Indonesia`)
- jika kirim field lain → `403 AUTH_FORBIDDEN`

Success `200`:
- return payload sama seperti GET `/employees/{id}`

Error `422`:
- `TEAM_INACTIVE_NOT_ASSIGNABLE` — assignment ke team inactive ditolak.
- `TEAM_MASTER_SELECTION_REQUIRED` — assignment wajib memakai `teamId` dari master teams.

### POST `/employees/{id}/profile-photo`

RBAC:
- HCM Admin: boleh upload foto untuk employee dalam tenant aktif
- Non-admin: hanya boleh upload foto milik sendiri (`id == auth.id`)

Identifier:
- Path `{id}` saat ini **numeric legacy** (`users.id`)

Multipart body:
- `photo` required file image
- format: `jpg|jpeg|png|gif`
- max size: `2048 KB`

Success `200`:
- `data.profilePhotoUrl` URL publik foto terbaru

Error:
- `403 AUTH_FORBIDDEN`
- `404 EMPLOYEE_NOT_FOUND`
- `422 INVALID_MEDIA`

### DELETE `/employees/{id}/profile-photo`

RBAC:
- HCM Admin: boleh hapus foto untuk employee dalam tenant aktif
- Non-admin: hanya boleh hapus foto milik sendiri (`id == auth.id`)

Identifier:
- Path `{id}` saat ini **numeric legacy** (`users.id`)

Behavior:
- File lama di storage akan dihapus (jika ada)
- `employee_profiles.profile_photo_path` di-reset ke `null`

Success `200`:
- `data.profilePhotoUrl = null`

Error:
- `403 AUTH_FORBIDDEN`
- `404 EMPLOYEE_NOT_FOUND`

## Bulk

### GET `/employees/bulk-template`

RBAC:
- HCM Admin only

Behavior:
- download `employee-bulk-template.xlsx`

### POST `/employees/bulk-upload`

RBAC:
- HCM Admin only

Multipart:
- `file` required file max 10240KB, mimes `xlsx,xls,csv,txt`

Success `200`:
- `data.createdRows`, `data.updatedRows`, `data.failedRows`, `data.errors[]`

Error `422`:
- `BULK_UPLOAD_VALIDATION_FAILED` — file bulk dibatalkan secara **all-or-nothing** jika ada **satu saja** baris tidak valid; tidak ada perubahan yang disimpan.

Catatan:
- parsing menerima `employee_uuid` atau `email` untuk match existing
- create baru butuh `name,email,password,confirm_password`
- validasi enum sekarang ketat untuk `employment_status`, `contract_type`, `contract_status`, `gender`, `marital_status`, `religion`, `bank_name`, `tax_status`
- kolom opsional yang sudah dipakai importer mencakup `employee_type`, `start_date`, `contract_type`, `contract_status`, `contract_start_date`, `contract_end_date`, `probation_end_date`, `nik`, `place_of_birth`, `date_of_birth`, `gender`, `marital_status`, `religion`, `nationality`, `bank_account_holder_name`, `npwp`, `tax_status`, `ptkp_status`, `bpjs_kesehatan_no`, `bpjs_ketenagakerjaan_no`
- `department_id`, `designation_id` (integer, harus ada di master); `designation` teks tetap didukung; kombinasi id divalidasi seperti API POST
- Jika `team_id` dikirim, importer memverifikasi team tenant aktif dan status team harus `active`.
- Jika hanya `team` (nama) dikirim, importer akan resolve ke master team tenant aktif bila nama match; jika match ke team inactive maka baris ditolak.
- setelah import sukses, snapshot legacy `employee_profiles` dan tabel riwayat relasional akan di-sync bersamaan.
- template saat ini masih **single-sheet**; final polish berikutnya bisa dinaikkan menjadi template multi-sheet dengan referensi master/dropdown.

## Salary bulk aliases (route duplikat)

Routes berikut saat ini alias ke bulk template/upload yang sama:
- `GET /employees/salary-template` → bulk-template
- `POST /employees/salary-bulk-upload` → bulk-upload

