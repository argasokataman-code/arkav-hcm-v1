# HCM Permission Scope Reference

Dokumen ini adalah referensi cepat untuk melihat permission apa saja yang saat ini bisa masuk ke scope role HCM, role default mana yang langsung mendapat scope penuh, dan gap apa saja yang masih tidak selaras dengan fallback super admin.

Tujuan utamanya: saat ada bug role/permission, tim tidak perlu menebak-nebak lagi apakah sebuah code permission memang valid, cuma legacy, atau belum masuk katalog assignable.

---

## 1. Sumber kebenaran yang dipakai sekarang

Ada tiga sumber permission yang saat ini hidup bersamaan, dan ini memang sumber kekacauan utama:

1. `backend/database/seeders/HcmUserManagementSeeder.php`
   Ini katalog permission HCM yang benar-benar dipakai untuk role setup company-scoped. Saat seeder ini aktif, ada **76 permission** yang bisa di-assign ke role HCM.
2. `backend/app/Http/Controllers/Api/AuthController.php`
   Ini fallback global super admin untuk `/v1/identity/auth/me`. Daftar ini berisi **permission legacy yang jauh lebih luas** dan banyak nama code-nya tidak sama dengan katalog role HCM saat ini.
3. `docs/planning/active-hcm-templates-and-permissions.md`
   Ini matriks halaman dan target akses. Dokumen ini menjelaskan siapa boleh buka halaman, tetapi tidak mendefinisikan katalog code permission secara rinci.

Prinsip operasional yang aman:

- Untuk **role setup HCM per company**, anggap **`HcmUserManagementSeeder`** sebagai katalog assignable saat ini.
- Untuk **global super admin runtime fallback**, anggap `AuthController::getAllPermissionsForGlobalAdmin()` sebagai fallback legacy yang perlu terus diselaraskan.
- Jangan pakai nama permission baru di UI/controller kalau code itu belum ada di katalog assignable atau belum diputuskan jadi code resmi.

---

## 2. Role default dan scope default-nya

Berdasarkan `HcmUserManagementSeeder`, role yang dibuat otomatis per company adalah:

- `ADMIN`
- `HR_ADMIN`
- `OPS_ADMIN`
- `OWNER`
- `HCM_ADMIN`
- `MANAGER`
- `EMPLOYEE`

Scope default yang aktif saat ini:

| Role | Scope default saat seeding | Catatan |
|------|----------------------------|---------|
| `ADMIN` | Semua 76 permission HCM | Full company-scoped admin |
| `HR_ADMIN` | Semua 76 permission HCM | Full company-scoped admin |
| `OPS_ADMIN` | Semua 76 permission HCM | Full company-scoped admin |
| `OWNER` | Semua 76 permission HCM | Full company-scoped admin |
| `HCM_ADMIN` | Semua 76 permission HCM | Full company-scoped admin |
| `MANAGER` | Tidak ada permission default | Harus diisi eksplisit jika ingin granular scope |
| `EMPLOYEE` | Tidak ada permission default | Akses self-service banyak masih ditentukan endpoint ownership, bukan role permission granular |

Implikasinya:

- Kalau mau “scope super admin HCM per company”, paket yang paling aman saat ini adalah **semua 76 permission HCM**.
- Kalau mau role menengah seperti Manager, katalog permission-nya **sudah ada**, tetapi mapping default-nya **belum ada**.

---

## 3. Katalog permission HCM yang bisa di-assign sekarang

Total current catalog: **76 permission**.

### Attendance (7)

- `attendance.view`
- `attendance.create`
- `attendance.update`
- `attendance.admin`
- `timesheet.view`
- `schedule.view`
- `schedule.manage`

### Dashboard (1)

- `dashboard.view`

### Employee (5)

- `employee.view`
- `employee.create`
- `employee.update`
- `employee.delete`
- `employee.export`

### Holiday (4)

- `holiday.view`
- `holiday.create`
- `holiday.update`
- `holiday.sync`

### HR Actions (6)

- `promotion.view`
- `promotion.manage`
- `resignation.view`
- `resignation.manage`
- `termination.view`
- `termination.manage`

### Leave (7)

- `leave.view`
- `leave.create`
- `leave.update`
- `leave.approve`
- `leave.reject`
- `leave.settings`
- `leave.type`

### Organization (6)

- `department.view`
- `department.manage`
- `designation.view`
- `designation.manage`
- `policy.view`
- `policy.manage`

### Overtime (4)

- `overtime.view`
- `overtime.create`
- `overtime.approve`
- `overtime.type.manage`

### Payroll (9)

- `payroll.view`
- `payroll.create`
- `payroll.update`
- `payroll.run`
- `payroll.finalize`
- `payroll.disburse`
- `payroll.item.manage`
- `payroll.thr.manage`
- `payroll.pkwt.manage`

### Performance (4)

- `performance.view`
- `performance.manage`
- `goal.view`
- `goal.manage`

### Report (2)

- `report.view`
- `report.export`

### System (3)

- `settings.view`
- `settings.manage`
- `cron.manage`

### Ticket (5)

- `ticket.view`
- `ticket.create`
- `ticket.update`
- `ticket.assign`
- `ticket.category.manage`

### Training (4)

- `training.view`
- `training.manage`
- `trainer.view`
- `trainer.manage`

### User Management (9)

- `user.view`
- `user.create`
- `user.update`
- `user.assign_role`
- `role.view`
- `role.create`
- `role.update`
- `role.delete`
- `role.sync_permission`

