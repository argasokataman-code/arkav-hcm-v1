# E2E Testing — Approval Settings

## Prasyarat

- Login sebagai **HCM Admin** atau **Company Owner**
- Company memiliki package yang mencakup module yang akan diuji
- Browser devtools terbuka (Network tab untuk cek API response)

---

## Skenario 1: Halaman Settings

### 1.1 Render Halaman
1. Buka `/approval-settings`
2. **Expect:**
   - Halaman tidak error
   - Section Leave Approval muncul (karena `leave_management` selalu ada di MVP)
   - Jika company memiliki add-on `overtime` → section Overtime Approval muncul
   - Jika company **tidak** memiliki add-on `resignation` → section Resignation Approval **tidak** muncul
   - Tidak ada `console.error` atau 401 di Network tab

### 1.2 Dynamic Visibility
1. Login sebagai company **tanpa** package `overtime`
2. Buka `/approval-settings`
3. **Expect:** Hanya section Leave yang muncul

### 1.3 Empty State
1. Buka `/approval-settings` (company baru, belum pernah set config)
2. **Expect:**
   - Setiap section menampilkan "No approvers configured"
   - Approval mode default: Simultaneous
   - Approver dropdown kosong

---

## Skenario 2: Konfigurasi Approver

### 2.1 Create Config — Sequence Mode
1. Buka `/approval-settings`
2. Pada section Leave Approval:
   - Pilih **Sequence** mode
   - Cari approver via dropdown (ketik nama employee)
   - Pilih 2 approver (urutan: Level 1, Level 2)
   - Klik **Save**
3. **Expect:**
   - Toast sukses "Approval settings saved"
   - Approvers muncul di daftar dengan urutan yang benar
   - `PUT /v1/hcm/approval-settings/leave` response 200

### 2.2 Create Config — Simultaneous Mode
1. Pada section Overtime Approval:
   - Pilih **Simultaneous** mode
   - Pilih 1 approver
   - Klik **Save**
2. **Expect:**
   - Toast sukses
   - Mode badge: "Simultaneous"
   - Network: 200

### 2.3 Update Config
1. Edit section Leave yang sudah disimpan sebelumnya
2. Ganti mode ke Simultaneous
3. Ganti approver ke orang lain
4. Klik **Save**
5. **Expect:** Approver lama hilang, approver baru muncul

### 2.4 Add Multiple Approvers (Max)
1. Coba pilih 11 approver
2. Klik **Save**
3. **Expect:**
   - Toast error / validation message
   - Config tidak berubah (masih 10 approver atau sebelumnya)

### 2.5 Remove All Approvers
1. Coba hapus semua approver dari suatu section
2. Klik **Save**
3. **Expect:** Validation error "At least one approver required"

---

## Skenario 3: Eligible Approvers Search

### 3.1 Search by Name
1. Buka approver dropdown
2. Ketik nama employee yang ada
3. **Expect:** Dropdown menampilkan hasil sesuai pencarian
4. Network: `GET /v1/hcm/approval-settings/eligible-approvers?q=<nama>` response 200

### 3.2 Search by Email
1. Ketik sebagian email
2. **Expect:** Hasil filter sesuai email

### 3.3 Search No Result
1. Ketik string acak "zzzzzzzz"
2. **Expect:** Dropdown kosong / "No results found"

### 3.4 Exclude Non-Active Employees
1. Pastikan ada employee dengan status `inactive` di perusahaan
2. Cari nama employee tersebut
3. **Expect:** Tidak muncul di hasil pencarian

---

## Skenario 4: Leave Integration

### 4.1 Submit Leave — Sequence Mode
1. Pastikan Leave Approval config: Sequence, 2 approvers
2. Login sebagai employee biasa
3. Submit leave request
4. **Expect:**
   - Request status: `pending`
   - Approver Level 1 mendapat notifikasi (in-app inbox)
   - Approver Level 1 bisa melihat request di daftar

