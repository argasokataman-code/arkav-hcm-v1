# Purchase Transaction

## Ringkasan

Feature ini menjadi ledger billing operasional untuk admin SaaS, baik dari halaman aktif `/saas/transactions` maupun alias `/purchase-transaction`. Runtime saat ini masih menampung dua bentuk contract pada path API yang sama, sehingga dokumentasi harus tegas membedakan ledger legacy untuk blade aktif dan contract purchase transaction yang lebih kaya untuk consumer bearer-token.

## Akses

- Global HCM Admin / SaaS Admin: full access ke halaman transaksi dan endpoint `/v1/saas/transactions*`.
- Non-admin dan employee biasa: tidak boleh membuka surface admin billing atau memutasi transaksi.
- Company user: memakai surface invoice company sendiri, bukan halaman transaksi admin.

## UI Aktif

- Halaman aktif: `/saas/transactions` dan alias `/purchase-transaction`.
- Blade aktif: `backend/resources/views/saas/transactions.blade.php`.
- JS manager aktif: `frontend/resources/js/purchase-transactions-data.js`.

## Flow Bisnis End-to-End

1. Admin membuka `/saas/transactions` atau alias `/purchase-transaction`.
2. Halaman memuat ledger transaksi billing dan filter aktif.
3. Admin dapat membuka detail transaksi, export ledger, atau melakukan create/update via API sesuai contract consumer.
4. Data transaksi kemudian dipakai modul billing lain seperti invoice, payment, subscription, dan reporting.

## Lifecycle Dan Keputusan Bisnis

- Runtime split contract dipertahankan sementara demi kompatibilitas halaman aktif dan consumer API bearer.
- Integrity guard: create transaction harus menolak subscription-company mismatch.
- Admin-only: seluruh create/update/list/detail billing ledger harus tetap dibatasi ke admin global.

## Integrasi

- Subscriptions: transaction dapat mereferensikan subscription tenant dan status billing subscription. Lihat `docs/features/subscriptions/README.md`.
- Reporting: transaksi menjadi input laporan revenue, aging, dan churn pada stack reporting/billing. Lihat `docs/features/reporting/README.md`.
- Trial & Billing Dashboard: dashboard company list dan invoice detail membaca status invoice/payment yang bersinggungan dengan transaksi. Lihat `docs/features/trial-billing-dashboard/README.md`.
- Export Reconciliation: action finansial sensitif di billing dapat digate oleh export reconciliation sesuai rollout. Lihat `docs/features/export-reconciliation/README.md`.
- Invoice render context (terintegrasi):
  - identitas issuer global membaca Business Settings (`business_*`) via `WebsiteSettings`;
  - data `Bill To` membaca Company Profile tenant (`company_profile_*`);
  - terms/policy invoice membaca Invoice Settings tenant (`invoice_*`) untuk PDF output.
- Peta integrasi lengkap: `docs/features/INTEGRATION-MAP.md`.

## Kontrak API

## Documentation Structure

- [README.md](README.md) — business overview, runtime split, role check, existing vs target, dan gap transparency
- [IMPLEMENTATION.md](IMPLEMENTATION.md) — technical summary untuk billing surfaces yang terkait (transactions, invoices, payments, reminders, reports)
- [E2E-TESTING.md](E2E-TESTING.md) — skenario manual UI E2E admin/company
- [tracker.md](tracker.md) — snapshot status terbaru, evidence, dan open gaps

## Existing Vs Target

- Existing: route web admin sudah diproteksi, FE menampilkan backend error aktual, UUID compatibility untuk create/filter sudah ditangani, dan runtime split contract ditulis eksplisit.
- Existing: blade aktif tetap cocok dengan shape ledger legacy yang dipakai frontend sekarang.
- Existing: PDF invoice sekarang memakai context terintegrasi (Business Settings + Company Profile + Invoice Settings) sehingga branding/terms tidak lagi terpecah antar sumber.
- Target: unifikasi penuh antara legacy ledger contract dan purchase transaction contract masih pekerjaan lanjutan.

## Ringkasan Bisnis

Feature **Purchase Transaction** menjadi ledger billing operasional untuk admin SaaS. Tujuannya ada dua:

