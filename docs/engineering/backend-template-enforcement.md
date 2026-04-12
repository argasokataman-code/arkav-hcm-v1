# Backend Development Must Follow Current Template

Dokumen ini menetapkan aturan wajib untuk semua development backend (BE) ke depan.

## Objective

Memastikan seluruh perubahan backend tetap kompatibel dengan UI template yang saat ini dipakai, tanpa menambah pola UI di luar template.

## Mandatory rules

1. Backend hanya melayani flow yang ada pada template aktif (auth, dashboard, employees, leave, dan menu turunannya).
2. Tidak membuat asumsi UI baru dari sisi backend.
3. Kontrak endpoint (request/response/error) harus konsisten terhadap kebutuhan komponen template yang sudah ada.
4. Semua perubahan behavior endpoint yang memengaruhi UI wajib diikuti update docs + verifikasi flow UI.
5. Menu atau route yang sudah disederhanakan (contoh: employees list/grid dalam satu menu) tidak boleh dipecah ulang tanpa keputusan produk.

## Endpoint design checklist

Setiap endpoint baru/revisi harus menjawab poin berikut:

- Halaman template mana yang consume endpoint ini?
- Komponen mana yang membaca field response ini?
- Apakah perubahan ini backward-compatible untuk halaman existing?
- Error code apa yang akan tampil di UI dan bagaimana fallback-nya?
- Apakah auth state (token/me/logout) tetap konsisten?

## PR acceptance checklist (BE)

- [ ] Referensi halaman/template target dicantumkan di deskripsi PR.
- [ ] Contract endpoint terdokumentasi di `docs/api/api-spec-phase-1.md` (jika ada perubahan).
- [ ] Tidak ada penambahan UI route baru di luar template aktif.
- [ ] Flow login, dashboard access, dan logout tidak regress.
- [ ] Untuk perubahan employee/leave, toggle/list/grid dan navigasi existing tetap aman.

## Non-compliance examples

- Menambah endpoint yang memaksa frontend membangun halaman baru non-template.
- Mengubah nama field penting tanpa compatibility layer dan tanpa update frontend.
- Mengubah alur auth sehingga redirect/guard template menjadi rusak.

## Compliance examples

- Menambah field opsional pada response lama (non-breaking).
- Menambah endpoint baru untuk komponen template yang sudah ada dan terdokumentasi.
- Menambah validasi backend sambil tetap mengembalikan error contract standar (`code`, `message`, `details`, `traceId`).
