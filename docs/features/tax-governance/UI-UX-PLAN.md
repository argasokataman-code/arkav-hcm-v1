# Tax Governance UI/UX Plan

## Status

- Tanggal: 2026-04-27
- Status: planned (execution pending)
- Scope: tenant statutory tax + platform billing tax
- Referensi keputusan: [DECISION.md](DECISION.md)
- Referensi tracking fase: [tracker.md](tracker.md)

## Tujuan UI/UX

1. Menghilangkan ambiguity bahwa `/tax-rates` saat ini belum authoritative.
2. Menyediakan alur authoring policy yang aman: draft -> review -> approve -> publish.
3. Menyediakan governance dashboard lintas tenant untuk role global tanpa melanggar tenant boundary.
4. Menyediakan reporting yang jelas dipisah antara domain tenant statutory tax dan platform billing tax.

## Persona dan Kebutuhan

1. Tenant HCM Admin
   - Butuh membuat, submit, dan memantau policy tenant sendiri.
   - Butuh anomaly dashboard dan export audit tenant.
2. Payroll Operator
   - Butuh lihat policy aktif + warning saat payroll run.
   - Tidak boleh mengubah policy baseline.
3. Tenant Auditor
   - Butuh evidence read-only per periode.
4. Platform Global HCM Admin
   - Butuh observability lintas tenant + break-glass terkontrol.
5. Platform Billing Admin
   - Butuh kelola policy pajak billing layanan aplikasi (monthly/yearly/custom).

## Peta Screen (Planned)

1. Tax Governance Landing (`/tax-rates`)
   - Tujuan: entry point governance, bukan tabel statis.
   - Widget inti: status policy aktif, latest publication, anomaly count, quick action.
   - Mandatory banner: source of truth runtime + status wiring.
2. Tenant Policy List (`/tax-rates/policies`)
   - Tujuan: daftar policy versioned per tenant.
   - Tabel: policy code, version, status lifecycle, effective window, updated by.
   - Filter: status, periode efektif, author, updated range.
3. Policy Editor (`/tax-rates/policies/{uuid}/edit`)
   - Tujuan: buat/edit draft policy.
   - Area: taxonomy mapping, rate schedule, validation preview, impact summary.
   - Guard: unsaved changes prompt + validation inline.
4. Approval Inbox (`/tax-rates/approvals`)
   - Tujuan: approve/reject dengan maker-checker.
   - Data: pending item, maker, diff summary, reason, effective date.
5. Publication Timeline (`/tax-rates/publications`)
   - Tujuan: jejak publish/supersede/void immutable.
   - Data: before-after snapshot, actor, timestamp, reason code.
6. Tenant Audit Reports (`/tax-rates/reports`)
   - Tujuan: export tenant self-audit pack.
   - Output: policy history, anomaly summary, payroll tax evidence.
7. Global Governance Dashboard (`/tax-rates/governance`)
   - Tujuan: observability lintas tenant subscribe.
   - View: heatmap risk, top anomaly tenant, stale policy monitor.
8. Platform Billing Tax Policy (`/tax-rates/platform-billing/policies`)
   - Tujuan: kelola tax policy billing platform.
   - Segmentasi: monthly/yearly/custom contract.
9. Platform Billing Tax Reports (`/tax-rates/platform-billing/reports`)
   - Tujuan: monitoring billing tax lintas tenant.
   - Output: tax by package, tax by cycle, invoice snapshot reconciliation.

## Alur UX Kritis

1. Buat policy tenant baru
   - Create draft -> input taxonomy/rates -> simpan draft -> submit approval.
2. Approve dan publish policy
   - Approver review diff -> approve -> publish dengan effective date.
   - SoD wajib: maker tidak bisa approve publish item yang dibuat sendiri.
3. Jalankan payroll saat anomaly ada
   - Operator melihat anomaly warning (missing tax profile/drift).
   - Operator bisa lanjut jika policy published valid, namun warning tetap tercatat.
4. Monitoring lintas tenant oleh global admin
   - Buka dashboard -> drill-down tenant -> lihat evidence read-only.
   - Mutasi tenant lain hanya lewat break-glass flow terkontrol.
5. Billing tax monitoring
   - Platform Billing Admin memonitor tax outcome invoice per cycle.

## State, Warning, dan Error UX

1. Empty states
   - Belum ada policy published.
   - Belum ada approval item.
   - Belum ada data anomaly pada periode terpilih.
2. Warning states
   - `missingTaxProfile` pada employee.
   - component drift pada taxable gross.
   - policy stale (melewati ambang review period).
3. Error states
   - unauthorized action (403) dengan copy sesuai role.
   - tenant boundary violation (403/404 masked).
   - optimistic lock conflict saat update draft.
4. Success feedback
   - draft saved, submitted, approved, published, superseded.

## Prinsip UX Mandatory

1. Semua aksi sensitif tampilkan confirmation dialog + reason capture jika relevan.
2. Visibility bukan authorization; backend tetap source of truth access control.
3. Semua entitas publik yang ditampilkan menggunakan UUID.
4. Audit trace harus dapat diakses dari UI tanpa query manual database.

## Cross-check Checklist Antar Tim

1. Product
   - Flow lifecycle dan copy status bisnis disetujui.
   - Domain boundary tenant statutory vs platform billing tervalidasi.
2. UX/UI
   - Screen map, empty/error states, dan warning copy final.
   - Semua role punya jalur yang eksplisit dan tidak ambigu.
3. Frontend
   - Routing, state management, dan permission-aware UI terimplementasi.
   - Banner/warning anti-misleading pada `/tax-rates` aktif.
4. Backend
   - Endpoint UUID-only, SoD, scope check tenant, dan audit events aktif.
5. QA
   - Positive + negative path test per role tersedia.
   - Regression test payroll impact dan report export tersedia.
6. Security/Compliance
   - Break-glass, immutable audit trail, dan authorization boundary diverifikasi.

## Definition of Done UI/UX

1. Tidak ada lagi halaman tax governance yang bersifat dummy tanpa status eksplisit.
2. Role tidak hanya dibatasi di UI, tetapi juga tervalidasi server-side.
3. User dapat memahami status policy aktif dan dampaknya ke payroll dari UI.
4. Tenant dan platform dapat menghasilkan report sesuai domain masing-masing.