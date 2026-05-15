# Audit: Halaman SaaS Subscriptions + Pending Payment + Renew Flow

**Tanggal:** 2026-05-15  
**Auditor:** Code Analysis  
**Scope:**  
- `backend/resources/views/saas/subscriptions.blade.php` (UI)  
- `backend/public/build/js/saas/subscriptions-management.js` (JS logic, 1465 baris)  
- `backend/app/Http/Controllers/Api/Saas/SubscriptionController.php` (API, 604 baris)  
- `backend/app/Models/Subscription.php`  
- `docs/features/subscriptions/README.md`, `IMPLEMENTATION.md`, `SCENARIOS.md`  
- Cross-reference: `docs/features/auto-renewal/`, `docs/api/saas-renewal-monitoring-api.md`  
- Referensi: `docs/api/saas-subscriptions-api.md`

---

## Ringkasan

Halaman `/saas/subscriptions` memiliki 3 masalah utama yang dilaporkan user dan 7 masalah tambahan. Prioritas P0–P1 harus diperbaiki sebelum rilis berikutnya.

---

## Masalah Utama (User Report)

### 🔴 P0: Tabel List Tidak Menampilkan Subscription ID — User Bingung Copas ID untuk "Renew by ID"

**Lokasi:** `subscriptions-management.js:705-811` (fungsi `renderSubscriptions`)

**Aktual:** Kolom tabel:
| Company | Package | Status | Start Date | End Date | Auto Renew | Actions |

**Tidak ada kolom ID.** Namun di toolbar ada tombol **"Renew by ID"** yang membuka modal dengan field:
> "Masukkan Subscription ID / Reference"  
> placeholder: "e.g. 42 atau SUB-10042"

**Ironi:**  
- User disuruh masukkan ID tapi ID tidak ditampilkan di manapun di tabel.  
- Yang tampil di baris hanya `data-subscription-row="${subscriptionRouteKey(sub)}"` sebagai atribut HTML (tidak visible).  
- User harus inspect element atau buka modal satu per satu untuk lihat ID.  

**Akar masalah:**  
1. `renderSubscriptions()` tidak menambahkan kolom ID.  
2. `subscriptionRouteKey()` di `utils.js` memformat key (UUID atau ID) tapi hanya dipakai sebagai data attribute.  
3. Tidak ada tooltip/copy button untuk salin ID.  

**Seharusnya:**  
- Tambah kolom **"ID"** sebagai kolom pertama di tabel (atau minimal ID number saja).  
- Atau tambahkan badge kecil `#ID: 42` di samping nama company.  
- Atau tambahkan tombol "Copy ID" di setiap baris.  

**Dampak:** User tidak bisa menggunakan fitur "Renew by ID" karena tidak tahu ID subscription yang mana.

---

### 🔴 P0: Flow Renew Tidak Terintegrasi ke Renewal Monitoring

**Lokasi:**  
- `SubscriptionController.php:349-419` (method `renew`)  
- `docs/features/subscriptions/README.md:91` (out of scope: renewal notification automation)  

**Aktual:**  
Method `renew()` hanya melakukan:
1. Validasi status (tidak boleh `pending_payment`)
2. Update `status=active`, `starts_at=now()`, `ends_at={input}`, hapus grace/suspended
3. `CompanyStatusSynchronizer::syncFromSubscription()`
4. Notifikasi reactivation (jika dari suspended)
5. ✅ Sudah emit `SubscriptionEvent` jika reactivasi dari suspended (`SUBSCRIPTION_REACTIVATED_MANUAL_RENEW`)

**Tapi TIDAK melakukan:**
- ❌ Tidak membuat invoice renewal (seharusnya auto-generate invoice baru untuk periode baru)
- ❌ Tidak mencatat `renewal_period_key` di invoice (hubungan ke renewal monitoring)
- ❌ Tidak menambahkan timeline event `renewal` dengan reason code ke `SubscriptionEvent` (kecuali untuk reactivation dari suspended)
- ❌ Tidak sinkron dengan `auto-renewal` scheduler (renew manual bypass auto-renewal logic)
- ❌ Tidak ada entry di `docs/api/saas-renewal-monitoring-api.md` untuk tracking manual renew

**Seharusnya:**  
Flow renew manual (admin) harus minimal:
1. Generate invoice renewal untuk periode baru  
2. Catat `renewal_period_key` dan `renewal_reason_code` di invoice  
3. Tambah timeline event `ADMIN_MANUAL_RENEW` di `SubscriptionEvent`  
4. Agar muncul di `/saas/renewal-monitoring` sebagai record renewal  

**Dampak:** Renew manual admin tidak tercatat di renewal monitoring, tidak ada trail audit yang konsisten.

---

