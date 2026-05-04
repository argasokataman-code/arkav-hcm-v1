# Employee Allowance Governance - Implementation Blueprint

## Tujuan Implementasi

Dokumen ini menjadi blueprint teknis implementasi modul Employee Allowance Governance agar konsisten dengan pola governance BPJS dan PPh21.

Tujuan utama:

1. Menjadikan tunjangan umum sebagai domain governance terpisah.
2. Menjamin payroll draft memakai data allowance yang valid, versioned, dan audit-ready.
3. Menyediakan compliance score + detail gap untuk action cepat tim HR/Payroll.

## Scope Fase Awal (Baseline)

1. Master allowance policy CRUD + lifecycle sederhana (draft/active/superseded/archived).
2. Assignment allowance ke karyawan (manual) dengan effective date.
3. Integrasi ke payroll draft untuk assignment allowance aktif.
4. Compliance report allowance dengan detail entitas tidak patuh.

## Scope Fase Lanjutan

1. Assignment by rule (grade/jabatan/divisi/lokasi).
2. Formula allowance variable (prorate attendance, threshold kehadiran, dsb).
3. Approval workflow perubahan allowance sensitif.
4. Evidence export JSON/PDF untuk audit.

## Usulan Struktur Data

1. hcm_allowance_policies
   - uuid
   - company_id
   - code
   - name
   - allowance_type (fixed, variable)
   - taxable_flag
   - default_amount
   - formula_payload (json nullable)
   - effective_start_date
   - effective_end_date
   - status (draft, active, superseded, archived)
   - created_by_user_id
   - updated_by_user_id
2. hcm_allowance_policy_histories
   - uuid
   - company_id
   - policy_uuid
   - action_type
   - snapshot (json)
   - changed_by_user_id
   - created_at
3. hcm_allowance_assignments
   - uuid
   - company_id
   - policy_uuid
   - user_id
   - amount_override (nullable)
   - effective_start_date
   - effective_end_date
   - status (draft, active, suspended, ended)
   - created_by_user_id
   - updated_by_user_id
4. hcm_allowance_assignment_histories
   - uuid
   - company_id
   - assignment_uuid
   - action_type
   - snapshot (json)
   - changed_by_user_id

## Usulan Surface API

1. GET /v1/hcm/allowance-governance/policies
2. POST /v1/hcm/allowance-governance/policies
3. PATCH /v1/hcm/allowance-governance/policies/{policyRef}
4. POST /v1/hcm/allowance-governance/policies/{policyRef}/activate
5. GET /v1/hcm/allowance-governance/assignments
6. POST /v1/hcm/allowance-governance/assignments
7. PATCH /v1/hcm/allowance-governance/assignments/{assignmentRef}
8. GET /v1/hcm/allowance-governance/reports/compliance

Envelope response:

1. success
2. data
3. error

## Rule Bisnis Inti

1. Owner tenant default exclude dari scope allowance payroll (kecuali override explicit).
2. Satu user tidak boleh punya assignment active overlap untuk policy yang sama pada periode yang sama.
3. Policy archived tidak boleh dipakai assignment baru.
4. Semua perubahan policy/assignment wajib menulis history snapshot.
5. Payroll finalized tidak berubah retroaktif; allowance policy baru berlaku run berikutnya.

## Integrasi Ke Payroll Draft

Checklist integrasi:

1. Ambil assignment allowance aktif sesuai payroll period.
2. Resolve nominal (override dulu, lalu default policy).
3. Inject ke payroll line items sebagai addition komponen allowance.
4. Simpan policy/assignment snapshot minimal di payroll line metadata agar audit replay aman.

## Smart Compliance Output

Output compliance yang disarankan:

1. score
2. checks
3. detail per check (evidence)
4. non_compliant_employees

Contoh gap yang dideteksi:

1. allowance_assignment_missing
2. allowance_assignment_overlap
3. allowance_assignment_expired_active
4. taxable_flag_mismatch_with_tax_component

## Risk Dan Mitigasi

1. Risiko duplikasi assignment
   - mitigasi: unique guard + overlap validator server-side.
2. Risiko drift taxable logic
   - mitigasi: cross-check ke Tax Governance saat save policy.
3. Risiko payroll retroactive mutation
   - mitigasi: snapshot policy/assignment per payroll line.

## Rencana Testing

Backend test minimum:

1. policy lifecycle create/update/activate/supersede
2. assignment overlap rejection
3. owner exclusion from allowance compliance
4. compliance report detail payload contains non_compliant_employees

Frontend test minimum:

1. render compliance score
2. render detail gap employee list
3. add/edit assignment modal flow

## Definition Of Done

1. API + UI + payroll integration berjalan untuk baseline allowance fixed.
2. Compliance score + detail gap tampil di overview.
3. Docs API (OpenAPI + feature API doc) sinkron.
4. Test backend/frontend terkait allowance governance lulus.
