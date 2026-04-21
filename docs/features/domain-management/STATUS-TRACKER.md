# Domain Management Status Tracker

## Snapshot 2026-04-21

- Status: audited and runtime-aligned
- Scope: FE↔BE wiring, API doc truth, multi-company filter coverage, negative flow validation, UI UX consistency

## Findings Closed

- Frontend create/update sebelumnya mengirim `company_id` numeric dari company dropdown, padahal backend aktif memvalidasi `company_id` sebagai UUID perusahaan.
- Frontend belum punya validasi host-only untuk `domain_name`, sehingga input seperti `https://bad.example.com/path` baru gagal di backend atau berpotensi lolos bila validasi server berubah.
- Dokumentasi API `docs/api/custom-domain-api.md` sebelumnya menggambarkan controller/model `custom_domains` yang bukan route aktif.
- Feature docs menyebut beberapa gap wiring frontend yang pada runtime aktif sebenarnya sudah atau seharusnya sudah ditutup.

## Current Runtime Truth

- Route aktif: `backend/routes/api.php` -> `App\Http\Controllers\Api\DomainController`
- Model aktif: `App\Models\Domain` (`domains` table)
- Path `{domain}`: UUID route binding
- Create/update `company_id`: UUID company
- List filter `company_id`: numeric internal `companies.id`
- Verify flow: simulasi manual, `pending -> verified`; status non-pending dikembalikan apa adanya

## Evidence

- PHPUnit:
  - `php artisan test tests/Feature/DomainControllerTest.php tests/Feature/CustomDomainServiceTest.php`
  - Result: 21 passed
- Vitest:
  - `npx vitest run tests/ui/domain-management.wiring.test.js`
  - Result: 3 passed
- Build:
  - `npm run build`
  - Result: passed

## Remaining Gaps

- Verify domain masih simulasi, belum melakukan DNS/file verification real.
- Response domain list/detail belum mengembalikan `companyUuid`, sehingga frontend masih perlu map dari company list saat edit.
- OpenAPI/runtime sync untuk domain baru ditambahkan pada audit ini; perlu dijaga jika controller aktif berubah lagi.