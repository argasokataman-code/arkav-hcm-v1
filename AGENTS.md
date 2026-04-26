# Agent instructions

Full agent notes live in [`.cursor/rules/AGENTS.md`](.cursor/rules/AGENTS.md). **Penutupan task:** ikuti [`development-closure-checklist.mdc`](.cursor/rules/development-closure-checklist.mdc) (security + docs + OpenAPI). Untuk file deploy/runtime, ikuti juga [`deployment-runtime-guard.mdc`](.cursor/rules/deployment-runtime-guard.mdc) agar mount storage, runtime cache dir Laravel, dan urutan artisan cache tidak regress.

## LOCAL-FIRST WORKFLOW (mandatory untuk production)

Setiap push ke main **WAJIB** lulus local test gate terlebih dulu:

```bash
bash scripts/local-test-gate.sh
```

Script ini menjalankan (otomatis):
1. composer install --no-dev
2. npm ci && npm run build
3. php artisan migrate --force --env=testing
4. php artisan test (PHPUnit)
5. npx vitest run (Vitest)

**Hanya commit/push jika semua pass.** GitHub Actions hanya cek artifact + deploy (bukan re-test).

## Git hooks enforcement (recommended mandatory)

Aktifkan guard lokal sekali per clone:

```bash
bash scripts/install-git-hooks.sh
```

Dengan hook aktif:
- `pre-commit`: menjalankan `scripts/check-tests-on-change.sh` + `scripts/check-api-docs-sync.sh --staged`
- `pre-push` (khusus push ke `main`): memblok push jika salah satu gagal:
	1. `bash scripts/local-test-gate.sh`
	2. `bash scripts/check-shared-hosting-artifact-sync.sh <HEAD>`
	3. `bash scripts/check-deploy-runtime-guard.sh`

## Communication discipline (mandatory)

- Saat memberi "next step" atau "saran", **wajib** tampilkan seluruh langkah/opsi relevan dalam **satu list lengkap** pada satu respons.
- Dilarang memberi saran bertahap satu-per-satu jika konteksnya bisa diberikan sekaligus.
- Urutkan list dari paling wajib ke opsional agar tim tidak bingung prioritas.

## Shared hosting deploy (lokal-first automated)

1. Lokal: `bash scripts/local-test-gate.sh` (mandatory gate)
2. Lokal: commit perubahan code/docs dulu (tanpa artifact release)
3. Lokal: `bash scripts/shared-hosting-package-local.sh` (build artifact dari commit terbaru)
4. Lokal: `bash scripts/check-shared-hosting-artifact-sync.sh` (wajib PASS)
5. Lokal: commit perubahan `release/shared-hosting/` (artifact refresh)
6. Lokal: push ke `main` **hanya setelah konfirmasi eksplisit dari operator**
7. **GitHub Actions auto-deploy**: SCP + SSH extract + `shared-hosting-deploy-easy.sh`
8. Lokal: parity post-check (`scripts/compare-local-staging.sh`) saat akses SSH staging tersedia.

### Mandatory safety (deploy prep)

- Saat diminta "prepare deploy", **jangan push otomatis**. Stop di status "ready to push" dan tunggu instruksi operator.
- Jangan minta parameter SSH (`--user/--host/--port/--app-dir`) untuk flow GitHub auto-deploy standar, kecuali operator memang minta parity check manual.
- Artifact **wajib** dibangun setelah commit code/docs utama agar `RELEASE-METADATA git_head` tidak stale.
- Jika pre-push menolak karena stale artifact, ulangi urutan resmi: commit code/docs -> package artifact -> commit release artifact -> push.
- Gunakan `bash scripts/prepare-main-push.sh --message "<commit message code/docs>"` untuk alur aman satu pintu (script ini tidak pernah melakukan push otomatis).

Artifact di-track di `release/shared-hosting/` agar GitHub dapat deploy tanpa re-build.

### Anti-drift rule (mandatory)

- Jangan lakukan deploy manual per-file ke staging (`scp backend/...`) kecuali emergency yang disetujui.
- Setiap emergency patch di server harus diikuti commit repo + artifact deploy normal supaya parity local/staging kembali.
- Artifact wajib menyertakan `RELEASE-METADATA.txt` untuk verifikasi commit runtime.

README feature di [docs/features](docs/features) harus business-readable: flow end-to-end, keputusan/lifecycle, gap existing vs target, dan cross-check role/API permission tidak boleh hilang untuk fitur operasional.

HCM role/permission vs URL aktif: [`docs/planning/active-hcm-templates-and-permissions.md`](docs/planning/active-hcm-templates-and-permissions.md).

## GitHub Copilot / instruksi di repo

Agen lewat **GitHub** (Copilot, review di web) memakai [`.github/instructions/`](.github/instructions/) — ringkasan per `applyTo` yang **harus selaras** dengan `.cursor/rules/*.mdc`. Indeks integrasi dan aturan “satu sumber kebenaran”: [`.github/instructions/README.md`](.github/instructions/README.md).
