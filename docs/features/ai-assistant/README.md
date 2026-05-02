# AI Assistant — RBAC-Aware HCM Chatbot

## Status
🚧 Planning (Architecture & Policy Draft)

---

## Ringkasan Bisnis

AI Assistant membantu user HRMS menjawab pertanyaan operasional sehari-hari **langsung dari data aplikasi** tanpa user harus membuka halaman satu per satu.  
Contoh: "Berapa sisa cuti saya?" → AI menjawab dari data balance leave aktual user tersebut.

**Perbedaan kunci dari AI umum:**
- AI **tidak** menjawab dari pengetahuan umum/bebas.
- AI **hanya** boleh mengakses data yang sudah diizinkan untuk user dan tenant aktif.
- Setiap jawaban harus punya **sumber data internal** yang dapat diverifikasi.

---

## Prinsip Arsitektur

| Prinsip | Detail |
|---------|--------|
| Deny by default | Intent yang tidak dikenali atau tidak ada izinnya → ditolak dengan pesan standar |
| Server-side auth | Semua query AI ke endpoint internal harus membawa bearer token + X-Company-Id yang valid; tidak ada bypass dari sisi client/UI |
| Tenant isolation | AI tidak boleh mengakses data lintas company; scope selalu dibatasi tenant aktif user |
| Data provenance | Setiap jawaban yang mengandung angka/fakta wajib menyertakan sumber: endpoint + timestamp sinkronisasi |
| No hallucination policy | Jika endpoint gagal atau data tidak ditemukan, AI wajib menolak menjawab secara definitif, bukan mengarang |
| Audit trail | Setiap sesi tanya-jawab di-log: user, role, tenant, intent, endpoint yang dipanggil, status allow/deny, ringkasan respons |

---

## Model Role yang Berlaku

Mengikuti role matrix utama di [docs/planning/active-hcm-templates-and-permissions.md](../../planning/active-hcm-templates-and-permissions.md).

| Role | Scope AI yang diizinkan |
|------|------------------------|
| **Karyawan (employee)** | Data self-service saja: sisa cuti sendiri, absensi sendiri, payslip sendiri, tiket sendiri |
| **HCM Admin (tenant)** | Semua scope karyawan + ringkasan data tim/company: headcount aktif, leave usage bulan ini, payroll run status, dll. |
| **Global HCM Admin** | Semua scope HCM Admin + lintas tenant (contoh: ringkasan billing, company count) |
| **Anonim** | Tidak ada akses AI |

---

## Flow End-to-End (Happy Path)

```
User ──► [Chat Input]
             │
             ▼
     [Intent Classifier]          ← berjalan di backend; tidak ada LLM call sebelum auth verified
             │
       Intent dikenali?
        ┌────┴────┐
       Tidak      Ya
        │          │
      DENY       [Auth & RBAC Gate]
      (std msg)   │
                 User punya izin?
                  ┌────┴────┐
                 Tidak      Ya
                  │          │
                DENY      [Internal API Call]  ← pakai bearer token + X-Company-Id user aktif
                (403-style)  │
                          Data ditemukan?
                           ┌────┴────┐
                          Tidak      Ya
                           │          │
                     "Data tidak    [Compose Answer]
                      tersedia"      + provenance snippet
                                     │
                                  [Audit Log]
                                     │
                                  User ◄── Jawaban
```

### Contoh konkret: "Berapa sisa cuti saya?"

1. User kirim pertanyaan natural language.
2. Intent classifier mengenali: `leave.balance.self`.
3. Gate cek: user authenticated + izin `leave.view` (minimal self) → allowed.
4. AI call `GET /v1/hcm/leaves/balance?userId={self}` dengan token + tenant user aktif.
5. Response: `{ remaining: 8, period: "2026", type: "annual" }`.
6. AI jawab: *"Sisa cuti tahunan kamu per 2026 adalah **8 hari**."*  
   Disertai metadata: `[Sumber: leave balance API, 01 Mei 2026 22:00]`.
7. Log: `intent=leave.balance.self | endpoint=GET /v1/hcm/leaves/balance | status=allowed | user=xxx | company=yyy`.

---

## Negative Scenarios (Wajib Ditest)

| Skenario | Perilaku yang diharapkan |
|----------|--------------------------|
| Employee tanya sisa cuti orang lain | DENY — `"Kamu tidak punya akses ke data user lain."` |
| User tanpa permission modul leave mencoba tanya cuti | DENY — `"Fitur ini belum tersedia untuk akunmu."` |
| Admin tanya data company lain (cross-tenant) | DENY — AI hanya boleh akses company ID dari token aktif |
| Intent tidak dikenali ("resepnya opor ayam?") | DENY — `"Saya hanya dapat membantu pertanyaan seputar HRMS Arkav."` |
| Endpoint internal gagal (500/timeout) | `"Maaf, data tidak bisa diambil saat ini. Coba beberapa saat lagi."` — tidak boleh mengarang angka |
| Token expired mid-conversation | Re-auth prompt, tidak ada data sensitif di cache client |

---

## Intent Catalog v1 (Self-Service Employee)

Katalog lengkap dipisah di [INTENT-CATALOG.md](./INTENT-CATALOG.md).

