# Planning: Fix Attendance Correction Flow (Needs Review → Resolution)

**Tanggal dibuat:** 2026-05-25  
**Terakhir diaudit:** 2026-05-25 ✅ (audit selesai — 15 gap, 100% coverage)  
**Status:** OPEN — belum dikerjakan  
**Prioritas:** Medium-High (menyentuh daily employee & admin workflow)  
**Referensi tracker:** `docs/features/attendance/tracker.md` → Gap #2

---

## 1. Konteks Masalah

Flow koreksi attendance saat ini **tidak punya closure end-to-end yang jelas**. Employee bisa mengajukan koreksi, tapi admin tidak dinotifikasi. Admin bisa melihat alasan koreksi, tapi tidak bisa langsung approve dari sana. Approval terjadi secara implisit saat admin mengedit record. Employee tidak tahu bahwa koreksinya sudah disetujui.

### Trigger Status `needs_review`
Record diberi status `needs_review` ketika net productive time < **4 jam (240 menit)** saat punch out.  
Konstanta: `AttendanceController::EARLY_PUNCH_OUT_REVIEW_MINUTES = 4 * 60`

### Lifecycle Status Koreksi Saat Ini
```
correction_status: none
       ↓ (employee submit correction request)
correction_status: requested
       ↓ (admin edit record via adminUpsertRecord)
correction_status: approved   ← IMPLISIT, tidak ada notifikasi ke siapapun
```

---

## 2. Gap Inventory — Lengkap

### GAP-A · Admin tidak dinotifikasi saat koreksi diajukan [TINGGI]
**Kondisi saat ini:**  
`requestCorrection()` hanya menyimpan data ke DB. Tidak ada `NotificationEventCatalog` event, tidak ada notifikasi in-app ke admin.

**Dampak:**  
Admin tidak tahu ada yang perlu ditangani. Mereka harus membuka tabel attendance secara manual dan mencari baris dengan suffix `(Requested)`.

**File terdampak:**  
- `backend/app/Http/Controllers/Api/Attendance/AttendanceController.php` — `requestCorrection()`  
- `backend/app/Support/Hcm/NotificationEventCatalog.php`  
- `backend/app/Notifications/` — perlu class baru  
- `backend/app/Models/CompanyUser.php` — untuk menemukan admin target  

---

### GAP-B · Modal correction detail tidak punya tombol aksi [TINGGI]
**Kondisi saat ini:**  
Modal `#arcav_attendance_correction_detail` hanya punya tombol "Close". Admin membaca alasan lalu harus tutup modal, cari row yang sama, baru klik Edit.

**Dampak:**  
UX admin putus di tengah. Dua aksi yang semestinya satu alur menjadi dua step terpisah dengan friski navigasi.

**File terdampak:**  
- `backend/resources/views/hrm/attendance-admin.blade.php`  
- `frontend/resources/js/attendance/attendance-actions.js` — handler `[data-attendance-correction-view]`  

---

### GAP-C · Modal edit tidak menampilkan alasan koreksi employee [TINGGI]
**Kondisi saat ini:**  
Modal `#arcav_edit_attendance` hanya berisi field Work date, Check in, Check out, Break, Late. Tidak ada informasi bahwa record ini punya pending correction, apalagi isi alasannya.

**Dampak:**  
Admin membuka form edit tanpa konteks. Mereka tidak tahu *mengapa* employee minta koreksi. Alasan yang sempat dibaca di modal sebelumnya sudah tertutup.

**File terdampak:**  
- `backend/resources/views/components/modals/attendance.blade.php` — tambah section correction context  
- `frontend/resources/js/attendance/attendance-actions.js` — populate correction context saat buka edit modal  

---

### GAP-D · Dua modal terpisah untuk satu task (see reason → edit) [SEDANG]
**Kondisi saat ini:**  
Correction detail modal dan edit modal adalah dua modal yang sama sekali tidak terhubung. Tidak ada tombol "Edit Record" di modal correction detail.

