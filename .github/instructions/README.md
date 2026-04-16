# Integrasi aturan: GitHub Copilot ↔ Cursor

## Sumber kebenaran (canonical)

| Lapisan | Lokasi | Peran |
|--------|--------|--------|
| **Cursor (detail, gate agent)** | [`.cursor/rules/`](../../.cursor/rules/) — berkas `.mdc` + frontmatter | Aturan lengkap, `alwaysApply`, matriks HCM, checklist penutupan, dll. |
| **Ringkasan agen (repo root)** | [`AGENTS.md`](../../AGENTS.md) → [`.cursor/rules/AGENTS.md`](../../.cursor/rules/AGENTS.md) | Arah cepat + penutupan task. |
| **GitHub / Copilot (konteks per glob)** | Berkas di folder ini — `*.instructions.md` | **Cerminan ringkas** + `applyTo` agar Copilot di GitHub/Code spaces mengikuti proyek yang sama. |

**Prinsip:** isi panjang dan perubahan kebijakan dilakukan di **`.cursor/rules/*.mdc`**. Berkas `.github/instructions/*.instructions.md` hanya boleh:

- mengarahkan ke path kanonik di atas, dan
- memuat **ringkasan operasional** yang cukup untuk PR/review tanpa membaca semua `.mdc`.

## Saat mengubah aturan

1. Ubah dulu **[`.cursor/rules/<nama>.mdc`](../../.cursor/rules/)** (atau [`AGENTS.md`](../../.cursor/rules/AGENTS.md) di `.cursor/rules/` bila perlu).
2. Sesuaikan **ringkasan** di `.github/instructions/*.instructions.md` yang relevan (`applyTo` masih cocok dengan path yang disentuh).
3. Perbarui baris **“Terakhir diselaraskan dengan”** di bagian bawah berkas instruksi yang Anda sentuh.
4. Jika matriks HCM / izin berubah: ikuti juga [`docs/planning/active-hcm-templates-and-permissions.md`](../../docs/planning/active-hcm-templates-and-permissions.md) dan cerminan di `.cursor/rules/role-permissions-with-features.mdc`.

## Daftar berkas instruksi GitHub

| Berkas | `applyTo` (glob) | Selaras dengan rule Cursor (utama) |
|--------|------------------|--------------------------------------|
| [`laravel-hcm.instructions.md`](./laravel-hcm.instructions.md) | Backend PHP, routes, Blade | `development-closure-checklist`, `openapi-collection-sync`, `api-spec-docs-sync-per-change`, `migration-discipline`, `application-security-baseline`, `bugfix-guardrails`, `context7-usage` |
| [`frontend-hcm.instructions.md`](./frontend-hcm.instructions.md) | JS sumber + Blade | `backend-template-lock`, `no-hardcoded-dummy-template-data`, `documentation-sync-after-development`, `role-permissions-with-features` (UX vs server), `context7-usage` |
| [`project-governance.instructions.md`](./project-governance.instructions.md) | Semua berkas | `00-session-preamble`, `development-closure-checklist`, `documentation-feature-packaging`, `quality-anomaly-pass` |

## Cursor tidak membaca folder ini secara otomatis

Cursor memuat **`.cursor/rules/*.mdc`**. Folder `.github/instructions/` memastikan **agen yang bekerja lewat GitHub** (Copilot suggestions, PR review di web) melihat pesan yang konsisten. Dua sisi harus dijaga agar tidak divergen.
