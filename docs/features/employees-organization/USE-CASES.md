# Use Case — Employees & Organization (HCM)

Dokumen ini mendefinisikan **siapa boleh melakukan apa** pada fitur employee, alur utama, variasi, dan edge case. Dipakai sebagai acuan produk + engineering sebelum mengunci RBAC di API/UI.

**Status implementasi vs dokumen:** RBAC inti employee (list/create/admin update, self read/update subset) dan master org (department/designation/policy) sudah mengikuti §3 / §5.2 di `HcmEmployeeController`; lihat §9 untuk item sisa (bulk merge, dll.).

---

## 1. Ruang lingkup

| In scope | Out of scope (fase ini) |
|----------|-------------------------|
| Daftar karyawan, detail profil, create/update via UI modal | RBAC granular per field (mis. hanya HR Payroll lihat gaji) |
| Quick preview & navigasi list ↔ detail | Workflow approval perubahan data karyawan |
| Bulk upload + template Excel | Sinkron ke LDAP/SSO |
| Relasi ke kompensasi (`base_salary`, `fixed_allowance`) untuk overtime | Department/designation master — lihat use case terpisah jika perlu |

---

## 2. Definisi aktor

### 2.1 Pengguna terautentikasi (Authenticated User)

Siapa pun yang sudah login (cookie HttpOnly + token valid untuk `/v1/hcm/*`).

### 2.2 Karyawan (Employee / Self)

Pengguna yang **hanya** berhak mengakses **data dirinya sendiri** pada konteks employee (profil sendiri, bukan direktori seluruh perusahaan).

### 2.3 Admin HCM (`hcmAdmin`)

Pengguna dengan hak administrasi modul HCM employee (dan fitur terkait yang memang dirancang admin-only).

**Deteksi di sistem saat ini (kode):**

- Respons `GET /v1/identity/auth/me` menyertakan flag boolean **`hcmAdmin`** (selaras dengan `User::isHcmAdmin()` dan trait `EnsuresHcmAdmin`).
- Logika admin saat ini adalah **heuristik** (email khusus QA + keyword pada `employee_profiles.designation` / `team`). Ini **bukan** matriks role DB penuh; dokumentasi ini mengasumsikan flag `hcmAdmin` = “boleh akses use case admin” sampai RBAC formal menggantinya.

### 2.4 Sistem / Job (tidak dipakai di fase ini)

Tidak ada batch non-interaktif selain bulk upload yang dipicu admin via UI.

---

## 3. Ringkasan matriks hak (target perilaku)

| Use case | Employee (self) | HCM Admin |
|----------|-----------------|-----------|
| UC-01 Lihat daftar semua karyawan | Tidak | Ya |
| UC-02 Lihat detail karyawan (semua field bisnis) | Hanya `id` = user login | Ya (semua ID) |
| UC-03 Buat karyawan baru | Tidak | Ya |
| UC-04 Ubah data karyawan | Hanya profil sendiri (scope field §5.2) | Ya (semua field yang didukung API) |
| UC-05 Quick preview di list | Sama seperti UC-02 (hanya self jika non-admin) | Ya |
| UC-06 Download template bulk | Tidak | Ya |
| UC-07 Bulk upload employee | Tidak | Ya |

**Catatan UX:** Halaman `/employees` saat ini berorientasi admin (tombol add, edit, bulk). Untuk karyawan non-admin, navigasi ke direktori penuh **boleh disembunyikan** atau dialihkan; yang penting API tidak mengizinkan akses lintas user.

---

## 4. Use case detail

### UC-01 — Melihat daftar karyawan

- **ID:** UC-01  
- **Aktor utama:** HCM Admin  
- **Tujuan:** Melihat ringkasan karyawan (nomor, nama, email, team, designation, status, tanggal join, dll.) untuk operasional HR.

**Prekondisi:** Pengguna terautentikasi.

**Alur utama:**

1. Admin membuka `/employees` (atau view grid jika ada).
2. Sistem memanggil `GET /v1/hcm/employees` dengan kredensial cookie.
3. Sistem menampilkan tabel + ringkasan kartu (total, aktif, dll.).

**Postkondisi:** Data yang ditampilkan sesuai filter query (jika ada).

**Ekstensi / negatif:**

- **E1 — Tidak login:** redirect login / 401 dari API.
- **E2 — Bukan admin:** **403** (target); tidak boleh mengembalikan seluruh list.

**Aturan bisnis:**

- Pagination `perPage` dibatasi (mis. max 100) agar performa terjaga.
- `employeeNo` di API diformat konsisten (contoh `EMP-0001`).

---

### UC-02 — Melihat detail karyawan

- **ID:** UC-02  
- **Aktor:** HCM Admin (semua ID); Employee (hanya diri sendiri)  
- **Tujuan:** Melihat profil lengkap termasuk kompensasi, kontak, bank, array education/experience/emergency jika ada.

**Prekondisi:** Terautentikasi.

