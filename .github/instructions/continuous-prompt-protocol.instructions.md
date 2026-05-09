---
applyTo: "**/*"
---

# Continuous Prompt Protocol — Entry Gate Setiap Task

**Sumber kanonik:** [`.cursor/rules/continuous-prompt-protocol.mdc`](../../.cursor/rules/continuous-prompt-protocol.mdc)

Sebelum memulai coding apapun, jalankan **6 fase** berikut secara berurutan.

---

## Fase 1: Task Receipt — STOP & Identifikasi

Baca task dua kali. Catat:
- **Domain/feature**: payroll / attendance / leave / employee / identity / dll.
- **Jenis task**: `bugfix` | `new-feature` | `refactor` | `migration` | `docs` | `analisis`
- **Scope**: API, UI, DB, auth, deploy?
- **Role yang relevan**: admin-only, self, team?

Jika ada ambiguitas → gunakan `vscode_askQuestions` untuk mengklarifikasi **sebelum** lanjut.

---

## Fase 2: Source Discovery — Hierarki Sumber Kebenaran

Cari dari atas ke bawah, berhenti saat mendapat yang dibutuhkan:

| # | Lokasi | Isi |
|---|--------|-----|
| 1 | `.cursor/rules/*.mdc` | Rule agent (wajib dibaca) |
| 2 | `docs/features/<feature>/README.md` | Flow bisnis, role, lifecycle |
| 3 | `docs/features/<feature>/IMPLEMENTATION.md` | Arsitektur, DB schema, controller |
| 4 | `docs/api/<feature>-api.md` | Endpoint, request/response, RBAC |
| 5 | `docs/api/openapi.yaml` | Swagger/OpenAPI spec |
| 6 | `docs/planning/active-hcm-templates-and-permissions.md` | Matriks role/URL |
| 7 | `docs/features/RUNTIME-FEATURE-CLASSIFICATION.md` | default vs MVP vs add-on |
| 8 | `docs/features/INTEGRATION-MAP.md` | Cross-feature dependencies |
| 9 | `backend/routes/api/` + `backend/routes/web/` | Routes aktual |
| 10 | `backend/database/migrations/` | Schema aktual |

**Wajib minimal setiap task:**
- [ ] Baca `docs/features/<feature>/README.md`
- [ ] Cek `docs/api/<feature>-api.md` atau `backend/routes/api/<feature>.php`
- [ ] Cek `docs/planning/active-hcm-templates-and-permissions.md` untuk baris yang relevan

---

## Fase 3: Impact Analysis — Definisikan Scope

Jawab checklist ini sebelum menyentuh satu baris code:

**Data & Schema**
- Model/table mana yang berubah? Ada FK cascade/restrict?
- Jika kolom baru: nullable? default? backfill data existing?

**API Contract**
- Response shape berubah? Field dihapus/rename?
- RBAC berubah (admin vs self)?

**UI & Frontend**
- Blade mana yang render ini? JS mana yang consume endpoint ini?
- Perlu sync `frontend/resources/js/` → `backend/public/build/js/`?

**Cross-feature**
- Cek `INTEGRATION-MAP.md` — fitur lain bergantung pada data/model ini?

**Output wajib:**
```markdown
## Impact Boundary
- **In scope:** [file1, file2, ...]
- **NOT in scope:** [file3, ...]
- **Downstream effects:** [...]
- **Docs to update:** [...]
```

---

## Fase 4: Implementation — Coding Dengan Presisi

- Hanya sentuh file yang masuk "In scope" dari Fase 3
- Masalah ditemukan di file luar scope → **catat sebagai temuan**, jangan perbaiki tanpa persetujuan user
- Sync JS build jika `frontend/resources/js/` berubah
- Wajib ikuti: `application-security-baseline`, `backend-template-lock`, `migration-discipline`, `bugfix-guardrails`

---

## Fase 5: Verification — Gate Sebelum Selesai

**Gate 1: Test Suite**
```bash
cd backend && php artisan migrate --force
cd backend && php artisan test <suite-terdampak>
cd backend && npx vitest run <scope>  # jika JS/Blade berubah
```

**Gate 2: Manual Smoke**
- Hit endpoint yang diubah → response sesuai?
- Cek 401/403 untuk role yang tidak berhak?
- Cek 422 untuk validasi gagal?

**Gate 3: Closure Checklist**
- [ ] Security: RBAC + guard selaras?
- [ ] Docs: `docs/` yang kontraknya berubah sudah di-update?
- [ ] OpenAPI: `docs/api/openapi.yaml` selaras?

**Gate 4: Anti-Hallucination**
- Ada perubahan di file luar "In scope"? → Apakah disengaja dan sudah diverifikasi?

---

## Fase 6: Reporting — Ringkasan Untuk User

```markdown
## ✅ Task Complete: [judul]

### Summary
[2-3 kalimat]

### Files Changed
- `path/file` — [alasan]

### Impact Confirmation
- ✅ Tidak ada perubahan di luar scope
- ✅ [Feature X] tidak tersenggol — [alasan]

### Test Results
- `php artisan test`: ✅ Pass (X tests)
- `npx vitest run`: ✅ Pass (X tests)
- Migrasi: ✅ Applied / Nothing to migrate

### Docs Updated
- `docs/features/<feature>/README.md`
- `docs/api/<feature>-api.md`

### Caveats / Next Steps
1. [...]
```

---

## Ringkasan

**JANGAN PERNAH:** task → langsung coding.

**SELALU:** STOP → baca sumber → analisis impact → scope → coding → verifikasi → lapor.

**Terakhir diselaraskan dengan:** `.cursor/rules/continuous-prompt-protocol.mdc` per 2026-05-08.