1. memberi admin daftar transaksi billing yang bisa difilter cepat dari halaman aktif `/saas/transactions` atau alias `/purchase-transaction`;
2. menyediakan contract API purchase transaction yang lebih kaya untuk create/update ledger billing dengan relasi company, subscription, dan add-on.

Saat ini runtime belum sepenuhnya memakai satu contract tunggal. Path API yang sama, `/v1/saas/transactions`, melayani dua bentuk payload berbeda bergantung flow konsumennya:

- **cookie/API-token SaaS admin page** tetap memakai ledger legacy yang cocok dengan blade aktif `saas.transactions`;
- **bearer-token API consumers** memakai payload purchase transaction yang lebih kaya dan UUID-oriented.

Dokumentasi ini mengikuti runtime tersebut secara eksplisit supaya QA, backend, dan frontend tidak salah mengaudit source of truth.

## Aktor & Role

| Aktor | Peran bisnis | Akses existing |
|------|---------------|----------------|
| Global HCM Admin / SaaS Admin | Melihat ledger transaksi, detail, export, create/update via API | Full access ke halaman transaksi dan endpoint `/v1/saas/transactions*` |
| Admin non-global / employee biasa | Tidak boleh membuka halaman admin billing atau mutate transaksi | Ditolak guard web admin dan backend `ADMIN_REQUIRED` |
| Company user | Tidak memakai halaman transaksi admin; company flow tetap membaca invoice company sendiri dari surface terpisah | Tidak ada akses ke `/saas/transactions` |

## Flow Bisnis End-to-End

### Flow utama

1. Admin membuka `/saas/transactions` atau alias `/purchase-transaction`.
2. Halaman memuat daftar transaksi billing dan filter berdasarkan invoice number, company name, status, payment method, dan date from.
3. Admin dapat membuka detail transaksi lewat modal dan men-trigger export CSV seluruh ledger.
4. Untuk integrasi API bearer, admin juga bisa membuat atau mengubah purchase transaction dengan relasi ke company, subscription, dan add-on.
5. Data transaction berhubungan dengan modul invoice, payment, subscription, dan reporting di layer billing yang sama.

### Exception / skenario negatif

- Guest atau user non-admin tidak boleh membuka halaman `/saas/transactions` maupun `/purchase-transaction`.
- Backend mengembalikan `403 ADMIN_REQUIRED` bila endpoint transaksi dipanggil tanpa role global admin yang sesuai.
- UI transaksi sekarang menampilkan pesan error envelope backend yang aktual, bukan toast generik saja.
- Pada bearer purchase contract, create transaction ditolak `422 SUBSCRIPTION_COMPANY_MISMATCH` bila `subscription_id` bukan milik `company_id` yang dikirim.
- Pada bearer purchase contract, filter `company_id` menerima UUID atau numeric legacy fallback; identifier yang tidak dapat diresolusikan sekarang menghasilkan hasil kosong, bukan leak data lintas company.

## Snapshot Audit 2026-04-19

- **Sudah diperbaiki**: route web `/saas/transactions` dan alias `/purchase-transaction` sekarang diproteksi `hcm.web.admin`.
- **Sudah diperbaiki**: page transaksi merender pesan backend error envelope yang aktual pada failure flow list/detail.
- **Sudah diperbaiki**: legacy create transaction tidak lagi gagal FK saat menerima `subscription_id` UUID; controller sekarang meresolusikan UUID ke FK integer internal.
- **Sudah diperbaiki**: bearer purchase contract menolak create transaction bila subscription berasal dari company lain.
- **Sudah diverifikasi**: bearer purchase contract menerima `company_id` UUID saat filter list.
- **Sudah diverifikasi**: active blade `saas.transactions` tetap cocok dengan ledger legacy filter/query yang dipakai frontend aktif.

## Role & Permission Cross-check

### Halaman aktif

| Surface | Existing target role | Catatan |
|--------|-----------------------|---------|
| `/saas/transactions` | HCM admin/global SaaS admin only | Halaman ledger aktif, memakai `saas.transactions` |
| `/purchase-transaction` | HCM admin/global SaaS admin only | Alias ke halaman ledger yang sama |
| `/company/invoices` | Company flow | Surface terpisah, bukan bagian dari admin transaction ledger |

### Endpoint API existing