**Alur utama:**

1. Pengguna membuka `/employee-details?id={userId}` (atau dari link internal).
2. Sistem memanggil `GET /v1/hcm/employees/{id}`.

**Postkondisi:** Data ditampilkan di halaman detail.

**Ekstensi:**

- **E1 — `id` bukan milik user dan bukan admin:** **403**.
- **E2 — User tidak ada:** **404** `EMPLOYEE_NOT_FOUND`.

**Aturan privasi (target):**

- Field kompensasi (`baseSalary`, `fixedAllowance`) **hanya** untuk self + admin HCM (sampai ada kebijakan lebih ketat).

---

### UC-03 — Menambah karyawan baru

- **ID:** UC-03  
- **Aktor:** HCM Admin saja  
- **Tujuan:** Mendaftarkan akun user baru + `employee_profiles` awal.

**Prekondisi:** Admin terautentikasi.

**Alur utama (UI modal):**

1. Admin klik “Add Employee”, isi minimal: nama, email, password + konfirmasi, opsional team, designation, status, salary.
2. Submit → `POST /v1/hcm/employees` dengan body JSON sesuai kontrak API.

**Postkondisi:** User baru ada di DB; profile terbentuk; admin dapat memberi tahu karyawan kredensial awal (di luar sistem).

**Ekstensi:**

- **E1 — Email duplikat:** **409** atau **422** dengan pesan jelas.
- **E2 — Password tidak memenuhi kebijakan:** **422**.
- **E3 — Non-admin:** **403**.

**Aturan bisnis:**

- Password kuat sesuai validasi backend (regex yang sudah ada).
- `employmentStatus` default `active` jika tidak diisi.

---

### UC-04 — Mengubah data karyawan

- **ID:** UC-04  
- **Aktor:** HCM Admin (semua); Employee (hanya self, subset field §5.2)  
- **Tujuan:** Memperbarui nama, email, profil HR, kompensasi, bank, dll.

**Prekondisi:** Terautentikasi; untuk admin, karyawan target harus ada.

**Alur utama (UI modal admin):**

1. Admin klik edit pada baris → modal terisi dari `GET /v1/hcm/employees/{id}`.
2. Submit → `PUT /v1/hcm/employees/{id}`.

**Postkondisi:** Data tersimpan; response mengikuti `show`.

**Ekstensi:**

- **E1 — Non-admin mengubah orang lain:** **403**.
- **E2 — Self mencoba ubah field terlarang (mis. gaji orang lain — tidak relevan; gaji self jika policy melarang):** **403** atau abaikan field (keputusan produk §8).

---

### UC-05 — Quick preview dari daftar

- **ID:** UC-05  
- **Aktor:** Sama UC-02 (admin: semua; non-admin: hanya self jika ada path ke preview diri — secara praktis fitur ini untuk admin)

**Alur:** Klik baris → offcanvas → opsional `GET` detail untuk render.

**Aturan:** Response API yang dipakai harus tunduk pada **ownership** yang sama dengan UC-02.

---

### UC-06 — Download template bulk employee

- **ID:** UC-06  
- **Aktor:** HCM Admin saja  
- **Tujuan:** Unduh file Excel contoh kolom untuk impor massal.

**Alur:** `GET /v1/hcm/employees/bulk-template` → file `employee-bulk-template.xlsx`.

**Ekstensi:** Non-admin → **403**.

---

### UC-07 — Bulk upload / impor data karyawan

- **ID:** UC-07  
- **Aktor:** HCM Admin saja  
- **Tujuan:** Create atau update banyak baris dari file `.xlsx` / `.xls` / `.csv`.

**Prekondisi:** Admin terautentikasi; file valid (ukuran & mime).

**Alur utama:**

1. Admin unduh template (UC-06).
2. Admin isi baris data → upload via `POST /v1/hcm/employees/bulk-upload` (multipart `file`).
3. Sistem memvalidasi seluruh file lebih dulu; jika semua lolos maka create/update dijalankan dalam satu transaksi, lalu mengembalikan ringkasan `createdRows`, `updatedRows`, `failedRows`, daftar `errors`.

**Aturan identitas baris (penting untuk menghindari salah sasaran):**

- **Update** jika baris mengidentifikasi user yang sudah ada:
  - Prioritas 1: `employee_no` valid (`EMP-{digits}`) → resolve ke `users.id` sesuai konvensi sistem.
  - Prioritas 2: jika `employee_no` kosong/tidak valid, fallback **`email`** unik ke user.
- **Create** jika user belum ada: wajib `name`, `email`, `password`, `confirm_password` (dan validasi email unik).
- **Konflik:** Jika `employee_no` **dan** `email` keduanya diisi tetapi mengacu ke **dua user berbeda**, baris ditolak dengan error jelas (target perilaku — hindari silent wrong update).

**Aturan merge field (target untuk menghindari data loss):**