**Dampak:**  
Admin harus menutup modal pertama, kembali ke tabel, mencari row yang sama, lalu membuka modal kedua.

**File terdampak:**  
- `backend/resources/views/hrm/attendance-admin.blade.php` — tambah tombol "Edit Record" di footer modal correction detail  
- `frontend/resources/js/attendance/attendance-actions.js` — handler tombol Edit dari dalam correction modal (buka edit modal dengan data row yang sama)  

---

### GAP-E · Approval implisit — admin tidak tahu Save mereka juga approve correction [SEDANG]
**Kondisi saat ini:**  
```php
if ((string) $rec->correction_status === 'requested') {
    $rec->correction_status = 'approved';  // silent
}
```
Tidak ada indikasi di UI edit modal bahwa Save akan otomatis meng-approve correction request.

**Dampak:**  
Cognitive gap — admin tidak sadar bahwa aksi mereka menyelesaikan correction workflow, bukan sekadar mengedit jam kerja.

**File terdampak:**  
- `backend/resources/views/components/modals/attendance.blade.php` — tambah badge/peringatan "⚠ Ada pending correction request"  
- `frontend/resources/js/attendance/attendance-actions.js` — set/unset banner di edit modal berdasarkan data row  

---

### GAP-F · Employee tidak dinotifikasi saat koreksinya disetujui [SEDANG]
**Kondisi saat ini:**  
`adminUpsertRecord()` tidak mengirim notifikasi apapun ke employee setelah auto-approve.

**Dampak:**  
Employee harus polling (refresh halaman) untuk tahu apakah koreksi mereka sudah diproses. Tidak ada feedback loop.

**File terdampak:**  
- `backend/app/Http/Controllers/Api/Attendance/AttendanceController.php` — `adminUpsertRecord()`  
- `backend/app/Support/Hcm/NotificationEventCatalog.php`  
- `backend/app/Notifications/` — perlu class baru  

---

### GAP-G · Tidak ada state "approved" di UI employee [SEDANG]
**Kondisi saat ini:**  
```js
if (d.needsReview || d.correctionStatus === "requested") {
    // tampilkan tombol
} else {
    correctionBtn.classList.add("d-none");  // hilang tanpa pesan
}
```
Setelah approved: tombol hilang begitu saja. Tidak ada badge "Koreksi disetujui".

**Dampak:**  
Employee bingung apakah status mereka sudah beres atau belum, terutama karena alert orange juga ikut hilang.

**File terdampak:**  
- `frontend/resources/js/attendance/attendance-employee-attendance.js`  
- `backend/resources/views/hrm/attendance-employee.blade.php` — mungkin perlu elemen tambahan untuk state "approved"  

---

### GAP-H · workDate hardcode ke hari ini — employee tidak bisa koreksi hari lalu [SEDANG]
**Kondisi saat ini:**  
```js
var today = new Date();
var dateStr = today.getFullYear() + ...  // always today
```
Backend `requestCorrection()` support `workDate` sembarang. Tapi UI selalu kirim hari ini.

**Dampak:**  
Employee yang baru sadar ada anomali di hari sebelumnya tidak bisa mengajukan koreksi dari history page.

**File terdampak:**  
- `frontend/resources/js/attendance/attendance-extras.js` — ubah agar workDate diambil dari data row history (bukan hardcode today)  
- `frontend/resources/js/attendance/attendance-employee-attendance.js` — tambah tombol correction di history rows (conditional)  
- `backend/resources/views/hrm/attendance-employee.blade.php` — kemungkinan perlu elemen tambahan di history list  

---

### GAP-I · History tidak expose `correctionStatus` per baris [RENDAH]
**Kondisi saat ini:**  
`meHistory` response tidak return `correctionStatus`, `correctionReason`.

**Dampak:**  
Employee tidak bisa audit mana saja record yang pernah dikoreksi atau masih pending dari daftar riwayat.

**File terdampak:**  
- `backend/app/Http/Controllers/Api/Attendance/AttendanceController.php` — `meHistory()` response mapping  

---