| Endpoint | Fungsi | Existing runtime behavior |
|----------|--------|---------------------------|
| `GET /v1/saas/transactions` | List ledger | Cookie/API-token page memakai legacy ledger payload; bearer consumers memakai purchase transaction payload |
| `POST /v1/saas/transactions` | Create transaction | Admin only; legacy contract menerima `subscription_id` UUID lalu dirutekan ke FK internal, bearer contract memakai company/subscription UUID |
| `GET /v1/saas/transactions/{transaction}` | Detail transaction | Admin only; identifier menerima UUID + numeric fallback |
| `PUT /v1/saas/transactions/{transaction}` | Update transaction | Admin only; identifier menerima UUID + numeric fallback |
| `GET /v1/saas/transactions/export` | Export CSV | Admin only; dipakai tombol `Download All` pada halaman aktif |

## Kondisi Existing vs Target Bisnis

### Existing runtime yang sudah ada

- halaman aktif transaksi menggunakan blade `backend/resources/views/saas/transactions.blade.php` dan JS `frontend/resources/js/purchase-transactions-data.js`;
- ledger page masih bergantung pada shape legacy (`invoiceNumber`, `companyName`, `packageName`) yang cocok dengan list/detail UI sekarang;
- bearer purchase transaction contract sudah tersedia untuk payload yang lebih kaya (`transactionCode`, nested `company`, nested `subscription`, add-on linkage);
- create/update purchase transaction sudah memakai UUID untuk input eksternal dan integer FK di persistence layer internal;
- route web admin sekarang sudah mengikuti guard admin seperti surface billing admin lainnya.

### Gap yang masih terbuka

- path API yang sama masih melayani dua contract berbeda tergantung mekanisme auth; ini benar di runtime sekarang tetapi belum ideal untuk maintenance jangka panjang;
- README lama sebelumnya mencampur semua billing surfaces sebagai satu modul “selesai”, padahal transaksi aktif hanya satu bagian dari ekosistem invoice/payment/reporting;
- belum ada regression khusus untuk export filtering atau detail negative flow selain wiring toast/backend message.

### Keputusan kompromi sementara

- runtime split contract dipertahankan demi backward compatibility halaman admin aktif dan consumer bearer yang sudah ada;
- source-of-truth docs menuliskan split tersebut secara eksplisit alih-alih menyamarkan seolah hanya ada satu payload;
- audit ini hanya menutup bug/security/integrity yang nyata tanpa memaksa redesign total kontrak transaksi.

## UI Existing

- Blade aktif: `backend/resources/views/saas/transactions.blade.php`
- JS manager aktif: `frontend/resources/js/purchase-transactions-data.js`
- Filter aktif: invoice number, company, status, payment method, date from
- Aksi aktif: view detail modal, download all CSV, pagination
- Negative flow aktif: toast Bootstrap dengan pesan backend aktual

## Status

- Status implementation: **in progress**
- Tracker: [tracker.md](tracker.md)
- Snapshot saat ini: audit runtime transaksi aktif sudah ditutup untuk route guard, integrity create path, dan FE error rendering, tetapi unifikasi contract legacy vs purchase masih jadi pekerjaan lanjutan.

## API & Technical References

- API contract: [docs/api/purchase-transaction-api.md](../../api/purchase-transaction-api.md)
- OpenAPI: [docs/api/openapi.yaml](../../api/openapi.yaml)
- Technical summary: [IMPLEMENTATION.md](IMPLEMENTATION.md)

## Test & Evidence

- Backend route guard: `backend/tests/Feature/WebHcmRouteGuardTest.php`
- Backend legacy transaction integration: `backend/tests/Feature/TransactionControllerTest.php`
- Backend purchase transaction integration: `backend/tests/Feature/PurchaseTransactionServiceTest.php`
- Frontend wiring: `backend/tests/ui/purchase-transactions.wiring.test.js`
- Negative scenario yang sudah diverifikasi:
  - guest dan non-admin tidak boleh membuka page admin transaksi;
  - legacy create dengan UUID `subscription_id` berhasil dan tidak pecah FK;
  - bearer create menolak subscription-company mismatch;
  - bearer list menerima UUID `company_id`;
  - frontend menampilkan pesan backend `ADMIN_REQUIRED` pada failed list load.
