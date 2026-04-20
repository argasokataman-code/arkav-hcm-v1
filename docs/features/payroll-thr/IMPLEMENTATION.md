# Payroll THR Implementation

## Runtime Surface

- Blade utama: `backend/resources/views/payroll-thr.blade.php`.
- Asset frontend: `build/js/thr-payroll-batch.js`, dimuat dari `footer-scripts.blade.php` untuk route `payroll-thr`.
- PDF slip THR: `backend/resources/views/pdf/thr-slip.blade.php`.

## API Yang Dipakai

- Kalkulator: `POST /v1/hcm/payroll/thr-calculate`
- Pengaturan tahunan: `GET/PUT /v1/hcm/payroll/thr-settings`
- Batch: `GET /v1/hcm/payroll/thr-batch`, `POST /generate`, `POST /disburse`, `POST /post-payroll`
- Slip: `GET /v1/hcm/payroll/thr-batch/lines/{line}/slip`, `GET /v1/hcm/payroll/my-thr-slip`

## Perilaku Penting

- Generate batch butuh `calculationCutoffDate`; tanpa itu backend mengembalikan `THR_SETUP_CUTOFF_REQUIRED`.
- Disburse hanya memproses line eligible yang belum `paid`; line paid dilewati.
- Post-payroll ditolak bila masih ada payable line yang belum dibayar.
- Setelah post-payroll sukses, hasil berpindah ke run payroll purpose `thr` dan batch berubah ke `assigned`.
- Enforcement export reconciliation dapat memblokir disburse dan post-payroll sampai evidence tersedia.

## Dependensi Data

- Upah acuan berasal dari `baseSalary + fixedAllowance` employee profile.
- Katalog komponen payroll THR harus aktif agar post-payroll bisa membentuk line final.
- Payment date tahunan menentukan periode payroll THR saat posting.

## Test Dan Evidence

- `backend/tests/Feature/HcmPayrollThrApiTest.php` mencakup kalkulator, settings, generate, disburse, post-payroll, slip PDF, self-service THR slip, exclusion resigned/terminated, dan enforcement reconciliation.
- UI indicator export/evidence sudah tercatat juga pada feature `export-reconciliation`.
- Snapshot status terbaru disimpan di `tracker.md`.

## Risiko Yang Masih Harus Dijaga

- Perubahan aturan pro rata atau dasar hukum THR harus diikuti update dokumen kontrak dan test numerik.
- Karena siklus THR tahunan terpisah dari payroll monthly, drift tanggal `paymentDate` vs batch state perlu tetap diuji saat ada perubahan process.