### GAP-J · Tidak ada eskalasi/expiry jika admin mengabaikan correction request [RENDAH]
**Kondisi saat ini:**  
`correction_status = 'requested'` bisa bertahan selamanya tanpa ada tindakan. Tidak ada auto-reject, reminder, atau batas waktu.

**Dampak:**  
Employee stuck dengan tombol "Koreksi diajukan" disabled selamanya jika admin tidak memprosesnya.

**File terdampak:**  
- Perlu artisan command baru atau logic di cronjob yang ada  
- `backend/routes/console.php` — tambah schedule  

---

### GAP-K · Tidak ada counter/indikator "pending correction" di admin [RENDAH]
**Kondisi saat ini:**  
Admin tidak punya cara cepat untuk tahu ada berapa pending correction request. Tidak ada badge di sidebar, tidak ada widget di dashboard.

> **Catatan audit:** `adminIndex()` sebenarnya SUDAH return `correctionStatus`, `correctionReason`, `correctionRequestedAt` per baris (line 392-396). Yang kurang hanya **aggregasi** di `meta.summary` (`pendingCorrectionCount`) dan UI counter-nya.

**Dampak:**  
Discovery issue — admin harus aktif membuka halaman attendance dan melihat satu per satu.

**File terdampak:**  
- `backend/app/Http/Controllers/Api/Attendance/AttendanceController.php` — `adminIndex()` tambah `pendingCorrectionCount` di `meta.summary` (data per-baris sudah ada)  
- `backend/resources/views/hrm/attendance-admin.blade.php` — elemen counter  
- `frontend/resources/js/attendance/attendance-admin-attendance.js` — render counter  

---

### GAP-L · `punch()` checkout hard-reset semua correction fields [SEDANG]
**Kondisi saat ini:**  
Pada branch punch-out di `punch()` (line 1221), semua correction fields di-reset:
```php
$rec->correction_status = 'none';
$rec->correction_reason = null;
$rec->correction_requested_at = null;
$rec->corrected_by_user_id = null;
$rec->corrected_at = null;
```
Tidak ada server-side guard yang mencegah punch-out jika sudah ada `correction_status = 'requested'`.

**Dampak:**  
Jika API punch dipanggil langsung (bypass UI yang mem-disable tombol), correction request yang sedang pending akan terhapus diam-diam tanpa notifikasi ke siapapun.

**File terdampak:**  
- `backend/app/Http/Controllers/Api/Attendance/AttendanceController.php` — `punch()` — tambah guard: tolak punch-out kedua jika record sudah punya `check_out_at`, atau setidaknya jangan reset `correction_status` jika nilainya bukan `'none'`  

---

### GAP-M · `requestCorrection()` tidak ada guard re-request [RENDAH]
**Kondisi saat ini:**  
`requestCorrection()` tidak mengecek apakah `correction_status` sudah `'requested'`. Employee bisa overwrite request yang sudah ada dengan reason dan timestamp baru.

**Dampak:**  
Employee bisa menimpa request mereka sendiri (bisa disalahgunakan untuk "refresh" request yang terabaikan, atau tidak sengaja menimpa reason yang sudah benar). Admin juga tidak dapat notifikasi ulang.

**File terdampak:**  
- `backend/app/Http/Controllers/Api/Attendance/AttendanceController.php` — `requestCorrection()` — tambah check: jika `correction_status === 'requested'`, return 422 dengan pesan informatif  

---

### GAP-N · Delete record via `adminUpsertRecord` menghapus pending correction tanpa notifikasi [RENDAH]
**Kondisi saat ini:**  
`adminUpsertRecord()` mendukung soft-delete record (semua field kosong → `$rec->delete()`). Jika record yang dihapus punya `correction_status = 'requested'`, request employee hilang sepenuhnya tanpa notifikasi.

**Dampak:**  
Employee tidak tahu koreksinya "ditolak" atau record-nya dihapus. Tombol correction tetap disabled di sisi employee karena data sudah tidak ada.

