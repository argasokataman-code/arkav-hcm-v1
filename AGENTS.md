# Agent instructions

Full agent notes live in [`.cursor/rules/AGENTS.md`](.cursor/rules/AGENTS.md). **Penutupan task:** ikuti [`development-closure-checklist.mdc`](.cursor/rules/development-closure-checklist.mdc) (security + docs + OpenAPI). Untuk file deploy/runtime, ikuti juga [`deployment-runtime-guard.mdc`](.cursor/rules/deployment-runtime-guard.mdc) agar mount storage, runtime cache dir Laravel, dan urutan artisan cache tidak regress.

Untuk setiap fixing atau fitur baru yang menyentuh runtime, jangan tutup task tanpa evidence `php artisan migrate --force`, `php artisan test <suite-terdampak>`, dan `Vitest` untuk scope frontend yang relevan; perubahan lintas FE+BE wajib menjalankan keduanya.

README feature di [docs/features](docs/features) harus business-readable: flow end-to-end, keputusan/lifecycle, gap existing vs target, dan cross-check role/API permission tidak boleh hilang untuk fitur operasional.

HCM role/permission vs URL aktif: [`docs/planning/active-hcm-templates-and-permissions.md`](docs/planning/active-hcm-templates-and-permissions.md).

## GitHub Copilot / instruksi di repo

Agen lewat **GitHub** (Copilot, review di web) memakai [`.github/instructions/`](.github/instructions/) — ringkasan per `applyTo` yang **harus selaras** dengan `.cursor/rules/*.mdc`. Indeks integrasi dan aturan “satu sumber kebenaran”: [`.github/instructions/README.md`](.github/instructions/README.md).