### 4.2 Approve — Level 1
1. Login sebagai Approver Level 1
2. Buka leave request
3. Klik **Approve**
4. **Expect:**
   - Toast sukses
   - Approver Level 2 mendapat notifikasi `leave.approval.next_level`
   - Request status masih `pending`

### 4.3 Approve — Level 2 (Final)
1. Login sebagai Approver Level 2
2. Buka leave request
3. Klik **Approve**
4. **Expect:**
   - Request status: `approved`
   - Employee mendapat notifikasi `leave.approved`
   - `approved_by_user_id` terisi

### 4.4 Reject at Any Level
1. Submit leave baru
2. Login sebagai Approver Level 1
3. Klik **Reject**
4. **Expect:**
   - Request status: `declined`
   - Employee mendapat notifikasi `leave.rejected`
   - Tidak ada notifikasi ke level berikutnya

### 4.5 Simultaneous Mode
1. Set Leave Approval ke Simultaneous, 2 approvers
2. Submit leave baru
3. **Expect:** Kedua approver mendapat notifikasi bersamaan
4. Approver A approve → request langsung `approved`

### 4.6 No Config Fallback
1. Hapus semua config Leave Approval
2. Submit leave baru
3. **Expect:** Flow fallback ke admin notification (notif ke semua admin tenant, seperti sebelum Approval Settings)

---

## Skenario 5: Overtime Integration

### 5.1 Overtime Request with Config
1. Set Overtime Approval config (Simultaneous)
2. Login employee, submit overtime request
3. **Expect:**
   - Status: `pending`
   - Configured approver mendapat notif `overtime.approval.requested`
4. Approver approve → status `approved`

---

## Skenario 6: Error Handling

### 6.1 401 Unauthenticated
1. Buka API endpoint langsung (via Postman/curl tanpa token):
   ```
   GET /v1/hcm/approval-settings
   ```
2. **Expect:** 401

### 6.2 403 Non-Admin
1. Login sebagai employee biasa (bukan admin)
2. Buka `/approval-settings`
3. **Expect:**
   - Redirect ke halaman lain
   - API call dari halaman dapat 403

### 6.3 422 Invalid Module
1. Via API:
   ```
   PUT /v1/hcm/approval-settings/invalidmod
   ```
2. **Expect:** 422 `INVALID_MODULE`

### 6.4 422 Approver Not in Company
1. Coba set approver dengan user ID dari perusahaan lain
2. **Expect:** 422 `APPROVER_NOT_IN_COMPANY`

---

## Skenario 7: Multi-Tenant Isolation

### 7.1 Config Tidak Bisa Diakses Antar Perusahaan
1. Sebagai Admin Company A, buat config Leave
2. Login sebagai Admin Company B
3. Buka `/approval-settings`
4. **Expect:** Config Company A **tidak** terlihat (default/empty)

### 7.2 Approver Hanya dari Perusahaan Sendiri
1. Cari approver di Company A
2. User dari Company B **tidak** muncul di hasil pencarian

---

## Skenario 8: Destructive Confirmation

### 8.1 Hapus Approver dari Config
1. Config sudah ada approvers
2. Hapus satu approver (klik icon hapus)
3. **Expect:** Konfirmasi menggunakan `ArcavUi.confirmDelete` (modal template, **bukan** `alert`/`confirm` native)
4. Konfirmasi → approver dihapus, config tersimpan

---

## Regression Check

| No | Cek | Expected |
|---|---|---|
| 1 | Halaman tidak error JavaScript | ✅ |
| 2 | API response envelope `{ success, data?, error? }` | ✅ |
| 3 | Toast untuk sukses/error | ✅ |
| 4 | Button disabled saat submit (loading state) | ✅ |
| 5 | Tidak ada `window.alert` / `window.confirm` | ✅ |
| 6 | Token diambil dari `localStorage.arcav_access_token` | ✅ |
| 7 | Company ID dari `arcav_active_tenant.companyId` | ✅ |
| 8 | Select2 dropdown berfungsi | ✅ |
