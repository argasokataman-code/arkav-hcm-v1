# Migrasi Cursor Rules → GitHub Copilot Instructions

**Tanggal:** April 2026  
**Status:** ✅ Complete — Semua `.cursor/rules/` sudah di-mirror ke `copilot-instructions.md`

---

## 📋 File yang Telah Dibuat

| Lokasi | Scope | Basis | Tujuan |
|---|---|---|---|
| `/copilot-instructions.md` | Root/Workspace-level | `00-session-preamble.mdc`, `AGENTS.md`, semua rules | Instruksi utama project GitHub Copilot |
| `/backend/copilot-instructions.md` | Backend-specific | `backend-template-lock.mdc`, `application-security-baseline.mdc` | Best practice backend Laravel |
| `/frontend/copilot-instructions.md` | Frontend-specific | `backend-template-lock.mdc`, `no-hardcoded-dummy-template-data.mdc` | Best practice frontend Vue/JS |

---

## 🔄 Mapping: Cursor Rules → Copilot Instructions

### Root Level (`/copilot-instructions.md`)
| Cursor Rule | Bagian di Copilot | Deskripsi |
|---|---|---|
| `00-session-preamble.mdc` | §1–3 "Ikhtisar & Rules Wajib" | Preamble project, checklist workflow |
| `AGENTS.md` | §4 "Security Checklist" | Agent guidelines, tool references |
| `application-security-baseline.mdc` | §4 "Security Checklist" | Security awareness per task |
| `web-hcm-route-security.mdc` | §4 "Security Checklist", tabel | Guard web publik, whitelist |
| `backend-template-lock.mdc` | §5 "Template & UI Consistency" | Template lock + pola integrasi API |
| `no-hardcoded-dummy-template-data.mdc` | FAQ & gotchas | Data dummy jangan hardcode |
| `role-permissions-with-features.mdc` | FAQ, referensi | Matriks RBAC HCM aktif |
| `documentation-sync-after-development.mdc` | §3 "Workflow: Sebelum Close Task" | Docs sync checklist |
| `development-closure-checklist.mdc` | §3 "Workflow: Sebelum Close Task" | Final checklist: security + docs + OpenAPI |
| `openapi-collection-sync.mdc` | §3 "Workflow: Sebelum Close Task" | OpenAPI/Swagger sync requirement |
| `quality-anomaly-pass.mdc` | §2 "Session Preamble" | QA checklist sebelum close |
| `migration-discipline.mdc` | — | (Migrasi DB spesifik; dapat di-document terpisah) |
| `api-spec-*-parity.mdc`, `*-validation-parity.mdc` | Referensi backend | Validasi spec sync |

### Backend (`/backend/copilot-instructions.md`)
| Cursor Rule | Bagian | Deskripsi |
|---|---|---|
| `backend-template-lock.mdc` | §1–7 | Template lock, Blade pattern, integrasi API, RBAC web |
| `application-security-baseline.mdc` | §2 "Security Checklist" | Auth, input, secret, HTTP, UX security |

### Frontend (`/frontend/copilot-instructions.md`)
| Cursor Rule | Bagian | Deskripsi |
|---|---|---|
| `backend-template-lock.mdc` | §1 "UI Consistency", API Integration | Pola modal, no custom UI, API pattern |
| `no-hardcoded-dummy-template-data.mdc` | §3 "No Hardcoded Dummy Data" | Hapus dummy data hardcode |

---

## 🚀 Cara Pakai

### Untuk GitHub Copilot Chat (VS Code)

**Opsi 1: Auto-load root level**
- Copilot automatically reads `/copilot-instructions.md` saat kamu buka chat untuk **any file** di workspace
- Tidak perlu referensi manual — instruksi sudah jadi constraint

**Opsi 2: Folder-specific instruction**
- Chat di folder `backend/` → Copilot juga baca `backend/copilot-instructions.md`
- Chat di folder `frontend/` → Copilot juga baca `frontend/copilot-instructions.md`

**Opsi 3: Explicit reference (opsional)**
```
@copilot-instructions

# atau direktify mention file

@/backend/copilot-instructions.md help me understand API pattern
```

---

## 📖 Hirarki Reading (Suggested)

1. **Mulai dengan:** `/copilot-instructions.md` (root) → Overview project, rules mandatory
2. **Untuk backend task:** `/backend/copilot-instructions.md` → API pattern, security, Blade
3. **Untuk frontend task:** `/frontend/copilot-instructions.md` → UI consistency, no dummy data API integration
4. **Untuk dokumentasi:** Refer ke `documentation-sync-after-development.mdc` (original, dalam `.cursor/rules/`)
5. **Raw checklist:** Lihat rule file original di `.cursor/rules/` untuk detail penuh

---

## ✅ Checklist: Setup Complete

- [ ] Read `/copilot-instructions.md` sebagai base
- [ ] Bookmark `/backend/copilot-instructions.md` untuk API development
- [ ] Bookmark `/frontend/copilot-instructions.md` untuk UI development
- [ ] Verify Copilot reads file automatically (chat dialog test)
- [ ] Delete atau archive `.cursor/rules/` jika sudah tidak perlu (optional — keep as reference)

---

## 📝 Notes

### Mengapa berbeda format?

**Cursor `.mdc` files:**
- YAML frontmatter: `description`, `alwaysApply` — auto-inject ke context
- Panjang, detail, struktur ringkas

**Copilot `copilot-instructions.md`:**
- Standard Markdown (no YAML auto-inject)
- Emoji header untuk scannability
- Lebih verbose untuk clarity di chat context
- Bisa langsung reference di prompt

### Maintenance

Jika rules di `.cursor/rules/` diupdate:
- **Manual sync** ke `copilot-instructions.md` (tidak otomatis)
- Suggested: Maintain dua copy (lock `.cursor/` sebagai master, update `copilot-instructions.md` sesuai)
- Atau: Delete `.cursor/` — gunakan hanya Copilot instructions (simplified, one source of truth)

### Existing `.cursor/` Files

Tetap ada untuk:
- **Reference manual** (kamu bisa buka file)
- **Cursor IDE users** (yang pakai Cursor editor)
- **Backup** (jika perlu revert)

Tidak mengganggu Copilot — dua sistem bisa coexist.

---

## 🔗 Related Rules (Original Files di `.cursor/rules/`)

Rules yang **perlu manual read** (bukan fully di-mirror):
- `migration-discipline.mdc` — Best practice migrasi DB
- `api-spec-docs-sync-per-change.mdc` — Detail sync API docs
- `api-spec-first-validation-parity.mdc` — Validation parity check
- `openapi-collection-sync.mdc` — OpenAPI update detail
- `quality-anomaly-pass.mdc` — Full QA checklist
- `role-permissions-with-features.mdc` — Full RBAC matriks

Cek file original `.cursor/rules/` jika butuh detail spesifik yang tidak tercakup di Copilot instructions.

---

## 🎯 Next Steps

1. **Verify:** Buka chat Copilot, pastikan file dimention/loaded otomatis
2. **Test:** Coba development task (cth: "create new API endpoint") → Copilot seharusa refer constraint dari instructions
3. **Feedback:** Jika ada rule yang tidak clear atau perlu expand — update `copilot-instructions.md`
4. **Sync:** Maintain dua sistem (Cursor rules + Copilot instructions) atau pilih satu master

---

Generated: April 2026