**File terdampak:**  
- `backend/app/Http/Controllers/Api/Attendance/AttendanceController.php` — `adminUpsertRecord()` — sebelum delete, jika `correction_status === 'requested'`, kirim notifikasi ke employee bahwa record dihapus  

---

### GAP-O · Tidak ada batas waktu pengajuan koreksi — employee bisa koreksi absensi dari tanggal berapa pun [TINGGI]

**Kondisi saat ini:**  
`requestCorrection()` hanya memvalidasi:
```php
'workDate' => ['required', 'date'],
```
Tidak ada batas berapa hari ke belakang employee boleh mengajukan koreksi. Selama record ada di DB, koreksi bisa diajukan kapan pun — bahkan untuk absensi 6 bulan lalu.

**Dampak bisnis:**  
Payroll sudah difinalisasi untuk bulan sebelumnya. Jika employee mengajukan koreksi untuk tanggal yang sudah masuk payroll yang sudah dikunci, admin bisa tidak sengaja approve → inkonsistensi data payroll vs attendance.

**Solusi:**  
Tambah setting per-company `attendance_correction_window_days` (default: `30`). Nilai `0` berarti tidak terbatas. Disimpan di tabel `company_settings` (EAV pattern yang sudah dipakai oleh `HcmInvoiceSettingsController`, `HcmPayrollSettingsController`, dll.).

---

#### Impact Runtime — Detail Lengkap

**1. Tabel `company_settings` (EAV — tidak perlu migrasi)**  
Pattern yang dipakai: `CompanySetting::query()->updateOrCreate(['company_id' => $id, 'key' => 'attendance_correction_window_days'], ['value' => '30', 'type' => 'integer'])`  
Tidak ada perubahan schema DB. Key baru ditambahkan via `updateOrCreate` saat admin menyimpan.

**2. `requestCorrection()` — validasi runtime baru**  
```php
// Ambil setting per-company
$windowDays = (int) CompanySetting::query()
    ->where('company_id', $activeCompanyId)
    ->where('key', 'attendance_correction_window_days')
    ->value('value') ?? 30;

if ($windowDays > 0) {
    $oldestAllowed = Carbon::now($tz)->subDays($windowDays)->startOfDay();
    $workDateCarbon = Carbon::parse($workDate, $tz)->startOfDay();
    if ($workDateCarbon->lt($oldestAllowed)) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'CORRECTION_WINDOW_EXCEEDED',
                'message' => "Koreksi hanya dapat diajukan untuk absensi dalam {$windowDays} hari terakhir.",
            ],
        ], 422);
    }
}
```

**3. `meHistory()` — expose `correctionEligible` per baris**  
Frontend perlu tahu mana baris yang masih bisa dikoreksi tanpa harus hit API dulu. Tambah field:
```json
"correctionEligible": true
```
Logic: `correction_window_days === 0 OR workDate >= today - windowDays AND correctionStatus NOT IN ['requested', 'approved']`

**4. Employee UI — disable/hide tombol koreksi untuk baris di luar window**  
Saat ini `attendance-extras.js` hardcode `workDate = today`. Setelah GAP-H fix (workDate dari data row), UI history harus juga cek `correctionEligible` dari response sebelum render tombol.

**5. Settings endpoint baru**  
Pola yang diikuti: `HcmSmartAttendanceController@settings` + `@updateSettings` yang sudah ada di `routes/api/attendance.php`.  
Perlu endpoint baru:
- `GET /v1/hcm/attendance/settings` — return semua attendance settings termasuk `correctionWindowDays`  
- `PUT /v1/hcm/attendance/settings` — simpan setting baru ke `company_settings`  
RBAC: `ensureHcmAdmin` (sama dengan endpoint admin lainnya di file ini).

**6. Admin UI — settings page**  
Tambah field input di halaman settings admin attendance:  
- Input number `correctionWindowDays` (min: 0, max: 365, default: 30)
- Label: "Batas maksimal pengajuan koreksi absensi (hari ke belakang). Isi 0 untuk tidak terbatas."
- Blade + JS wiring di attendance-admin.blade.php atau halaman settings yang relevan.