- Kolom kosong pada baris **tidak boleh** menghapus nilai DB yang sudah ada (partial update per baris), **kecuali** produk secara eksplisit mendefinisikan “empty = clear” untuk kolom tertentu (disarankan tidak untuk payroll/PII).
- Controlled fields Indonesia (`employment_status`, `salary_type`, `contract_type`, `contract_status`, `religion`, `bank_name`, `tax_status`) harus mengikuti daftar resmi; alias legacy tertentu (`permanent`, `TK`, `K`, `Mandiri`) masih diterima lalu dinormalisasi.

**Ekstensi:**

- **E1 — Non-admin:** **403**.
- **E2 — File kosong / tidak ada baris data:** **422** `EMPTY_FILE`.
- **E3 — Ada satu atau lebih baris gagal validasi:** seluruh import dibatalkan (**all-or-nothing rollback**), `createdRows` dan `updatedRows` kembali `0`.
- **E4 — Final polish berikutnya:** template dapat dinaikkan menjadi **multi-sheet import workbook** agar referensi master (departemen, jabatan, bank, enum) lebih aman untuk user operasional.

---

## 5. Scope field per aktor (rekomendasi)

### 5.1 HCM Admin — `PUT /employees/{id}`

Semua field yang didukung controller saat ini: identitas user + profile personal (termasuk `nik`, tempat/tanggal lahir, gender, status pernikahan, agama, kebangsaan) + bank + JSON arrays + kompensasi.

### 5.2 Employee (self) — subset (rekomendasi awal)

| Boleh diubah self | Tidak boleh / admin only |
|-------------------|---------------------------|
| `nik`, `phone`, `address`, `placeOfBirth`, `dateOfBirth`, `gender`, `maritalStatus`, `religion`, `nationality`, `bio` | `baseSalary`, `fixedAllowance` (kecuali kebijakan perusahaan mengizinkan) |
| Opsional: emergency contact (jika produk setuju) | `employmentStatus`, `team`, `designation` |
| | `email` (bisa dibatasi: verifikasi email / ticket HR) |

Angka di atas bisa disesuaikan satu baris keputusan produk; yang penting **dibatasi di API**, bukan hanya di UI.

---

## 6. Integrasi dengan fitur lain

- **Overtime / kalkulator:** membaca `baseSalary` & `fixedAllowance` dari profil; hanya admin atau konteks yang sah yang boleh mengubah angka ini.
- **Attendance / leave:** memakai `user_id`; tidak mengubah use case employee kecuali referensi organisasi.

---

## 7. Non-functional

- **Audit trail:** belum wajib di fase ini; disarankan log siapa admin yang bulk upload (future).
- **Ukuran file bulk:** sesuai validasi (mis. max 10MB); batasi jumlah baris jika perlu.
- **Rate limiting:** pertimbangkan throttle khusus endpoint bulk untuk mencegah abuse.

---

## 8. Keputusan terbuka (untuk diparkir produk)

1. Apakah karyawan boleh melihat **gaji sendiri** di UI detail?
2. Apakah perubahan `email` self dilakukan langsung atau harus melalui HR?
3. Kapan mengganti heuristik `isHcmAdmin()` dengan tabel role/resmi?

---

## 9. Gap implementasi terhadap dokumen ini (April 2026)

| Area | Yang diinginkan dokumen | Kode saat ini (ringkas) |
|------|-------------------------|-------------------------|
| `GET/POST` employees (list, create) | Hanya **hcmAdmin** | `ensureHcmAdmin` di `index` / `store` |
| `GET/PUT` employees `{id}` | Admin semua ID; non-admin hanya self; self **PUT** subset field §5.2 | `show` + `update` (cabang admin vs self) + tes `HcmEmployeeApiTest` |
| Departments / designations / policies | Master HCM admin | `ensureHcmAdmin` pada GET dan mutasi + destroy\* |
| Bulk template/upload | Admin only | Sudah `ensureHcmAdmin` |
| Bulk merge | Partial row tidak menghapus data | Perlu diselaraskan di service impor |
| Identitas baris bulk | Tolak jika `employee_no` vs email mismatch | Perlu validasi eksplisit |

**UI:** `/employees`, `/employees-grid`, `/employee-report` memeriksa `me.hcmAdmin` lalu redirect non-admin ke `/employee-dashboard`; `/departments`, `/designations`, `/policy` sama via `hcm-pages-data.js`.

---

## 10. Referensi cepat endpoint (prefix `/v1/hcm`)

| Endpoint | Use case |
|----------|----------|
| `GET /employees` | UC-01 |
| `GET /employees/{id}` | UC-02, UC-05 |
| `POST /employees` | UC-03 |
| `PUT /employees/{id}` | UC-04 |
| `GET /employees/bulk-template` | UC-06 |
| `POST /employees/bulk-upload` | UC-07 |

Endpoint alias lama `salary-template` / `salary-bulk-upload` (jika masih ada) diperlakukan sebagai kompatibilitas; dokumen baru mengutamakan nama `bulk-*`.
