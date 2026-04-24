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

## Rule eksekusi pasca-fixing (wajib) — LOCAL-FIRST WORKFLOW

**MANDATORY:** Jalankan local test gate SEBELUM commit/push:

```bash
bash scripts/local-test-gate.sh
```

Gate ini secara otomatis:
1. `composer install --no-dev`
2. `npm ci && npm run build`
3. `php artisan migrate --force --env=testing`
4. `php artisan test` (PHPUnit)
5. `npx vitest run` (Vitest)

Hanya push ke main jika **semua tests pass lokal**. GitHub Actions hanya bertugas verifikasi artifact + deploy.

- Jangan klaim selesai tanpa evidence hasil local test gate yang success.
- Jika ada `Status` di dokumentasi fitur, update tracker terkait sebelum claim status final.

### Shared-hosting auto deploy (lokal-first)

**Workflow**:
1. **Lokal** — `bash scripts/local-test-gate.sh` (tester saja, gate mandatory)
2. **Lokal** — Jika pass, build artifact: `bash scripts/shared-hosting-package-local.sh`
	- Script ini otomatis melakukan rolling prune artifact lama (default simpan 5 terbaru).
	- Override jumlah retention bila perlu: `SHARED_HOSTING_ARTIFACT_KEEP_COUNT=<n> bash scripts/shared-hosting-package-local.sh`.
3. **Lokal** — Guard sinkronisasi artifact (wajib sebelum push):
	- `bash scripts/check-shared-hosting-artifact-sync.sh`
	- Guard ini wajib PASS; jika gagal berarti artifact stale terhadap commit aktif.
4. **Lokal** — Parity pre-check (wajib sebelum push):
	- `bash scripts/compare-local-staging.sh --user <user> --host <host> --port <port> --app-dir <remote_app_dir>`
	- Simpan evidence hash yang berbeda/sama sebelum deploy.
5. **Lokal** — Commit + push: `git add release/ && git commit && git push origin main`
6. **GitHub Actions** — Auto: cek artifact ada + cek sinkronisasi artifact vs commit → SCP upload → SSH deploy → verify status
7. **Lokal** — Parity post-check (wajib setelah deploy):
	- jalankan ulang `scripts/compare-local-staging.sh` dan pastikan file critical + release marker sinkron.

**GitHub workflow** (`.github/workflows/shared-hosting-deploy.yml`):
- Trigger: push ke main dengan perubahan di `backend/**` atau `release/shared-hosting/**`
- Hanya task: cek artifact → setup SSH → SCP → SSH extract + deploy
- ERROR jika artifact tidak ada (wajib build lokal dulu)

**Deployment commands di server**:
- `tar -xzf shared-hosting-artifact-<ts>.tar.gz`
- `bash scripts/shared-hosting-deploy-easy.sh` (migrate --force, cache rebuild, storage:link)

**Larangan drift runtime (mandatory):**
- Dilarang deploy manual per-file aplikasi ke staging (`scp backend/...` langsung) kecuali emergency approved.
- Untuk emergency hotfix langsung server, wajib follow-up commit di repo + rebuild artifact + deploy normal agar parity kembali.
- Artifact wajib membawa `RELEASE-METADATA.txt` dan diverifikasi lewat `scripts/compare-local-staging.sh`.

## Guard kontrak API (wajib)

- Jangan ubah kontrak API aktif kecuali memang ada issue API nyata (bug/security/regression) atau kebutuhan fitur baru yang disetujui.
- Jika route/controller API berubah, wajib sinkronkan dokumen kontrak:
	- `docs/api/openapi.yaml`
	- `docs/api/<feature>-api.md` terkait
- Untuk endpoint masa transisi UUID, dokumentasikan status identifier secara eksplisit (UUID-only, numeric legacy, atau UUID+legacy fallback).

Konflik instruksi user vs rule proyek: sebutkan konflik dan minta konfirmasi; jangan mengabaikan rule tanpa persetujuan eksplisit.

**Terakhir diselaraskan dengan:** isi `.cursor/rules` pada 2026-04-21 (mandatory PHPUnit + Vitest gate + deploy runtime guard).
