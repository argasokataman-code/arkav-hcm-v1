# Planning: Redesign UI/UX SaaS Packages (`/saas/packages`)

**Author:** AI Planning  
**Date:** 2026-05-26  
**Status:** Draft — Analisis Selesai, Belum Diimplementasi

---

## RINGKASAN KEPUTUSAN (dari user)

| # | Keputusan |
|---|-----------|
| Q1 | Paket Unlimited tetap tampil di packages grid |
| Q2 | Tombol "Compare Selected" dihapus |
| Q3 | Enum `mvp`/`addon` di DB tidak diubah — hanya label display yang berubah menjadi "Core"/"Addon" |
| Q4 | N/A |
| Q5 | Addons selalu bisa diaktifkan/nonaktifkan per paket (via `package_features`) |
| MW | **Middleware tidak disentuh** — jangan ubah `EnsureCompanyFeatureForApi` maupun `EnsureCompanyFeatureForWebPage` |

---

## ANOMALI YANG HARUS DIWASPADAI (Wajib Baca Sebelum Implementasi)

### ⚠️ Anomali #1 — `inferFeatureCodesFromRouteUri` Hardcode `employee_lifecycle` untuk Terminations URI

**File:** `backend/app/Services/PackageFeatureCatalogRuntimeService.php` (~line 540)

```php
// MASALAH: blok ini masih ada meski route sudah ganti ke hcm.api.feature:termination
if (Str::startsWith($uri, 'v1/hcm/promotions')
    || Str::startsWith($uri, 'v1/hcm/resignations')
    || Str::startsWith($uri, 'v1/hcm/terminations')) {
    $codes[] = 'employee_lifecycle';   // ← ini menghasilkan false discovery
}
```

**Dampak:**
- `employee_lifecycle` otomatis muncul di catalog sebagai fitur yang "ditemukan via route"
- Padahal termination route sudah menggunakan `hcm.api.feature:termination`, bukan `employee_lifecycle`
- Healthcheck akan menunjukkan `employee_lifecycle` di route-discovered codes (salah)

**Fix diperlukan:** Hapus baris `$codes[] = 'employee_lifecycle'` dari blok ini. `employee_lifecycle` akan tetap ditemukan via route jika ada route lain yang masih memakai feature code itu (promotion/resignation masih pakai employee_lifecycle lewat route middleware, bukan URI mapping).

**Risiko jika tidak di-fix:** Catalog terus memunculkan `employee_lifecycle` sebagai fitur yang "di-gate oleh route termination" — membingungkan dan mempersulit UI baru.

---

### ⚠️ Anomali #2 — Sumber Kebenaran untuk Klasifikasi MVP/Core: BUKAN config, tapi MARKDOWN FILE

**Ini yang paling kritis untuk dipahami sebelum implementasi.**

Banyak yang mengira `saas_package_feature_catalog.php` adalah sumber kebenaran. **Itu keliru.**

Aliran sebenarnya:

```
PackageController@featureCatalog
    └── PackageFeatureCatalogRuntimeService->build()
            ├── discoverFeatureCodesFromRoutes()         ← scan seluruh middleware route
            ├── discoverFeatureCodesFromRuntimeDocs()    ← baca docs/features/RUNTIME-FEATURE-CLASSIFICATION.md
            └── discoverMvpFeatureCodesFromRuntimeDocs() ← baca section "## Kategori 2 - MVP Package"
                                                            dari file markdown yang sama
```

**`saas_package_feature_catalog.php` hanya dipakai untuk:**
- `addon_source` env config (bukan tier classification)
- Fallback `mvp_feature_codes` di `PackageController` jika `buildFeatureTierMapping()` butuh fallback
- Config lama yang tidak dipakai runtime service

