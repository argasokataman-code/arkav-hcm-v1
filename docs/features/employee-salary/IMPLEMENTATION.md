# Employee Salary - Implementation Notes

## Scope

Halaman `/employee-salary` dipakai HCM Admin untuk mengelola kompensasi bulanan karyawan:

- `baseSalary`
- `fixedAllowance`

Data disimpan pada profil karyawan (`employee_profiles`) dan dipakai lintas modul payroll.

## Entry Points

- Web page: `/employee-salary`
- Frontend script: `frontend/resources/js/employee-salary-data.js`
- Blade page: `backend/resources/views/employee-salary.blade.php`
- Modal partial: `backend/resources/views/hcm/partials/employee-salary-compensation-modal.blade.php`

## API Contract Used by UI

1. `GET /v1/identity/auth/me`
   - Dipakai untuk cek role admin di frontend.
   - Jika `hcmAdmin !== true`, frontend redirect ke `/employee-dashboard`.
2. `GET /v1/hcm/employees?page=&perPage=&search=&status=`
   - Sumber tabel kompensasi.
3. `PUT /v1/hcm/employees/{id}`
   - Payload mutasi kompensasi dari modal.
   - Field yang dipakai UI ini: `baseSalary`, `fixedAllowance`.

## Role & Permission

- Web route `/employee-salary` diproteksi server-side dengan middleware `hcm.web.admin`.
- API employees untuk list/mutasi tetap admin-only di backend.
- Frontend menambah guard kedua (redirect non-admin) sebagai UX fail-safe.

## UI Flow

1. Halaman load.
2. Frontend panggil `GET /v1/identity/auth/me`.
3. Jika admin, lanjut `GET /v1/hcm/employees` untuk render tabel.
4. User bisa search/filter.
5. Klik edit pada baris membuka modal kompensasi.
6. Submit modal kirim `PUT /v1/hcm/employees/{id}`.
7. Toast sukses tampil, modal tutup, daftar direfresh.

## Integration Impact

- Perubahan nilai kompensasi akan mempengaruhi:
  - payroll basis bulanan,
  - kalkulasi lembur yang memakai basis kompensasi,
  - preview kompensasi kontrak (PKWT) terkait data profil.

## QA Baseline

- Playwright E2E: `backend/e2e/tests/employee-salary.spec.js`
- Command utama: `cd backend && npm run e2e:employee-salary`