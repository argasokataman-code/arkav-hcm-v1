# SPT Masa PPh 21 (Bulanan)

## Ringkasan

Modul **SPT Masa PPh 21** dipakai untuk menyiapkan pelaporan pajak bulanan dari hasil payroll yang sudah final. Modul ini fokus pada **rekap, validasi, review, dan export pelaporan**.

Modul ini **bukan** kalkulator pajak baru. Semua angka tetap mengikuti hasil payroll dan PPh 21 yang sudah dihitung lebih dulu di modul payroll.

## Akses

- HCM Admin (`hcm.web.admin` web guard, `api.token` + `tenant.context` API guard): generate SPT, review detail, regenerate, tandai ready, submit, dan export CSV.
- Finance/Payroll Staff: review dan export, tanpa mutasi nilai (gating opsional sesuai policy tenant).
- Karyawan biasa: tidak boleh akses modul ini.

Backend tetap sumber kebenaran otorisasi. UI hanya menyembunyikan tombol untuk UX. Permission codes target:

- `tax.spt.view`
- `tax.spt.manage`

## UI Aktif

Target halaman fase MVP:

- `/spt-masa-pph21`: list SPT per periode bulanan.
- `/spt-masa-pph21/{sptUuid}`: detail SPT periode terpilih (deep link via UUID publik).

Aksi pada list: Generate SPT Masa, View Detail, Export CSV.

Aksi pada detail: Regenerate, Tandai Ready, Submit.

## Flow Bisnis End-to-End

1. Payroll bulan berjalan selesai dengan run `finalized` (per user) dan, untuk skenario penuh, periode mencapai status `posted` di `hcm_payroll_periods`.
2. User klik **Generate SPT Masa** untuk periode `YYYY-MM`.
3. Sistem membaca payroll lines yang sudah final pada periode tersebut dan membentuk snapshot SPT:
   - buat header `hcm_spt_masa_headers` dengan status `draft`
   - isi `hcm_spt_masa_details` per karyawan/non-pegawai
4. User review ringkasan dan detail data.
5. Jika valid, user klik **Tandai Ready** (status `ready`).
6. Setelah setor pajak ke negara, user klik **Submit**.
7. Sistem ubah status menjadi `submitted` dan mengisi `submitted_at`.

## Lifecycle Dan Keputusan Bisnis

State machine wajib:

- `draft -> ready -> submitted`

Transisi balik tidak diizinkan; perbaikan data dilakukan via **Regenerate** selama status masih `draft` atau `ready`.

Aturan bisnis utama:

- SPT bersifat read-only terhadap data payroll final.
- Tidak boleh edit manual nilai bruto atau pajak di modul SPT.
- Jika ada salah data, perbaikan dilakukan di payroll lalu `regenerate` SPT.
- Generate harus idempotent (`generationKey`) supaya retry tidak menduplikasi header.
- Tidak boleh ada dua header aktif (`status in [draft, ready]`) untuk pasangan `(company_id, periode)`.

## Integrasi

Modul ini terhubung ke:

- Payroll Runs - status `finalized` sebagai gate generate (lihat `docs/features/payroll-runs/README.md`).
- Payroll Periods - status `posted` opsional untuk full-period closure.
- Payroll Lines - sumber bruto dan PPh21 per karyawan.
- Tax Governance - kualitas profil pajak karyawan (NPWP/PTKP) sebagai validasi pre-submit (`docs/features/tax-governance/README.md`).
- Reporting - export CSV dipakai sebagai evidence operasional.

## Sumber Data Existing (Wajib dipatuhi saat coding)

Modul ini **tidak boleh** memperkenalkan tabel `payroll_result`/`pph21_result` baru. Sumber resmi:

- `hcm_payroll_runs` (status: `draft`, `finalized`, `void`; kunci: `purpose`, `finalized_at`, `period_id`).
- `hcm_payroll_periods` (status: `open`, `posted`).
- `hcm_payroll_lines` (kolom kunci: `hcm_payroll_run_id`, `user_id`, `kind`, `category`, `component_code`, `amount`).

Aturan agregasi MVP:

- `bruto` per user = sum `amount` payroll lines `kind = addition` yang masuk skema PPh21 taxable (`category` mengandung `pph21_taxable_*`).
- `pph21` per user = sum `amount` payroll lines `kind = deduction` dengan `category` di-prefix `pph21_*`.
- Default scope: `purpose = monthly`. Skenario THR/bonus akan dipertimbangkan di fase berikutnya.

Detail kategori mengikuti `App\Support\PayrollDraftBuilder` dan `docs/api/openapi.yaml`. Generator wajib menormalkan agar tidak terjadi double counting.

## Klasifikasi Wajib

Mapping ke `kategori_spt`:

- `permanent -> pegawai_tetap`
- `contract -> tidak_tetap`
- `intern -> tidak_tetap`
- `non_employee -> non_pegawai`