---

#### Impact Development — Ringkasan File

| File | Perubahan |
|------|----------|
| `AttendanceController.php` — `requestCorrection()` | Tambah validasi window + read setting |
| `AttendanceController.php` — `meHistory()` | Tambah field `correctionEligible` per baris |
| **`HcmAttendanceSettingsController.php`** (baru) | Class baru: `show()` + `update()` menggunakan `CompanySetting` EAV |
| `routes/api/attendance.php` | Tambah 2 route: `GET` + `PUT /v1/hcm/attendance/settings` |
| `attendance-extras.js` | Cek `correctionEligible` sebelum render tombol koreksi |
| `attendance-employee-attendance.js` | History row — render tombol hanya jika `correctionEligible === true` |
| `attendance-admin.blade.php` atau settings blade | Tambah field setting `correctionWindowDays` |
| `docs/api/hcm-attendance-api.md` | Dokumentasikan endpoint settings + field baru |
| `docs/api/openapi.yaml` | Sinkronkan: 2 route baru + field `correctionEligible` di meHistory |

**File yang TIDAK perlu diubah:**  
- Tabel DB / migrasi — tidak perlu, EAV pattern
- `adminUpsertRecord()` — tidak terpengaruh (admin tidak dibatasi window)
- Notifikasi class — tidak terpengaruh

**File terdampak ringkas (untuk impact matrix):**  
- `backend/app/Http/Controllers/Api/Attendance/AttendanceController.php` — `requestCorrection()` + `meHistory()`
- `backend/app/Http/Controllers/Api/Attendance/HcmAttendanceSettingsController.php` — **class baru**
- `backend/routes/api/attendance.php` — 2 route baru
- `frontend/resources/js/attendance/attendance-extras.js` — cek `correctionEligible`
- `frontend/resources/js/attendance/attendance-employee-attendance.js` — history row button guard
- `backend/resources/views/hrm/attendance-admin.blade.php` — settings UI field
- `docs/api/hcm-attendance-api.md` + `docs/api/openapi.yaml`

| File | Gap yang Disentuh | Tipe Perubahan |
|------|-------------------|----------------|
| `AttendanceController.php` | A, F, I, K, L, M, N, O | Backend logic + response |
| `NotificationEventCatalog.php` | A, F | Tambah event key |
| `Notifications/` (class baru) | A, F | Class baru ×2 |
| **`HcmAttendanceSettingsController.php`** (baru) | O | Class baru: GET + PUT settings |
| `routes/api/attendance.php` | O | 2 route baru settings |
| `attendance-admin.blade.php` | B, D, O | Tambah elemen UI + settings field |
| `modals/attendance.blade.php` | C, E | Tambah context section |
| `attendance-actions.js` | B, C, D, E | Handler event baru |
| `attendance-admin-attendance.js` | K | Render counter |
| `attendance-employee-attendance.js` | G, O | State machine update + window guard |
| `attendance-extras.js` | H, O | workDate dynamic + eligibility check |
| `attendance-employee.blade.php` | G, H | Elemen UI baru |
| `routes/console.php` | J | Schedule baru |
| `AttendanceController::meHistory()` | I, O | Response field tambah |

---

## 4. Urutan Eksekusi (Dependency Order)

### Fase 1 — Notification Infrastructure (prerequisite untuk A & F)
Harus dikerjakan duluan karena Fase 2 bergantung pada event keys yang sudah ada.

1. **Tambah event keys di `NotificationEventCatalog`**:
   - `attendance.correction.requested` — notifikasi ke admin HCM
   - `attendance.correction.approved` — notifikasi ke employee

2. **Buat 2 Notification class**:
   - `AttendanceCorrectionRequestedNotification` — dikirim ke semua HCM admin tenant saat employee submit correction
   - `AttendanceCorrectionApprovedNotification` — dikirim ke employee saat admin approve (edit record)

---

### Fase 2 — Backend Integration (depends on Fase 1)