| Intent ID | Contoh pertanyaan | Endpoint internal | Min permission |
|-----------|-------------------|-------------------|----------------|
| `leave.balance.self` | Sisa cuti saya berapa? | `GET /v1/hcm/leaves/balance` | `leave.view` |
| `leave.history.self` | Riwayat cuti saya bulan ini | `GET /v1/hcm/leaves?userId=self` | `leave.view` |
| `attendance.today.self` | Sudah absen hari ini? | `GET /v1/hcm/attendance/today` | `attendance.view` |
| `payslip.latest.self` | Payslip bulan lalu | `GET /v1/hcm/payslip/latest` | `payroll.view` (self) |
| `ticket.status.self` | Status tiket saya | `GET /v1/hcm/tickets?scope=self` | `ticket.view` |
| `profile.info.self` | Divisi/jabatan saya apa? | `GET /v1/hcm/employees/self` | authenticated |

## Global Admin Scope (Implemented)

| Intent ID | Contoh pertanyaan | Data source internal |
|-----------|-------------------|----------------------|
| `saas.company.summary` | Berapa company aktif sekarang? | `Company` summary lintas tenant |
| `saas.billing.summary` | Total revenue bulan ini berapa? | `Invoice` + `Subscription` summary |
| `saas.tax.monthly` | Berapa pajak yang kita bayarkan ke pemerintah bulan ini? | `PlatformRevenueTransaction` (cleared tax) + `BillingTaxCalculationService` |

---

## Kontrak API AI

Endpoint AI akan ditempatkan di prefix `/v1/hcm/ai/` untuk isolasi.

| Method | Path | Keterangan |
|--------|------|------------|
| `POST` | `/v1/hcm/ai/chat` | Kirim pesan, terima jawaban + metadata sumber |
| `GET` | `/v1/hcm/ai/intents` | List intent yang tersedia untuk role user aktif |
| `GET` | `/v1/hcm/ai/history` | Riwayat percakapan user (paginated) |

Request body `/v1/hcm/ai/chat`:
```json
{
  "message": "Berapa sisa cuti saya?",
  "session_id": "optional-uuid-untuk-konteks-multi-turn"
}
```

Response envelope:
```json
{
  "success": true,
  "data": {
    "reply": "Sisa cuti tahunan kamu per 2026 adalah 8 hari.",
    "intent": "leave.balance.self",
    "sources": [
      {
        "label": "Leave Balance",
        "endpoint": "GET /v1/hcm/leaves/balance",
        "retrieved_at": "2026-05-01T22:00:00Z"
      }
    ],
    "allowed": true,
    "session_id": "uuid"
  }
}
```

Response jika ditolak (RBAC / unknown intent):
```json
{
  "success": true,
  "data": {
    "reply": "Kamu tidak punya akses ke data tersebut.",
    "intent": "leave.balance.other",
    "allowed": false,
    "sources": []
  }
}
```

> `success: true` selalu (bukan 403 HTTP) karena penolakan AI adalah jawaban valid, bukan error teknis.  
> HTTP 401 tetap berlaku jika token tidak valid sama sekali.

---

## Guardrail Keamanan

1. **Prompt injection** — input user di-sanitize sebelum masuk ke LLM; instruksi system prompt tidak pernah di-expose ke user.
2. **Data leakage** — AI hanya boleh meng-quote data dari internal API call sesi aktif; tidak ada caching data lintas user/session.
3. **Rate limiting** — endpoint `/v1/hcm/ai/chat` dibatasi: misal `throttle:30,1` per user per menit.
4. **LLM output filtering** — sebelum jawaban dikirim ke user, filter: (a) tidak mengandung data user lain, (b) tidak mengandung instruksi sistem tersembunyi.
5. **System prompt hardening** — system prompt AI wajib menyertakan instruksi: hanya jawab berdasarkan context data yang diberikan; tolak pertanyaan di luar scope HRMS.

---

## Existing vs Target

| Aspek | Existing | Target |
|-------|----------|--------|
| Search HRMS | Input statis → sekarang sudah live via `global-search-data.js` | AI melengkapi search: bisa jawab pertanyaan natural language, bukan hanya link navigasi |
| Data retrieval | User buka halaman masing-masing | AI fetch data atas nama user melalui endpoint resmi yang sudah ada |
| RBAC | Sudah diterapkan di semua endpoint HCM | AI mengikuti RBAC yang sama; tidak ada bypass |
| Audit | API activity log (via standard HTTP log) | Tambah AI-specific log: intent, allow/deny, source endpoint |

---

## Gap & Ambiguity yang Perlu Diputuskan

| # | Gap | Opsi | Status |
|---|-----|------|--------|
| 1 | LLM provider mana yang dipakai? | OpenAI, Ollama (self-hosted), atau model custom | ✅ **Ollama (self-hosted)** — data tidak keluar server, provider-agnostic via `.env` |
| 2 | Multi-turn conversation: apakah konteks disimpan di DB atau stateless per request? | DB-persisted vs stateless | ❓ Belum diputuskan |
| 3 | Scope bahasa: Bahasa Indonesia saja atau bilingual? | ID-only dulu, EN optional | ❓ Belum diputuskan |
| 4 | UI placement: floating button, sidebar panel, atau halaman tersendiri? | Floating button (mirip chat support) | ❓ Belum diputuskan |
| 5 | Employee scope self: apakah AI boleh suggest action (submit cuti) atau hanya read? | Read-only dulu (v1), action di v2 | ✅ **Read-only v1** |

---

## Dokumen Terkait

- [RBAC-POLICY.md](./RBAC-POLICY.md) — tabel allow/deny per intent per role
- [INTENT-CATALOG.md](./INTENT-CATALOG.md) — katalog intent lengkap + mapping endpoint
- Role matrix: [docs/planning/active-hcm-templates-and-permissions.md](../../planning/active-hcm-templates-and-permissions.md)
- API contract: [docs/api/openapi.yaml](../../api/openapi.yaml)