Profile karyawan repo saat ini hanya menyimpan `permanent|contract` (alias transisi `pkwt|pkwtt`). Dampaknya:

- MVP hanya merekap karyawan terdaftar `permanent` dan `contract`.
- `intern` dan `non_employee` ditunda ke fase lanjutan; jika dibutuhkan, akan ditampung lewat tabel opsional `hcm_bukti_potong` (input non-pegawai).
- Generator wajib menolak record tanpa identifikasi kontrak; tidak boleh menebak kategori.

## Validasi Wajib

Saat generate:

- Periode harus terisi (`YYYY-MM`).
- Minimal ada satu run `finalized` purpose `monthly` di periode tersebut.
- Tidak boleh ada header aktif (`draft|ready`) lain untuk pasangan `(company_id, periode)`.
- Regenerate butuh konfirmasi eksplisit + audit event.
- Snapshot dibekukan; mutasi payroll setelah snapshot tidak otomatis mengubah header existing.

Saat submit:

- `total_pph21` header == agregat deduction `pph21_*` periode (toleransi pembulatan `decimal:2`).
- `total_bruto` header == agregat addition taxable.
- Detail wajib lengkap: `nama`, `npwp`, `bruto`, `pph21`, `kategori_spt`.
- Validasi NPWP mengikuti tax governance: 15-16 digit setelah strip `.` dan `-`.

## Kontrak API Target

Prefix runtime: `/v1/hcm/spt-masa` (file route: `backend/routes/api/spt-masa.php`, middleware `api.token` + `tenant.context`).

| Method | Path | Fungsi |
|---|---|---|
| GET | `/headers` | List SPT bulanan tenant aktif (filter periode/status) |
| POST | `/headers` | Generate SPT periode (idempotent via `generationKey`) |
| GET | `/headers/{sptRef}` | Detail header + detail lines |
| POST | `/headers/{sptRef}/regenerate` | Regenerate snapshot dari payroll final periode |
| POST | `/headers/{sptRef}/mark-ready` | Transisi `draft -> ready` |
| POST | `/headers/{sptRef}/submit` | Transisi `ready -> submitted` |
| GET | `/headers/{sptRef}/export.csv` | Export CSV DJP-style |

Konvensi:

- `{sptRef}` adalah UUID publik. Tidak ada fallback numeric ID di path runtime (selaras `tax-governance/{policyRef}`).
- Mutating ops (`regenerate`, `mark-ready`, `submit`) wajib menyertakan `version` di body untuk optimistic lock.
- Generate menerima `generationKey` agar retry tidak menduplikasi header.
- Header response wajib menyertakan `X-Tenant-Id`.
- Envelope standar:
  - sukses: `{ "success": true, "data": ... }`
  - gagal: `{ "success": false, "error": { "code": "...", "message": "..." } }`

Negative codes wajib:

- `SPT_PAYROLL_NOT_FINAL`
- `SPT_HEADER_DUPLICATE`
- `SPT_INVALID_TRANSITION`
- `SPT_VERSION_CONFLICT`
- `SPT_DETAIL_INCOMPLETE`
- `SPT_TOTAL_MISMATCH`
- `TENANT_CONTEXT_REQUIRED`
- `AUTH_FORBIDDEN`

## OpenAPI Sync

Fase 2 wajib menambah skema baru di `docs/api/openapi.yaml`:

- `SptMasaHeader`, `SptMasaDetail`, `SptMasaListResponse`, `SptMasaDetailResponse`, `SptMasaGenerateRequest`, `SptMasaSubmitRequest`, `SptMasaExportResponse`.

Run `bash scripts/check-api-docs-sync.sh` setelah perubahan kontrak.

## Existing Vs Target

Existing:

- Payroll runtime, PPh21 calculator, BPJS, dan tax governance sudah aktif.
- Belum ada modul SPT Masa snapshot-based bulanan.

Target MVP:

- Generate SPT dari payroll final per periode (idempotent + version lock).
- List + detail SPT tenant-scoped + UUID public route.
- Validasi status dan kelengkapan data.
- Export CSV minimal: NPWP, Nama, NIK, Kategori SPT, Bruto, PPh21, Bukti Potong Type.
- Status flow `draft -> ready -> submitted`.

## Status

- Status implementasi: **planning phase**
- Eksekusi 3 fase:
  - Fase 1: Build FE (shell UI + wiring action)
  - Fase 2: Build BE (UUID-only contract + snapshot engine)
  - Fase 3: Integrasi & build verification (E2E + export validation)
- Tracker: [tracker.md](tracker.md)

## Catatan Scope

Non-goals yang harus dijaga:

- Tidak menghitung ulang pajak.
- Tidak mengubah pajak secara manual di modul SPT.
- Tidak auto-submit ke DJP tanpa review user.
- Tidak menambah tabel sumber payroll baru.
