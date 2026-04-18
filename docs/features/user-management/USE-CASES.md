# Use Case - User Management (HCM)

Dokumen ini mendefinisikan aturan akses menu User Management dengan pemisahan tegas antara:
- role company (tenant membership), dan
- role-permission aplikasi (RBAC fitur).

Tujuan utamanya: memastikan akses aplikasi tidak ditentukan oleh UI atau heuristik semata, tetapi oleh assignment role yang valid pada company aktif.

---

## 1. Ruang lingkup

| In scope | Out of scope (fase ini) |
|----------|-------------------------|
| Users list, detail, create, update, remove company membership | SSO/LDAP provisioning |
| Roles CRUD per company | Global policy engine lintas microservice |
| Permission catalog dan sync permission per role | Approval workflow perubahan role |
| Assign/revoke role ke user per company | ABAC berbasis attribute kompleks |

---

## 2. Model akses (wajib dipahami)

### 2.1 Layer A - Company role (tenant membership)

Sumber: membership user pada company aktif (contoh `company_users.role`).

Fungsi:
- Menentukan user berada di tenant/company mana.
- Menentukan boundary data tenant (isolasi antar company).
- Menjadi konteks dasar resolver `activeCompany`.

Catatan:
- Layer ini **bukan** pengganti permission fitur.
- Dua user bisa sama-sama `member`, tetapi hak menu aplikasi bisa berbeda lewat layer RBAC.

### 2.2 Layer B - Application role-permission (RBAC)

Sumber: assignment role per user per company (`hcm_user_roles`) + role-permission map (`hcm_role_permissions`).

Fungsi:
- Menentukan siapa boleh akses menu/aksi User Management.
- Menentukan otorisasi granular per aksi (view/create/update/delete/sync/assign/revoke).

Prinsip:
- Decision akhir izin ada di backend API.
- UI hanya menyesuaikan tampilan tombol agar UX jelas.

### 2.3 Employee bisa jadi admin

Bisa. Employee tetap employee sebagai data SDM, tetapi dapat hak admin aplikasi jika:
1. punya assignment role admin-level pada company aktif, dan
2. assignment tersebut aktif dan efektif (tanggal berlaku valid).

---

## 3. Aktor

### 3.1 Company User (non-admin)

User terautentikasi yang berada di company aktif tetapi tidak punya role-permission admin User Management.

### 3.2 Company Admin (RBAC)

User terautentikasi yang memiliki role admin-level (misalnya `ADMIN`, `HR_ADMIN`, `OPS_ADMIN`) pada company aktif, atau owner company sesuai policy tenant.

### 3.3 Owner Company

Pemilik company (membership role `owner`).

Catatan policy saat ini:
- Owner diperlakukan sebagai tenant-admin untuk company miliknya.
- Ke depan disarankan tetap dievaluasi lewat role-permission evaluator yang konsisten.

---

## 4. Matriks hak akses (target perilaku)

| Use case | Company User | Company Admin |
|----------|--------------|---------------|
| UC-01 Lihat daftar users (`GET /users`) | Tidak | Ya |
| UC-02 Export users CSV (`GET /users/export`) | Tidak | Ya |
| UC-03 Lihat user detail (`GET /users/{id}`) | Tidak | Ya |
| UC-04 Buat user (`POST /users`) | Tidak | Ya |
| UC-05 Ubah user (`PUT /users/{id}`) | Tidak | Ya |
| UC-06 Remove user from active company (`DELETE /users/{id}`) | Tidak | Ya (kecuali diri sendiri) |
| UC-07 Lihat roles (`GET /roles`) | Tidak | Ya |
| UC-08 Buat role (`POST /roles`) | Tidak | Ya |
| UC-09 Ubah role (`PUT /roles/{id}`) | Tidak | Ya |
| UC-10 Hapus/arsip role (`DELETE /roles/{id}`) | Tidak | Ya (dengan rule system role) |
| UC-11 Lihat permissions (`GET /permissions`) | Tidak | Ya |
| UC-12 Sync role permissions (`POST /roles/{id}/permissions:sync`) | Tidak | Ya |
| UC-13 Lihat assignment role user (`GET /users/{id}/roles`) | Tidak | Ya |
| UC-14 Assign role ke user (`POST /users/{id}/roles`) | Tidak | Ya |
| UC-15 Revoke role assignment (`DELETE /users/{id}/roles/{assignmentId}`) | Tidak | Ya |

---

## 5. Use case detail

### UC-01 - Lihat daftar users