---

## 4. Scope minimum per area kritikal

Ini scope minimum praktis untuk area yang sedang sering bermasalah.

### User Management page `/users`

Minimum untuk lihat list dan operasional penuh user management:

- `user.view`
- `user.create`
- `user.update`
- `user.assign_role`
- `role.view`

Tambahan untuk kontrol role dari modal/aksi terkait:

- `role.create`
- `role.update`
- `role.delete`
- `role.sync_permission`

### Roles & Permissions page `/roles-permissions`

Minimum untuk operasional penuh role setup:

- `role.view`
- `role.create`
- `role.update`
- `role.delete`
- `role.sync_permission`

UX aktif saat ini di halaman `/roles-permissions`:

- daftar role menampilkan preview permission yang sudah menempel ke role, bukan hanya jumlah total
- halaman utama merangkum blueprint permission per modul agar admin bisa cepat membaca cakupan akses tanpa tenggelam di katalog panjang
- modal create/edit role memakai builder dua panel ala package create: panel kiri untuk ringkasan role + preview pilihan, panel kanan untuk katalog permission yang bisa dicari, select-visible, dan reset
- setiap item permission di modal tetap menampilkan code + module/resource/action sehingga admin bisa memilih akses saat role dibuat, bukan menebak dari nama role saja

### Super Admin company-scoped HCM

Untuk kebutuhan operasional HCM penuh lintas menu company-scoped, paket paling aman saat ini adalah:

- semua **76 permission** pada seksi §3

### Super Admin global runtime fallback

Untuk login global admin (`/v1/identity/auth/me` fallback), runtime saat ini masih memberi set permission legacy yang jauh lebih luas dari katalog assignable HCM. Ini berguna untuk akses global, tetapi **belum aman dijadikan referensi tunggal untuk role setup**.

---

## 5. Gap yang sekarang memang bikin bug

Ini gap paling penting yang sudah terbukti berpotensi bikin UI dan backend tidak sinkron.

| Area | Fallback global admin / legacy | Katalog assignable HCM sekarang | Dampak |
|------|-------------------------------|----------------------------------|--------|
| User CRUD | `user.edit`, `user.delete`, `user.admin` | `user.update`, tidak ada `user.delete`, tidak ada `user.admin` | UI/backend bisa cek code yang berbeda |
| Role CRUD | `role.edit`, `role.admin` | `role.update`, tidak ada `role.admin` | Tombol atau guard bisa salah 403/hidden |
| Permission sync | `permission.view`, `permission.manage`, `permission.assign` | `role.sync_permission` | UI yang cari `permission.manage` akan salah |
| Settings | `setting.view`, `setting.edit`, `setting.admin` | `settings.view`, `settings.manage` | Singular vs plural mismatch |
| Attendance | `attendance.edit`, `attendance.delete` | `attendance.update` | Guard edit bisa miss |
| Employee | `employee.edit`, `employee.admin`, `employee.import` | `employee.update`, tidak ada `employee.admin`, tidak ada `employee.import` | Scope admin legacy tidak setara katalog HCM |
| Leave | `leave.edit`, `leave.delete`, `leave.admin` | `leave.update`, `leave.reject`, `leave.settings`, `leave.type` | Operasi modern leave tidak tergambar di fallback |
| Payroll | `payroll.edit`, `payroll.delete`, `payroll.admin` | `payroll.update`, `payroll.run`, `payroll.finalize`, `payroll.item.manage`, `payroll.thr.manage`, `payroll.pkwt.manage` | Scope payroll baru tidak match legacy fallback |
| Training | `training.create`, `training.edit`, `training.delete`, `training.admin` | `training.manage`, `trainer.view`, `trainer.manage` | UI lama bisa pakai code berbeda |
| HR actions | `promotion.admin`, `resignation.admin`, `termination.admin` | `promotion.manage`, `resignation.manage`, `termination.manage` | Pattern admin vs manage tidak konsisten |

Kesimpulan praktis:

- Bug role/permission yang muncul belakangan ini **bukan cuma di UI**, tapi juga karena **nama code permission memang belum satu bahasa**.
- Selama fallback global admin belum diselaraskan ke katalog assignable HCM, setiap halaman baru berisiko pakai code lama yang sebenarnya tidak ada di role setup modern.

---

## 6. Rekomendasi normalisasi

Kalau mau scope ini “enak diatur”, arah normalisasi yang paling aman adalah:

1. Jadikan katalog di `HcmUserManagementSeeder` sebagai **kamus resmi permission HCM modern**.
2. Selaraskan `AuthController::getAllPermissionsForGlobalAdmin()` ke code modern itu, minimal untuk modul HCM yang aktif.
3. Larang penambahan code baru dengan pola legacy seperti `*.edit`, `*.admin`, `permission.manage`, atau `setting.*` kalau modul modernnya sudah punya code `*.update`, `*.manage`, `role.sync_permission`, atau `settings.*`.
4. Tambahkan mapping default untuk `MANAGER` dan `EMPLOYEE` kalau memang mau role-based UI lebih granular; saat ini dua role itu praktis masih banyak bergantung ke ownership/self scope di endpoint.

---

## 7. Ringkasan satu kalimat

Kalau pertanyaannya adalah “scope apa yang aman dipakai sekarang untuk super admin HCM?”, jawabannya: **pakai semua 76 permission dari katalog `HcmUserManagementSeeder`, dan anggap fallback global admin di `AuthController` masih legacy yang perlu dibersihkan.**