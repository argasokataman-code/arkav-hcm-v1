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

## Shared hosting deploy (lokal-first automated)

1. Lokal: `bash scripts/local-test-gate.sh` (mandatory gate)
2. Lokal: `bash scripts/shared-hosting-package-local.sh` (build artifact)
3. Lokal: parity pre-check `bash scripts/compare-local-staging.sh --user <user> --host <host> --port <port> --app-dir <remote_app_dir>`
4. Lokal: `git commit && git push origin main`
5. **GitHub Actions auto-deploy**: SCP + SSH extract + `shared-hosting-deploy-easy.sh`
6. Lokal: parity post-check dengan command yang sama, wajib pastikan hash critical sinkron.

Artifact di-track di `release/shared-hosting/` agar GitHub dapat deploy tanpa re-build.

### Anti-drift rule (mandatory)

- Jangan lakukan deploy manual per-file ke staging (`scp backend/...`) kecuali emergency yang disetujui.
- Setiap emergency patch di server harus diikuti commit repo + artifact deploy normal supaya parity local/staging kembali.
- Artifact wajib menyertakan `RELEASE-METADATA.txt` untuk verifikasi commit runtime.

README feature di [docs/features](docs/features) harus business-readable: flow end-to-end, keputusan/lifecycle, gap existing vs target, dan cross-check role/API permission tidak boleh hilang untuk fitur operasional.

HCM role/permission vs URL aktif: [`docs/planning/active-hcm-templates-and-permissions.md`](docs/planning/active-hcm-templates-and-permissions.md).

## GitHub Copilot / instruksi di repo

Agen lewat **GitHub** (Copilot, review di web) memakai [`.github/instructions/`](.github/instructions/) — ringkasan per `applyTo` yang **harus selaras** dengan `.cursor/rules/*.mdc`. Indeks integrasi dan aturan “satu sumber kebenaran”: [`.github/instructions/README.md`](.github/instructions/README.md).
