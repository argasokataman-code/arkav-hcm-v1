# Approval Settings

**Status:** ✅ Phase 1–4 Complete. API + UI wired. Leave, Overtime, Resignation, Termination integrated. Phase 5 (Expense/Offer) pending modul baru.

---

## 1. Ringkasan Bisnis

Approval Settings adalah fitur konfigurasi yang memungkinkan **HR Admin/Owner** mengatur **siapa yang menjadi approver** dan **pola approval** (sequential/simultaneous) untuk setiap jenis request di HCM.

Tanpa fitur ini, approver di setiap modul dikonfigurasi secara hardcode atau manual per-request — tidak scalable untuk perusahaan dengan struktur hierarki dinamis.

### Masalah yang Diselesaikan

| Masalah Sebelumnya | Dampak | Status |
|---|---|---|
| Leave: approver tidak bisa dikonfigurasi via UI | HR harus set approver manual; audit trail kosong | ✅ Fixed |
| Overtime: siapa yang berhak approve tidak terkonfigurasi | Approval bisa bypass atau tidak konsisten | ✅ Fixed |
| Resignation: tidak ada `approved_by` | Siapa yang approve tidak tercatat | ✅ Fixed |
| Termination: approval hardcoded ke role admin | Tidak fleksibel untuk delegasi | ✅ Fixed |
| Expense & Offer: belum ada modul | Butuh approval framework yang siap saat dibangun nanti | ⏳ Pending |

---

## 2. Aktor & Role

| Role | Akses | Keterangan |
|---|---|---|
| **Super Admin** | Full akses semua company | Tidak dibatasi fitur package |
| **HCM Admin / Owner** | CRUD approval config + approve/reject requests | Harus punya feature package relevan |
| **Approver** (configured) | Approve/reject request yang ditugaskan | Ditentukan via Approval Settings |
| **Employee** | Mengajukan request, menerima notifikasi hasil | Tidak bisa edit config |

---

## 3. Alur Bisnis End-to-End

### 3a. Konfigurasi Approval

```
HR Admin buka halaman /approval-settings
  → Pilih module (Leave, Overtime, dll)
  → Pilih mode: Sequence / Simultaneous
  → Pilih approvers (dari daftar employee aktif perusahaan)
  → Simpan → API PUT /v1/hcm/approval-settings/{module}
```

### 3b. Approval Flow (Sequence Mode)

```
Employee submit request (status: pending)
  → Level 1 approver notified
  → Level 1 approve → Level 2 notified
  → Level 2 approve → ... → Level N approve
  → Request status: approved
  → Employee notified

Jika ada yang reject di level mana pun:
  → Request status: declined
  → Employee notified
  → Flow berhenti
```

### 3c. Approval Flow (Simultaneous Mode)

```
Employee submit request (status: pending)
  → Semua approver notified bersamaan
  → Approver mana pun bisa approve
  → Request status: approved
  → Employee notified

Jika ada yang reject:
  → Request status: declined
  → Employee notified
```

---

## 4. Status Lifecycle

### Status Request

```
pending → [approved | declined]
     ↕ (revisi — future)
```

### Status Approver Individual

```
pending → [approved | declined]
```

Tidak ada status `cancelled` di level approver — cancellation hanya di level request.

---

## 5. Module Coverage

| Module | Status Integrasi | Route Prefix | Feature Package |
|---|---|---|---|
| **leave** | ✅ Complete | `v1/hcm/leave-requests` | `leave_management` |
| **overtime** | ✅ Complete | `v1/hcm/overtime-requests` | `overtime` |
| **resignation** | ✅ Complete | `v1/hcm/resignations` | `resignation` |
| **termination** | ✅ Complete | `v1/hcm/terminations` | `termination` |
| **expense** | ⏳ Future | — | — |
| **offer** | ⏳ Future | — | — |

### Dynamic Module Visibility

Halaman settings hanya render section untuk module yang **aktif di package company**. Mekanisme via `Company::hasFeature(string $featureCode)`.

| Section UI | Feature Code | Tier |
|---|---|---|
| Leave Approval | `leave_management` | MVP (selalu ada) |
| Overtime Approval | `overtime` | Add-on |
| Resignation Approval | `resignation` (bagian `employee_lifecycle`) | Add-on |
| Termination Approval | `termination` | Add-on |
| Expense Approval | — | Future |
| Offer Approval | — | Future |

---

## 6. Decision Tree

```
Approval Settings page → /approval-settings
├── Company punya fitur leave_management?
│   ├── Ya → render Leave Approval section
│   └── Tidak → skip
├── Company punya fitur overtime?
│   ├── Ya → render Overtime Approval section  
│   └── Tidak → skip
├── Company punya fitur resignation?
│   ├── Ya → render Resignation Approval section
│   └── Tidak → skip
├── Company punya fitur termination?
│   ├── Ya → render Termination Approval section
│   └── Tidak → skip
└── Semua section → GET /v1/hcm/approval-settings → populate saved values
```

```
Approval Decision (saat approver approve/reject)
├── Mode sequence?
│   ├── Approve, masih ada level berikutnya
│   │   → Update approver status ke approved
│   │   → Notify next level approver
│   │   → Request tetap pending
│   ├── Approve, ini level terakhir
│   │   → Update approver status ke approved
│   │   → Update request status ke approved
│   │   → Notify employee
│   └── Reject di level mana pun
│       → Update approver status ke declined
│       → Update request status ke declined
│       → Notify employee
│       → Flow berhenti
└── Mode simultaneous?
    ├── Approve
    │   → Update approver status ke approved
    │   → (Jika ini adalah approval pertama) Update request ke approved
    │   → Notify employee
    └── Reject
        → Update approver status ke declined
        → Update request status ke declined
        → Notify employee
```

---

## 7. Notifikasi

| Event | Trigger | Penerima |
|---|---|---|
| `leave.approval.requested` | Leave request dibuat → pending | Approver level 1 |
| `leave.approved` | Leave request disetujui | Employee pengaju |
| `leave.rejected` | Leave request ditolak | Employee pengaju |
| `leave.approval.next_level` | Level 1 approve, ada level 2 | Approver level 2 |
| `overtime.approval.requested` | Overtime request dibuat | Approver |
| `overtime.approved` | Overtime request disetujui | Employee |

Notifikasi dikirim via queue (Laravel Notification + database channel). User bisa mute via notifikasi preferences.

---

## 8. Gap & Asumsi Eksplisit

| Item | Status |
|---|---|
| "Simultaneous" = semua approve atau cukup satu? | **Belum ditentukan** — perlu keputusan product owner |
| Config berlaku per-department atau per-company? | **Asumsi: per-company** |
| Approver bisa di-delegate (leave of absence)? | **Out of scope** Phase 1–3 |
| Expense & Offer modul | **Belum ada di runtime** — butuh dibangun dari awal |

---

## Status Implementasi

| Phase | Scope | Status |
|---|---|---|
| 1 | Foundation (migration, model, CRUD, API, Blade) | ✅ Complete |
| 2 | Integrasi Leave (populate approvals, chain flow) | ✅ Complete |
| 3 | Integrasi Overtime (config approvers, notifikasi) | ✅ Complete |
| 4 | Integrasi Resignation & Termination | ✅ Complete |
| 5 | Expense & Offer (butuh modul baru) | ⏳ Pending |

---

## Dokumen Terkait

- [IMPLEMENTATION.md](./IMPLEMENTATION.md) — arsitektur, DB, service, integrasi
- [API.md](./API.md) — kontrak API lengkap
- [E2E-TESTING.md](./E2E-TESTING.md) — skenario manual browser