**Konsekuensi untuk redesign:**
1. Jika kita backfill `feature_classifications` DB, DB override akan bekerja — karena service sudah support DB override (Anomali #3 di bawah)
2. JANGAN hanya ubah config file dan berharap runtime ikut berubah — tidak akan berubah
3. Untuk mengubah default classification, WAJIB salah satu: (a) ubah markdown file, atau (b) insert row ke `feature_classifications` table

---

### ⚠️ Anomali #3 — `feature_classifications` DB adalah OVERRIDE, BUKAN Replacement

Service melakukan:
```php
$dbOverrides = FeatureClassification::whereIn('feature_code', $allFeatureCodes)
    ->pluck('tier', 'feature_code')
    ->toArray();
// DB 'mvp' codes ditambahkan ke MVP
// DB 'addon' codes DIKELUARKAN dari MVP (override)
```

**Artinya:** DB hanya override fitur yang ada di-table. Fitur yang TIDAK ada di table → masih ikut klasifikasi dari markdown.

**Untuk Phase 1 (backfill):** kita insert semua 26 fitur ke `feature_classifications`.
Setelah backfill:
- DB menjadi **de facto** sumber kebenaran (karena semua fitur sudah punya row di DB)
- Markdown tetap dibaca tapi DB override akan override semuanya
- Ini safe karena kita backfill SESUAI dengan apa yang markdown saat ini klasifikasikan

**Risiko backfill:** Jika ada fitur yang ditemukan via route tapi belum di-backfill (misal fitur baru), fitur itu akan jatuh ke addon karena tidak ada di `mvp_feature_codes` dari DB. Perlu dibuat mekanisme "detect feature codes not in classifications table" di UI.

---

### ⚠️ Anomali #4 — API Response Shape adalah KONTRAK — Jangan Diubah

Frontend `packages-management` tightly coupled ke:

```json
GET /v1/saas/packages/feature-catalog
→ {
    "success": true,
    "data": [{ "module": "...", "features": [...] }],  ← sharedState.featureLibrary
    "meta": {
        "mvp_feature_codes": [...],      ← dipakai isAddonFeatureCode()
        "addon_feature_codes": [...],
        "total_feature_codes": N,
        "addon_source": "db"|"runtime",
        "feature_classification_overrides": {...}  ← sharedState.featureClassificationOverrides
    }
}
```

Jika shape ini berubah, **seluruh packages-management UI akan break.**

**Rule:** Untuk Phase 1-4, shape ini tidak boleh berubah. Jika perlu menambahkan data, TAMBAH field baru di `meta` — jangan ubah atau hapus field yang sudah ada.

---

### ⚠️ Anomali #5 — Compliance Checker Masih Berguna, Jangan Dihapus

`POST /v1/saas/packages/feature-catalog/compliance-check` dan logika di `compliance.js` memvalidasi dependency antar fitur (misalnya: payroll butuh bpjs_governance, termination butuh employee_management). 

Tombol "Compare Selected" memang dihapus (Q2), tapi logika **compliance check saat feature selection berubah** harus tetap jalan. Compliance check adalah guard yang mencegah konfigurasi paket yang invalid.

**Rule:** Hapus tombol "Compare Selected" di header, tapi JANGAN hapus compliance check yang auto-run saat user toggle fitur di form Add/Edit Package.

---

### ⚠️ Anomali #6 — `saas_package_feature_catalog.php` yang Sudah Diubah (promotion/resignation/termination)

Di sesi kemarin, saya menambahkan `termination`, `promotion`, `resignation` ke config ini. Tapi karena config ini TIDAK dipakai oleh runtime service (Anomali #2), perubahan itu tidak mempengaruhi catalog API.

**Status:** Perubahan config tidak berbahaya, tapi juga tidak efektif untuk runtime. Tidak perlu di-rollback, tapi perlu disadari bahwa untuk melihat efek di runtime, harus lewat `feature_classifications` DB atau markdown file.

---

## 1. Problem Statement

### 1.1 Pain Points Saat Ini

Halaman `/saas/packages` terlalu berbelit-belit karena terlalu banyak surface yang terpisah:

| Masalah | Detail |
|---------|--------|
| **4 tombol header tidak terhubung** | "List All Features" + "Manage Classifications" + "Compare Selected" + "Add Package" — semuanya buka modal/overlay berbeda, tanpa alur yang jelas |
| **"Manage Classifications" underused** | Tabel DB `feature_classifications` hampir kosong (hanya 1 row), padahal ini harusnya jadi sumber kebenaran |
| **Picker fitur terlalu kompleks** | Accordion 7 grup × 26 fitur di dalam modal Add/Edit Package — tidak ada pembeda mana "core" mana "addon" |
| **Dua tabel terpisah** | "Packages List" di atas + "Package Add-ons List" di bawah — membingungkan, tidak jelas relasinya |
| **Feature catalog config vs DB** | Sumber data fitur terbagi dua: `saas_package_feature_catalog.php` (hardcode) + `feature_classifications` (DB override) — confusing |

### 1.2 Kondisi Data Saat Ini

- **26 feature codes** terdaftar di catalog config, dikelompokkan dalam 7 modul
- **`feature_classifications` table**: hampir kosong, tier `mvp` / `addon`
- **`package_features` table**: data aktif yang menentukan hak akses tenant
- **8 packages**: starter, growth, business, enterprise, ultimate, trial, umkm, unlimited

---

## 2. Visi Redesign: Classification-First

### 2.1 Konsep Inti

```
Global Feature Catalog
        ↓
  Klasifikasi: CORE vs ADDON
        ↓
  Definisi Paket:
  - Pilih nama paket
  - Pilih fitur CORE mana yang aktif di paket ini
  - Sisa fitur CORE yg tidak dipilih → available as upgrade
  - Semua ADDON → bisa dibeli terpisah
        ↓
  Tampilan Paket:
  - Card/Row yang jelas: "Paket X mencakup A, B, C. Addon tersedia: D, E, F"
```

### 2.2 Contoh Konkret (sesuai permintaan user)

**Buat Paket UMKM:**
1. Nama: UMKM | Code: umkm
2. Fitur Core yang diaktifkan: `employee_management`, `payroll`, `attendance`
3. Sistem otomatis tahu: `leave_management`, `holiday_calendar`, `notifications` (core lainnya) → tidak termasuk
4. Addon yang tersedia: `termination`, `resignation`, `promotion`, `training`, dll.
5. Tampilan list paket: **"UMKM — Core: Employee, Payroll, Attendance | Addons: 8 tersedia"**

---

## 3. Arsitektur Baru

### 3.1 Single Source of Truth: DB `feature_classifications`

Ubah paradigma: catalog config **hanya untuk display metadata** (nama, deskripsi, module group). Klasifikasi tier **sepenuhnya dari DB**.

```
saas_package_feature_catalog.php  →  Display metadata saja
                                      (nama, deskripsi, grouping modul)

feature_classifications (DB)       →  Sumber SATU-SATUNYA untuk tier
                                      core | addon
```

#### Schema `feature_classifications` (minimal change):

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint | PK |
| `feature_code` | varchar | unique |
| `tier` | enum(`core`, `addon`) | ganti dari `mvp`/`addon` |
| `display_name` | varchar nullable | override nama tampilan |
| `module_group` | varchar nullable | grouping di UI |
| `created_at`, `updated_at` | timestamp | |

> Catatan: rename `mvp` → `core` cukup semantik, bisa dengan migration update enum atau tetap pakai `mvp` string namun ditampilkan sebagai "Core".

#### Backfill dari config:

Migration baru akan populate `feature_classifications` dari `saas_package_feature_catalog.php.mvp_feature_codes`:
- `max_employees`, `employee_management`, `attendance`, `leave_management`, `holiday_calendar`, `payroll`, `payroll_components`, `payroll_thr`, `notifications`, `trial_billing_dashboard`, `tax_governance`, `bpjs_governance` → tier `core`
- Semua lainnya (termination, promotion, resignation, training, dll.) → tier `addon`

### 3.2 Layout Halaman Baru

```
/saas/packages
│
├── [Header] "SaaS Package Management"
│   └── Tombol: [+ Buat Paket Baru]   [⚙ Kelola Klasifikasi Fitur]
│
├── [Summary Bar]
│   └── Total Paket: 8 | Active Subscriptions: X | Most Popular: Business
│
└── [Package Cards/Grid]
    ├── Card: Starter
    │   ├── Core Features: [Employee] [Attendance] [Leave] [Payroll]
    │   ├── Addons Tersedia: 12
    │   └── Actions: [Edit] [Lihat Subscriptions] [Archive]
    ├── Card: UMKM
    │   ├── Core Features: [Employee] [Payroll] [Attendance]
    │   ├── Addons Tersedia: 8
    │   └── Actions: [Edit] [Lihat Subscriptions] [Archive]
    └── ...
```

### 3.3 Alur "Kelola Klasifikasi Fitur" (halaman/drawer sendiri)

Bukan modal lagi — dedicated section atau slide-over panel:

```
Kelola Klasifikasi Fitur
│
├── Search/filter feature
├── [Backfill dari Config] button (sekali pakai, jika classifications kosong)
│
└── Tabel:
    ┌──────────────────────┬──────────┬────────┬────────────┐
    │ Feature              │ Module   │ Tier   │ Aksi       │
    ├──────────────────────┼──────────┼────────┼────────────┤
    │ Employee Management  │ Employee │ [Core] │ Toggle     │
    │ Attendance           │ Attend.  │ [Core] │ Toggle     │
    │ Termination          │ Employee │ [Addon]│ Toggle     │
    │ Training             │ Training │ [Addon]│ Toggle     │
    └──────────────────────┴──────────┴────────┴────────────┘
```

### 3.4 Alur "Buat/Edit Paket" (form yang lebih clean)

Ganti accordion kompleks dengan form 2-panel side-by-side:

```
┌─────────────────────────────────────┬────────────────────────────────────┐
│ INFORMASI PAKET                     │ FITUR PAKET                        │
│                                     │                                    │
│ Nama: [____________]               │ CORE FEATURES (pilih yang aktif)   │
│ Code: [____________]               │ ☑ Employee Management              │
│ Deskripsi: [_______]               │ ☑ Payroll                          │
│ Harga: [___________]               │ ☐ Attendance                       │
│ Status: [Active▼]                  │ ☐ Leave Management                 │
│                                     │ ☑ Holiday Calendar                 │
│                                     │                                    │
│                                     │ ADDON FEATURES (opsional, centang  │
│                                     │  jika paket ini bisa unlock addon) │
│                                     │ ☐ Termination Management          │
│                                     │ ☐ Training                        │
│                                     │ ☐ Asset Management                │
└─────────────────────────────────────┴────────────────────────────────────┘
                                               [Batal]  [Simpan Paket]
```

---

## 4. Perubahan Backend

### 4.1 API Baru / Modified

| Endpoint | Method | Perubahan |
|----------|--------|-----------|
| `GET /v1/saas/packages/feature-catalog` | GET | Tambah field `tier`, `module_group` dari DB classifications |
| `GET /v1/saas/feature-classifications` | GET | Sudah ada — tetap, tambah `module_group` di response |
| `POST /v1/saas/feature-classifications/backfill` | POST | **BARU** — backfill semua feature code dari config ke DB |
| `GET /v1/saas/packages` | GET | Tambah `core_features` dan `addon_features` di response per paket |

### 4.2 `PackageFeatureCatalogRuntimeService` Enhancement

- Method `build()`: wajib query `feature_classifications` terlebih dahulu, fallback ke config jika DB kosong
- Tambah method `classificationMap(): array` — return `[feature_code => 'core'|'addon']`

### 4.3 Migration: Backfill `feature_classifications`

```
2026_05_28_000200_backfill_feature_classifications_from_catalog_config.php
```

- Insert semua 26 feature codes ke `feature_classifications` jika belum ada
- `mvp_feature_codes` dari config → tier `core`
- Sisanya → tier `addon`

---

## 5. Perubahan Frontend

### 5.1 File yang Berubah / Baru

| File | Status | Keterangan |
|------|--------|------------|
| `backend/resources/views/saas/packages.blade.php` | **Modifikasi besar** | Layout baru: summary bar + grid cards |
| `frontend/resources/js/packages-management/modules/features/classifications.js` | **Refactor** | Dari modal → panel; tambah backfill button; tampilkan module group |
| `frontend/resources/js/packages-management/modules/features/catalog-ui.js` | **Refactor** | Dari accordion kompleks → 2-panel split (core vs addon) |
| `frontend/resources/js/packages-management/modules/data.js` | **Modifikasi** | Fetch & cache classification map dari DB |
| `frontend/resources/js/packages-management/modules/modals.js` | **Modifikasi** | Gunakan catalog-ui versi baru |
| `frontend/resources/js/packages-management/modules/bootstrap.js` | **Modifikasi ringan** | Wire up tombol baru |

### 5.2 File yang Dihapus (setelah refactor selesai)

- Tidak ada file yang dihapus dulu — refactor bertahap

### 5.3 Komponen Card Paket (Baru)

Setiap paket ditampilkan sebagai card dengan:
- **Badge warna** per fitur core (grouped by module — employee=blue, attendance=green, payroll=purple, dll.)
- **Counter** untuk addons tersedia: "12 addons tersedia"
- **Status indicator**: Active / Inactive / Archived
- **Tooltip** on hover badge: nama lengkap fitur

---

## 6. Impact Analysis

### In Scope — File yang BOLEH Disentuh
- `backend/resources/views/saas/packages.blade.php`
- `frontend/resources/js/packages-management/` (seluruh folder)
- `backend/app/Http/Controllers/Api/Saas/PackageController.php` — method `index` (tambah field di response), `featureCatalog` (tambah field di `meta` saja, JANGAN ubah shape yang ada)
- `backend/app/Http/Controllers/Api/Saas/FeatureClassificationController.php` — tambah endpoint `backfill`
- `backend/app/Services/PackageFeatureCatalogRuntimeService.php` — **SATU FIX HANYA**: hapus `employee_lifecycle` hardcode untuk terminations URI (Anomali #1)
- `backend/database/migrations/` — 1 migration backfill
- `backend/config/saas_package_feature_catalog.php` — **TIDAK DISENTUH** (tidak berpengaruh ke runtime)

### NOT In Scope — Jangan Disentuh
- Middleware: `EnsureCompanyFeatureForApi`, `EnsureCompanyFeatureForWebPage`
- `package_features` table schema (tidak berubah)
- Semua route HCM tenant (`/v1/hcm/*`)
- `feature_classifications` schema (tidak berubah — hanya data yang di-backfill)
- `FeatureClassification` model
- Subscription flow dan billing
- `PackageController@featureCatalog` response shape (`data` array of groups + `meta` keys tetap sama)
- `docs/features/RUNTIME-FEATURE-CLASSIFICATION.md` (tidak perlu diubah untuk redesign ini)

### Downstream Effects
- Setelah backfill `feature_classifications`, DB menjadi de-facto primary source untuk tier
- Markdown file tetap dibaca tapi DB override akan menang karena semua code sudah ada di DB
- Package cards baru bergantung pada `featureCatalog` API yang sudah include tier di `meta`

---

## 7. Implementation Phases

### Phase 1 — Foundation (Service Fix + DB Backfill + API Enhancement)
**Goal**: Satu sumber kebenaran (DB), fix anomali #1, enhancement API tanpa breaking change

- [ ] **Service fix**: Hapus baris `$codes[] = 'employee_lifecycle'` dari `inferFeatureCodesFromRouteUri` untuk URI terminations/promotions/resignations (Anomali #1) — hanya hapus false mapping, bukan menghapus feature code dari catalog
- [ ] **Migration**: Backfill semua feature codes ke `feature_classifications` dari markdown `## Kategori 2` (mvp) dan sisanya (addon) — insert ignore jika sudah ada
- [ ] **API**: `PackageController@index` — tambah `meta.tier_by_code` map `{feature_code: 'mvp'|'addon'}` di response packages (pakai data DB, TIDAK ubah existing fields)
- [ ] **API**: `FeatureClassificationController` — tambah `POST /backfill` untuk trigger backfill dari UI jika DB kosong

### Phase 2 — Classification Manager Upgrade
**Goal**: "Kelola Klasifikasi Fitur" jadi proper, bukan modal mini

- [ ] Blade: ganti modal classifications → slide-over panel / dedicated section
- [ ] JS: `classifications.js` — tampilkan semua feature dengan toggle Core/Addon, group by module
- [ ] JS: tambah "Backfill dari Config" button (jika kosong)
- [ ] JS: search/filter by feature name atau module

### Phase 3 — Package Cards Redesign
**Goal**: Packages list menjadi visual cards yang informatif

- [ ] Blade: ganti dua tabel lama → grid cards
- [ ] JS: render card per paket dengan:
  - Core feature badges (grouped by module, color-coded)
  - Addon count
  - Summary metrics (active subscriptions jika tersedia)
- [ ] Hapus "Package Add-ons List" tabel terpisah (merge ke card)

### Phase 4 — Package Form Redesign
**Goal**: Create/Edit form lebih intuitif dengan pemisahan core vs addon yang jelas

- [ ] JS: `catalog-ui.js` — ganti accordion → 2-panel split
  - Panel kiri: informasi dasar paket
  - Panel kanan: Core Features (checklist) + Addon Features (checklist)
- [ ] Core features diwarnai badge modul (bukan sekedar teks)
- [ ] Hapus "Compare Selected" dan "List All Features" tombol header (obsolete)

---

## 8. UI/UX Principles

1. **One Source of Truth**: Manage Classifications = define what's core, what's addon. Package form just picks from that.
2. **Progressive Disclosure**: Tampilkan core features first, addons second. User tidak perlu scroll 26 item sekaligus.
3. **Visual Grouping**: Badges warna per module (bukan plain text) — Employee=biru, Attendance=hijau, Payroll=ungu, Leave=cyan, Performance=orange
4. **No Hidden Modals**: Classification manager tidak boleh modal. Pakai slide-over atau section in-page yang tidak memblok seluruh view.
5. **Context Clarity**: Setiap paket card langsung terlihat "paket ini isinya apa" tanpa harus klik Edit.

---

## 9. Open Questions ~~(Perlu Konfirmasi)~~ — CLOSED

| # | Pertanyaan | Keputusan |
|---|-----------|-----------|
| Q1 | Apakah paket "Unlimited" ditampilkan di grid? | ✅ **Tampilkan saja**, tidak disembunyikan |
| Q2 | "Compare Selected" dipertahankan atau dihapus? | ✅ **Hapus** — tombol + JS compliance.js |
| Q3 | Rename enum `mvp`→`core` di DB? | ✅ **Tidak rename** — pertahankan `mvp`/`addon` di DB, tampilkan sebagai "Core"/"Addon" di UI saja |
| Q4 | Card vs table layout? | ✅ **Card layout** |
| Q5 | Addon bisa di-active/nonactive per paket? | ✅ **Sudah pasti bisa** — dikelola via `package_features` table yang sudah ada. Middleware tidak disentuh. |

**Constraints tambahan:**
- Tidak menyentuh middleware sama sekali (`EnsureCompanyFeatureForApi`, `EnsureCompanyFeatureForWebPage`)
- Tidak menyentuh route HCM tenant
- Klasifikasi fitur dikelola manual oleh admin via UI (tidak auto-assign)

---

## 10. Estimasi Kompleksitas

| Phase | Kompleksitas | File Utama | Risiko |
|-------|-------------|------------|--------|
| Phase 1 (DB + API) | Rendah | 2 controller + 1 service + 1 migration | Tidak ada breaking change |
| Phase 2 (Classification Manager) | Sedang | 1 blade + 1 JS | Refactor JS existing |
| Phase 3 (Package Cards) | Sedang-Tinggi | 1 blade + JS data.js | Perubahan visual besar |
| Phase 4 (Package Form) | Tinggi | catalog-ui.js | Logic feature selection cukup kompleks |

**Rekomendasi urutan**: Phase 1 → Phase 2 → Phase 3 → Phase 4  
**Phase 1 + 2 bisa dikerjakan dalam satu session** (mostly backend + UI manager)  
**Phase 3 + 4 session terpisah** (UI overhaul besar)