3. **`requestCorrection()` kirim notifikasi ke admin** (fix GAP-A + GAP-M):
   - Guard re-request: jika `correction_status === 'requested'`, return 422 (fix GAP-M)
   - Temukan semua admin tenant (role IN `['owner', 'admin', 'hcm_admin', 'super_admin']`) via `CompanyUser` — konsisten dengan `HcmProbationCycleCommand`
   - Kirim `AttendanceCorrectionRequestedNotification`

4. **`adminUpsertRecord()` kirim notifikasi ke employee setelah approve** (fix GAP-F + GAP-N):
   - Kirim `AttendanceCorrectionApprovedNotification` ke employee yang bersangkutan
   - Jika record di-delete dan `correction_status === 'requested'`, kirim notifikasi bahwa record dihapus (fix GAP-N)

5. **`meHistory()` tambah `correctionStatus` dan `correctionReason` di response** (fix GAP-I)

6. **(Opsional) `adminIndex()` tambah `pendingCorrectionCount` di meta** (fix GAP-K partial)
   - Data per-baris sudah tersedia; cukup hitung `WHERE correction_status = 'requested'` di stats query

7. **`punch()` tambah guard double-checkout** (fix GAP-L):
   - Tolak atau abaikan punch-out kedua jika `check_out_at` sudah terisi
   - Jangan reset correction fields jika `correction_status !== 'none'`

8. **Tambah settings endpoint + validasi window koreksi** (fix GAP-O):
   - Buat `HcmAttendanceSettingsController` dengan `show()` + `update()`
   - Daftarkan `GET` + `PUT /v1/hcm/attendance/settings` di `routes/api/attendance.php`
   - `requestCorrection()`: baca `attendance_correction_window_days` dari `company_settings`, validasi `workDate >= today - windowDays`
   - `meHistory()`: tambah field `correctionEligible` per baris
   - Admin UI: tambah field setting di halaman settings attendance

---

### Fase 3 — Admin UI (depends on Fase 2)

7. **Modal correction detail — tambah tombol "Edit Attendance"** (fix GAP-B + GAP-D):
   - Footer modal: tambah tombol `data-attendance-correction-open-edit` di samping "Close"
   - Handler: simpan data row (userId, checkIn, checkOut, break, late, reason) ke variable state, lalu hide correction modal + show edit modal pre-filled

8. **Modal edit — tampilkan correction context** (fix GAP-C + GAP-E):
   - Tambah section `data-attendance-correction-context` di dalam modal body
   - Saat membuka edit modal, jika row punya `correctionStatus === 'requested'`: tampilkan banner dengan alasan employee
   - Banner: "⚠ Ada pending correction request — menyimpan perubahan ini akan otomatis approve request tersebut."

---

### Fase 4 — Employee UI (tidak depends pada Fase 2/3, bisa paralel)

9. **Tambah state "approved" di UI employee** (fix GAP-G):
   - Kondisi: `correctionStatus === 'approved'`
   - UI: badge hijau "Koreksi disetujui" (bukan tombol, cukup info text)

10. **workDate dinamis dari data row history** (fix GAP-H):
    - `attendance-extras.js`: ubah agar `workDate` diambil dari `data-work-date` attribute di tombol correction (bukan `new Date()`)
    - History rows: tambah tombol "Ajukan koreksi" per row jika `statusLabel === 'Needs Review'` dan `correctionStatus !== 'requested'`

---

### Fase 5 — Housekeeping (independen, prioritas rendah)

11. **(Opsional) Artisan command expiry pending correction** (fix GAP-J):
    - Command: `hcm:attendance-correction-expiry`
    - Logic: koreksi dengan `correction_status = 'requested'` lebih dari N hari (configurable via `CronjobSettings`) → kirim reminder ke admin

---

## 5. Testing Requirements Per Fase

### Fase 1 & 2 — PHPUnit
- `AttendanceCorrectionNotificationTest` (baru):
  - Saat `requestCorrection()` dipanggil → notifikasi terkirim ke semua admin tenant
  - Saat `adminUpsertRecord()` approve → notifikasi terkirim ke employee
  - Tenant isolation: admin tenant lain tidak dapat notifikasi
  - `meHistory` response per row include `correctionStatus`

