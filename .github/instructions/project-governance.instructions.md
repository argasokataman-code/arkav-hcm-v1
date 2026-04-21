---
applyTo: "**/*"
---

## Aturan proyek (semua path)

**Kanonical detail:** [`.cursor/rules/`](../../.cursor/rules/) (berkas `.mdc`). **Ringkasan agen:** [`AGENTS.md`](../../AGENTS.md).

Sebelum menyelesaikan pekerjaan substantif (fitur, API, migrasi, RBAC, UI HCM ter-wire):

1. **Security** — auth di server, bukan hanya UI; rujuk `.cursor/rules/application-security-baseline.mdc` + `web-hcm-route-security.mdc` + `multi-tenant-rbac-baseline.mdc`.
2. **Dokumentasi** — `docs/` yang terdampak + fitur di `docs/features/<feature>/`; rujuk `.cursor/rules/documentation-sync-after-development.mdc` + `documentation-feature-packaging.mdc`.
	- Jika sebuah feature doc punya seksi `Status`, wajib ada tracker khusus yang mencatat snapshot status, gap, dan evidence terbaru, lalu tracker itu harus diupdate setiap status berubah.
	- `docs/features/<feature>/README.md` wajib bisa dibaca non-engineer: sertakan flow bisnis end-to-end, lifecycle/status dan arti bisnisnya, keputusan/percabangan, kondisi existing vs target, serta cross-check role/permission API + halaman aktif.
	- Aturan ini dipakai saat feature sedang dikerjakan/diperbarui secara substantif atau saat dokumentasinya memang sedang dirapikan; tidak berarti setiap diskusi kecil harus langsung mengubah docs.
	- Jika flow bisnis detail belum tersedia, isi dulu berdasarkan sistem existing saat ini dan tandai gap/ambiguity secara eksplisit. Jangan menutup-nutupi gap runtime.
	- Gunakan format heading yang seragam antar feature agar evaluasi lintas modul mudah dilakukan.
3. **OpenAPI** — jika kontrak API berubah, `docs/api/openapi.yaml`; rujuk `.cursor/rules/openapi-collection-sync.mdc`.
4. **HCM role** — matriks di `docs/planning/active-hcm-templates-and-permissions.md` + `.cursor/rules/role-permissions-with-features.mdc`.
5. **Kualitas** — cek anomali singkat: `.cursor/rules/quality-anomaly-pass.mdc`.
6. **Deploy/runtime** — jika menyentuh `.github/workflows/*.yml`, `Dockerfile`, `run.sh`, `PRODUCTION-SETUP.md`, atau script deploy, ikuti `.cursor/rules/deployment-runtime-guard.mdc`.
	- Jangan ubah setup deploy sembarangan atau menambah file deploy alternatif tanpa konfirmasi eksplisit.
	- Host mount `backend/storage` wajib tetap aman untuk Laravel runtime dir: `storage/logs`, `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `storage/app/public`, `storage/app/private`, dan `bootstrap/cache`.
	- Sebelum `php artisan config:cache` / `view:cache`, runtime dir wajib dibuat dulu; validasi dengan `bash scripts/check-deploy-runtime-guard.sh`.
7. **Library/framework** — Context7 sebelum mengandalkan sintaks: `.cursor/rules/context7-usage.mdc`.

## Rule eksekusi pasca-fixing (wajib)

- Setiap selesai fixing code (BE/FE yang menyentuh behavior runtime), jalankan migrasi lebih dulu:
	- `cd backend && php artisan migrate --force`
- Setelah migrate, fixing atau fitur baru wajib test ulang minimal scope terdampak (dan perluas ke suite lintas modul bila area kritikal):
	- backend/PHP/API: `cd backend && php artisan test <suite-terdampak>`
	- frontend JS/TS/Blade ter-wire atau asset Vite: `cd backend && npm run test -- <scope>` atau `cd backend && npx vitest run <scope>`
	- jika perubahan lintas FE+BE, PHPUnit dan Vitest keduanya wajib
- Jangan klaim selesai tanpa evidence hasil migrate + hasil PHPUnit + hasil Vitest yang relevan.
- Jika `Nothing to migrate`, tetap lanjut ke test ulang dan laporkan status tersebut.
- Jika ada `Status` di dokumentasi fitur, update tracker terkait sebelum claim status final.

## Guard kontrak API (wajib)

- Jangan ubah kontrak API aktif kecuali memang ada issue API nyata (bug/security/regression) atau kebutuhan fitur baru yang disetujui.
- Jika route/controller API berubah, wajib sinkronkan dokumen kontrak:
	- `docs/api/openapi.yaml`
	- `docs/api/<feature>-api.md` terkait
- Untuk endpoint masa transisi UUID, dokumentasikan status identifier secara eksplisit (UUID-only, numeric legacy, atau UUID+legacy fallback).

Konflik instruksi user vs rule proyek: sebutkan konflik dan minta konfirmasi; jangan mengabaikan rule tanpa persetujuan eksplisit.

**Terakhir diselaraskan dengan:** isi `.cursor/rules` pada 2026-04-21 (mandatory PHPUnit + Vitest gate + deploy runtime guard).