### 🟠 P1: Flow E2E "Pending Payment" Tidak Jelas — User Tidak Paham UX

**Lokasi:**  
- `subscriptions.blade.php:30-33` (tombol "Pending Payment")  
- `subscriptions.blade.php:143-153` (modal help text yang panjang dan ambigu)  

**Aktual:**  
1. Tombol "Pending Payment" (`#btn_add_pending_subscription`) langsung buka modal Add Subscription dengan status default `pending_payment`.  
2. Help text di modal:  
   > "Pending payment: hubungkan invoice ke subscription ini lalu tandai bayar — status jadi Active dan periode dihitung dari tanggal bayar."  

**Masalah:**  
- Help text teknis dan tidak menjelaskan flow langkah demi langkah ke user.  
- Tidak ada panduan visual tentang **bagaimana** menghubungkan invoice ke subscription.  
- Tidak ada link ke halaman invoice atau purchase transaction.  
- User tidak tahu: Setelah subscription dibuat dengan status `pending_payment`, apa langkah selanjutnya?  

**Flow aktual yang terjadi (dari kode):**  
1. Admin klik "Pending Payment" → modal Add Subscription dengan status `pending_payment`  
2. Admin submit → subscription terdaftar sebagai `pending_payment`  
3. ✅ Aktivasi otomatis via `InvoicePaidActivatesSubscriptionTest.php` — ketika invoice dengan `subscription_id` di-mark paid, subscription berubah jadi `active`  

**Tapi dari UI:**  
4. ❌ Tidak ada petunjuk untuk membuat invoice untuk subscription ini  
5. ❌ Tidak ada tombol "Buat Invoice" atau "Hubungkan ke Invoice"  
6. ❌ Tidak ada status progress: "Invoice belum dibuat" → "Invoice sudah dibuat, menunggu pembayaran" → "Sudah aktif"  

**Seharusnya:**  
- Tambah panduan step-by-step di modal atau buat mini wizard:  
  > Langkah 1: Buat subscription (selesai)  
  > Langkah 2: Buat invoice untuk subscription ini → [Buka halaman Invoice]  
  > Langkah 3: Tandai invoice sebagai paid → subscription otomatis aktif  
- Atau: Setelah subscription `pending_payment` dibuat, tampilkan toast dengan link "Buat invoice sekarang"  

**Dampak:** User tidak mengerti flow E2E pending payment → active. Fitur jadi misterius.

---

## Masalah Tambahan

### 🟠 P1: Tabel Tidak Menampilkan Info Invoice / Amount

**Lokasi:** `subscriptions-management.js:705-811`

**Aktual:** Kolom: Company, Package, Status, Start Date, End Date, Auto Renew, Actions.  
Tidak ada kolom Amount, Invoice Number, atau Last Payment.

**Dampak:** Admin tidak bisa lihat berapa tagihan per subscription tanpa klik detail.

---

### 🟠 P1: Fungsi `isRenewableSubscriptionStatus()` Bernama Terbalik

**Lokasi:** `subscriptions-management.js:36`

**Aktual:**
```javascript
const isRenewableSubscriptionStatus = subscriptionsUtils.isRenewableSubscriptionStatus || function (status) {
    return String(status || "") !== "active";
};
```
Logika: `return status !== "active"` → **SEMUA status kecuali "active" dianggap renewable**.  
Ini termasuk `trial`, `pending_payment`, `inactive`, `expired`, `cancelled`, `suspended`.

**Tapi di** `SubscriptionController.php:362-369`:
```php
if ($subscription->status === 'pending_payment') {
    return error('SUBSCRIPTION_INVALID_STATE', 'Cannot renew a subscription that is still awaiting payment.', 422);
}
```
Dan di `openRenewModal()` JS baris 1198:
```javascript
if (!isRenewableSubscriptionStatus(sub.status)) {
    this.showError("Renew is only available for expired, cancelled, suspended, or inactive subscriptions.");
    return;
}
```

**Masalah:** Nama fungsi `isRenewableSubscriptionStatus` misleading karena mengembalikan `true` untuk status `trial`, `pending_payment` yang sebenarnya tidak bisa di-renew. Validasi tambahan di controller dan JS justru membantah logika fungsi ini.

---

### 🟡 P2: View Details Pakai Native `alert()` style (Bukan Modal)

**Lokasi:** `subscriptions-management.js:1146-1178`

**Aktual:**
```javascript
viewSubscriptionDetails: function (id) {
    // ...
    const text = "Company: ...\nPackage: ...\nStatus: ...\nStart Date: ...";
    if (window.ArcavUi && typeof window.ArcavUi.showInfo === "function") {
        window.ArcavUi.showInfo("Subscription Details", text);
        return;
    }
    self.showToast(text.replace(/\n/g, "<br>"), "info");
}
```