### Fase 3 — Vitest (UI wiring)
- Modal correction detail: klik "Edit Attendance" → edit modal terbuka dengan data pre-filled
- Edit modal: jika row punya `correctionStatus === 'requested'` → banner correction context tampil
- Edit modal: jika row tidak punya correction → banner tidak tampil

### Fase 4 — Vitest (UI wiring)
- State `correctionStatus === 'approved'` → badge hijau tampil, tombol "Ajukan koreksi" tidak tampil
- State `correctionStatus === 'requested'` → tombol disabled "Koreksi diajukan" tampil
- History row dengan `needs_review` → tombol "Ajukan koreksi" tersedia

### Regression Check
- Semua test attendance yang sudah ada harus tetap pass:
  - `AttendanceApiTest`
  - `AttendanceAdminTenantScopeTest`
  - `attendance.wiring.test.js`

---

## 6. API Contract Changes

### Tidak ada breaking change.

**`meHistory` response — tambah field opsional per row:**
```json
{
  "correctionStatus": "none | requested | approved",
  "correctionReason": "string | null",
  "correctionEligible": true
}
```

**Endpoint baru `GET /v1/hcm/attendance/settings`** (RBAC: `ensureHcmAdmin`):
```json
{
  "success": true,
  "data": {
    "correctionWindowDays": 30
  }
}
```

**Endpoint baru `PUT /v1/hcm/attendance/settings`** (RBAC: `ensureHcmAdmin`):
```json
// Request
{ "correctionWindowDays": 14 }
// Response
{ "success": true, "data": { "correctionWindowDays": 14 } }
```

**`adminIndex` response — tambah field opsional di meta (jika GAP-K dikerjakan):**
```json
{
  "meta": {
    "pendingCorrectionCount": 3
  }
}
```

**Endpoint yang tidak berubah kontraknya:**
- `POST /v1/hcm/attendance/me/correction-request` — tidak berubah
- `PUT /v1/hcm/attendance/admin/record` — tidak berubah

> Setelah implementasi selesai, sinkronkan `docs/api/hcm-attendance-api.md` dan `docs/api/openapi.yaml`.

---

## 7. Out of Scope (tidak dikerjakan dalam plan ini)

- Approval chain berlapis (misal: manager → HR → selesai) — terlalu kompleks untuk fase ini
- Rejection flow (admin menolak koreksi dengan alasan) — belum ada kebutuhan bisnis eksplisit
- Correction request untuk multiple dates sekaligus
- Push/email notification — hanya in-app database notification

---

## 8. Definition of Done

- [ ] Fase 1 selesai: 2 event keys + 2 notification class
- [ ] Fase 2 selesai: PHPUnit test baru pass, notifikasi terkirim ke arah yang benar dengan tenant isolation
- [ ] GAP-L fix: `punch()` tidak bisa reset correction jika ada pending request
- [ ] GAP-M fix: `requestCorrection()` return 422 jika sudah ada pending request
- [ ] GAP-N fix: delete record dengan pending correction kirim notifikasi ke employee
- [ ] Fase 3 selesai: Admin UX — correction detail modal punya "Edit" button, edit modal tampilkan correction context
- [ ] Fase 4 selesai: Employee UX — state approved, correction di history rows
- [ ] GAP-O fix: `HcmAttendanceSettingsController` selesai, setting `correctionWindowDays` bisa disimpan dan dibaca
- [ ] GAP-O fix: `requestCorrection()` tolak request di luar window dengan error `CORRECTION_WINDOW_EXCEEDED`
- [ ] GAP-O fix: `meHistory` return `correctionEligible` per baris, UI employee pakai field ini untuk guard tombol
- [ ] Regression: semua existing attendance test tetap pass
- [ ] API docs diupdate: `hcm-attendance-api.md` + `openapi.yaml`
- [ ] Attendance tracker diupdate: Gap #2 ditutup
