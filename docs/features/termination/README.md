# Termination

## Ringkasan

Menu **Termination** menyimpan riwayat **pemutusan hubungan kerja** yang diinisiasi perusahaan: tipe terminasi, tanggal notice, tanggal efektif, alasan, status, dan catatan.

## Akses (RBAC)

- **Halaman `/termination`**: HCM Admin — **server:** `hcm.web.admin` redirect non-admin ke `/employee-dashboard`; **client:** `termination-data.js` cek `auth/me` sebelum load list.
- **API**: Mutasi dan list admin → `GET/POST/PUT/DELETE /v1/hcm/terminations` (**hcmAdmin**). `GET /terminations/{id}` dan `GET /terminations/users/{userId}/terminations`: admin semua user, karyawan hanya **self**.

## UI (selaras template)

- List tabel tanpa dummy; modal CRUD `#arcav_termination_modal`; detail `#arcav_termination_detail_modal`.
- **Termination type**: input teks + `datalist` saran (Retirement, Layoff, …); bebas isi manual (≤ 150 karakter, sesuai API).
- **Department**: otomatis dari team employee (disabled), sama pola promotion/resignation.
- Delete: `ArcavUi.confirmDelete`.
- **Employee detail**: section Termination + tombol Detail (script `termination-data.js` dimuat di `employee-details`).

## API

`docs/api/hcm-termination-api.md`

## Validasi penting

- `terminationType` wajib, max 150; `reason` max 2000; `notes` max 2000.
- `terminationDate` ≥ `noticeDate`.

## Test

`TerminationApiTest` — admin CRUD + forbidden list + self show/per-user list.