Detail tampil sebagai plain text dengan `\n` separator. Tidak ada modal Bootstrap seperti pola detail modul HCM lain (resignation, termination, promotion detail — semuanya pakai Bootstrap modal dengan layout proper).

---

### 🟡 P2: Tidak Ada Loading Spinner Saat Fetch Data

**Lokasi:** `subscriptions-management.js:662-692`

**Aktual:** Cuma text "Loading subscriptions..." di container. Tidak ada spinner/overlay.

---

### 🟡 P2: Tombol "Pending Payment" Selalu Terlihat untuk Admin — Tapi Tidak Selalu Relevan

**Lokasi:** `subscriptions.blade.php:30-33`

Tombol "Pending Payment" visible untuk semua HCM admin. Tapi kalau company sudah punya subscription active, membuat `pending_payment` baru tidak akan dicegah oleh UI (backend validasi `ACTIVE_SUBSCRIPTION_ALREADY_EXISTS` hanya untuk `active`/`trial`, bukan `pending_payment`). Ini bisa membingungkan.

---

### 🟢 P3: Label Badge `pending_payment` vs `Pending payment`

**Lokasi:** `subscriptions-management.js:727-728`

**Aktual:**
```javascript
: sub.status === "pending_payment"
    ? "badge-warning"
```
Label badge: `pending_payment` (snake_case mentah di database).

**Seharusnya:** Label dibersihkan jadi "Pending payment" dengan fungsi `normalizeLabel()`.

---

### 🟢 P3: Breadcrumb Tidak Konsisten

**Lokasi:** `subscriptions.blade.php:16-20`

**Aktual:** Breadcrumb hanya: SaaS > Subscriptions. Tidak ada link ke renewal monitoring atau pages terkait.

---

### 🟢 P3: Tidak Ada Tes PHPUnit untuk Renew Endpoint Secara Spesifik

Dari `SaasSubscriptionsAdminOnlyTest.php`, ada test admin-only generic. Tapi tidak ada test spesifik untuk:
- Renew dari berbagai status (suspended, expired, cancelled, inactive, trial, pending_payment)
- Validasi ends_at
- Event audit trail setelah renew
- Company status sync setelah renew

---

## Prioritas Rekomendasi

| Priority | Item | File(s) | Impact |
|----------|------|---------|--------|
| **P0** | #1 — Tambah kolom ID di tabel | JS + Blade | User tidak bisa pakai Renew by ID |
| **P0** | #2 — Integrasi renew → renewal monitoring | Controller + Event | Renew tidak tercatat di monitoring |
| **P1** | #3 — Flow pending payment E2E tidak jelas | Blade + Docs | User bingung flow |
| **P1** | #4 — Tambah kolom Invoice/Amount | JS | Informasi billing tersembunyi |
| **P1** | #5 — Fix `isRenewableSubscriptionStatus` | JS | Nama fungsi misleading |
| **P2** | #6 — Detail pakai modal Bootstrap | JS + Blade | Inkonsistensi UX pattern |
| **P2** | #7 — Loading spinner | JS | UX minor |
| **P2** | #8 — Prevent pending payment jika active exist | JS | UX clarity |
| **P3** | #9 — Label badge proper | JS | Kosmetik |
| **P3** | #10 — Test coverage renew | PHPUnit | Risiko regresi |

---

## Files to Modify (Jika Fix)

1. `backend/public/build/js/saas/subscriptions-management.js` — tambah kolom ID, fix `isRenewableSubscriptionStatus`, perbaiki view details, label badge
2. `backend/resources/views/saas/subscriptions.blade.php` — tambah petunjuk pending payment flow
3. `backend/app/Http/Controllers/Api/Saas/SubscriptionController.php` — tambah audit trail + renewal_period_key saat renew manual
4. `docs/features/subscriptions/README.md` — update flow E2E pending payment
5. `docs/features/subscriptions/SCENARIOS.md` — tambah scenario pending payment + renew integration
6. `docs/api/saas-renewal-monitoring-api.md` — catat bahwa manual renew juga masuk monitoring
7. Test files — tambah test untuk renew integration

---

## Analisis Dampak Lintas Fitur

| Perubahan | Fitur Terdampak | Risiko |
|-----------|----------------|--------|
| Tambah audit trail di `renew()` | Renewal Monitoring (muncul di timeline) | Rendah — hanya INSERT event |
| Invoice generation di `renew()` | Auto-renewal, Payment Gateway | **Sedang** — jangan generate invoice ganda |
| Kolom ID baru di tabel | Tidak ada | Rendah — hanya visual |
| Fix `isRenewableSubscriptionStatus` | Tidak ada | Rendah — fungsi cuma dipakai 2x |