- Aktor: Company Admin
- Tujuan: melihat user di company aktif beserta status membership dan role aktif.
- Prekondisi: active company context tersedia.
- Hasil: data terfilter tenant, tidak bocor lintas company.

Negatif:
- tanpa tenant context -> `422 TENANT_CONTEXT_REQUIRED`
- tanpa hak admin -> `403 AUTH_FORBIDDEN`

### UC-02 - Export users CSV

- Aktor: Company Admin
- Tujuan: audit user + role aktif dalam company aktif.
- Output minimal: user id, name, email, status, company role, active role codes.

Negatif:
- non-admin -> `403`

### UC-03 - Lihat detail user

- Aktor: Company Admin
- Tujuan: melihat detail user + assignment role + permission efektif.

Negatif:
- user tidak berada di company aktif -> `404 USER_NOT_FOUND`
- non-admin -> `403`

### UC-04 - Buat user baru

- Aktor: Company Admin
- Tujuan: membuat user baru dan mendaftarkan membership pada company aktif.
- Rule: `roleCodes` opsional, tetapi jika diisi harus valid untuk company aktif.

Negatif:
- role tidak valid -> `404 ROLE_NOT_FOUND`
- email duplikat / validasi gagal -> `422`

### UC-05 - Ubah user

- Aktor: Company Admin
- Tujuan: update data profil dasar + status membership.

Negatif:
- target bukan member company aktif -> `404 USER_NOT_FOUND`

### UC-06 - Remove user from active company

- Aktor: Company Admin
- Tujuan: mencabut membership user dari company aktif dan revoke role aktif terkait company itu.
- Rule: admin tidak boleh remove dirinya sendiri.

Negatif:
- self delete -> `422 SELF_DELETE_FORBIDDEN`

### UC-07 sampai UC-10 - Kelola role

- Aktor: Company Admin
- Tujuan: maintain role tenant (create/update/archive/delete).
- Rule:
  - system role tidak boleh dihapus langsung.
  - role yang masih dipakai diarsipkan, bukan hard delete.

Negatif:
- role tidak ditemukan -> `404 ROLE_NOT_FOUND`
- system role delete -> `422 ROLE_LOCKED`

### UC-11 sampai UC-12 - Kelola permission role

- Aktor: Company Admin
- Tujuan: sinkronisasi permission codes untuk role tertentu.
- Rule: sinkronisasi bersifat replace set (state akhir mengikuti payload).

Negatif:
- ada code permission invalid -> `404 PERMISSION_NOT_FOUND`
- payload kosong/tidak valid -> `422`

### UC-13 sampai UC-15 - Kelola assignment role user

- Aktor: Company Admin
- Tujuan: assign/revoke role user pada company aktif dengan dukungan effective date.
- Rule:
  - role harus milik company aktif.
  - revoke hanya untuk assignment status aktif.

Negatif:
- assignment tidak ditemukan -> `404 ROLE_ASSIGNMENT_NOT_FOUND`
- assignment sudah tidak aktif -> `422 ROLE_ASSIGNMENT_NOT_ACTIVE`

---

## 6. Aturan keamanan wajib

1. Semua endpoint user-management wajib tenant-scoped.
2. Semua endpoint mutasi wajib cek admin authorization di server.
3. UI boleh sembunyikan tombol, tetapi tidak boleh jadi source of truth izin.
4. Request lintas tenant harus ditolak (`403` atau `404` sesuai kontrak endpoint).
5. Audit assignment role (assign/revoke/remove membership) wajib tercatat.

---

## 7. Checklist verifikasi (manual)

1. Login sebagai non-admin company user.
2. Akses `/users` dan `/roles-permissions` langsung via URL.
3. Pastikan halaman tidak membuka operasi admin (redirect atau forbidden page yang jelas).
4. Coba panggil endpoint `/v1/hcm/user-management/*` dan pastikan `403`.
5. Login sebagai admin.
6. Verifikasi semua UC-01 s.d. UC-15 berjalan di company aktif.
7. Uji tenant boundary: admin company A tidak boleh mengelola user company B.

---

## 8. Keputusan terbuka

1. Owner company tetap implicit admin permanen atau dipindahkan murni ke role assignment?
2. Perlu unique guard assignment role aktif per user-role-company untuk mencegah duplikasi?
3. Halaman web `/users` dan `/roles-permissions` wajib diproteksi middleware admin langsung (disarankan: ya).
4. Heuristik admin berbasis profile (designation/team) kapan dipensiunkan penuh ke RBAC?
