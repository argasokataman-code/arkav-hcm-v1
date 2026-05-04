# Employee Allowance Governance Feature

## Ringkasan Bisnis

Employee Allowance Governance adalah modul operasional untuk mengelola tunjangan karyawan umum (di luar BPJS dan PPh21) secara terstruktur, teraudit, dan langsung terhubung ke payroll.

Modul ini dipakai untuk:

1. Menetapkan master jenis tunjangan umum perusahaan (contoh: transport, makan, komunikasi, jabatan, kerja lapangan).
2. Menetapkan assignment tunjangan ke karyawan (langsung) atau by rule (grade/jabatan/divisi).
3. Menjaga validitas periode berlaku, nominal, dan status aktif agar payroll tidak salah hitung.
4. Menyediakan deteksi kepatuhan tunjangan yang actionable (siapa belum lengkap, rule mana overlap, allowance mana expired tapi masih aktif).

## Akses

Status runtime saat ini: sudah ada halaman dedicated Allowance Governance.

Surface existing yang terkait tunjangan saat ini:

1. Employee Salary: halaman kompensasi per karyawan (fixed allowance).
2. Payroll Items: katalog item addition/deduction yang dipakai payroll runtime.
3. Payroll Salary Components: master komponen sebagai source-of-truth istilah komponen.
4. Employee Allowance Governance: policy + assignment + compliance report dedicated.

## UI Aktif

UI runtime aktif:

1. /employee-allowance-governance
2. /employee-allowance-governance/policies
3. /employee-allowance-governance/assignments
4. /employee-allowance-governance/reports

## Flow Bisnis End-to-End

1. HR Admin menyiapkan master allowance policy (nama tunjangan, kode, taxable/non-taxable, fixed/formula, frequency, effective period).
2. Sistem memvalidasi policy agar tidak overlap secara invalid pada scope yang sama.
3. HR Admin melakukan assignment tunjangan ke karyawan atau scope organisasi (grade/jabatan/divisi).
4. Sistem menyimpan assignment sebagai record versioned (immutable history), bukan overwrite diam-diam.
5. Saat payroll draft dihitung, engine mengambil assignment allowance aktif per periode payroll.
6. Payroll draft menghasilkan line item allowance per karyawan dengan policy snapshot yang dipakai saat run itu.
7. Compliance engine memeriksa gap allowance:
   - karyawan aktif tanpa tunjangan wajib,
   - assignment overlap,
   - assignment expired tapi masih dipakai,
   - rule taxable yang tidak sinkron dengan komponen pajak.
8. Halaman compliance menampilkan score + detail karyawan/rule yang menyebabkan gap agar mudah ditindaklanjuti.

## Lifecycle Dan Keputusan Bisnis

Lifecycle policy allowance (disarankan):

1. draft
2. active
3. superseded
4. archived

Lifecycle assignment allowance:

1. draft assignment
2. active assignment
3. suspended assignment
4. ended assignment

Keputusan bisnis utama:

1. Allowance umum dipisah dari BPJS/PPh21 agar governance lebih jelas dan audit trail tidak tercampur.
2. Owner tenant default tidak masuk scope allowance payroll kecuali ada flag eksplisit bahwa owner ikut payroll.
3. Assignment allowance harus period-based (effective start/end) agar payroll historis bisa direkonstruksi.
4. Perubahan nominal setelah payroll finalized tidak mengubah run lama; perubahan berlaku untuk run berikutnya atau recalculation yang sah.

## Integrasi

Integrasi existing yang harus dipertahankan:

1. Payroll Salary Components untuk kategori dan metadata komponen.
2. Payroll Items untuk line item runtime addition.
3. Payroll Runs untuk perhitungan draft/final.
4. Tax Governance untuk taxable flag alignment.
5. BPJS Governance untuk pemisahan domain statutory vs allowance umum.

Integrasi target:

1. Allowance policy/assignment menjadi input resmi PayrollDraftBuilder.
2. Compliance allowance tersedia di overview payroll governance.
3. Export evidence allowance untuk audit internal.

## Kontrak API

Status saat ini: endpoint dedicated allowance governance sudah aktif.

Kontrak runtime aktif:

1. GET /v1/hcm/allowance-governance/reference
2. GET /v1/hcm/allowance-governance/policies
3. GET /v1/hcm/allowance-governance/policies/history
4. POST /v1/hcm/allowance-governance/policies
5. PATCH /v1/hcm/allowance-governance/policies/{policyRef}
6. POST /v1/hcm/allowance-governance/policies/{policyRef}/activate
7. GET /v1/hcm/allowance-governance/assignments
8. POST /v1/hcm/allowance-governance/assignments
9. PATCH /v1/hcm/allowance-governance/assignments/{assignmentRef}
10. GET /v1/hcm/allowance-governance/reports/compliance
11. GET /v1/hcm/allowance-governance/reports/compliance/export

## Existing Vs Target

Existing saat ini:

1. Tunjangan umum kini punya surface governance dedicated, tetapi tetap terintegrasi ke employee salary/payroll items/salary components.
2. Governance score allowance dan detail gap per karyawan sudah tersedia di endpoint compliance report.
3. Policy lifecycle allowance baseline sudah berjalan (draft/active/superseded/archived).

Target modul:

1. Satu modul governance khusus tunjangan umum dengan policy + assignment + compliance.
2. Score kepatuhan allowance dengan detail masalah per karyawan/rule.
3. Riwayat perubahan policy/assignment yang siap audit.
4. Koneksi langsung ke payroll draft agar perubahan kebijakan terkontrol.

## Cross-check Role Dan Permission

Target role permission matrix:

1. allowance.policy.view
2. allowance.policy.manage
3. allowance.assignment.view
4. allowance.assignment.manage
5. allowance.report.view
6. allowance.report.export

Catatan scope:

1. HR Admin: full manage.
2. Payroll Admin: view + report + assignment manage (opsional sesuai kebijakan tenant).
3. Employee: tidak boleh mutate policy/assignment, hanya bisa melihat allowance dirinya jika fitur self-service diaktifkan.

## Baseline Default Allowance Indonesia

Untuk menghilangkan blocker keputusan awal, sistem menyiapkan baseline default allowance yang umum dipakai perusahaan di Indonesia. Baseline ini adalah starter pack bawaan sistem, bukan aturan kaku; owner tenant tetap bebas menambah, menonaktifkan, atau menghapus sesuai kebijakan internal.

Daftar baseline default yang disarankan:

1. Tunjangan Transport
2. Tunjangan Makan
3. Tunjangan Komunikasi (pulsa/internet)
4. Tunjangan Jabatan
5. Tunjangan Kehadiran
6. Tunjangan Shift
7. Tunjangan Kerja Lapangan/Site

Parameter default baseline (seed awal):

1. Status awal: active.
2. Jenis: fixed amount (bukan formula) untuk fase baseline.
3. Frequency: monthly.
4. Effective start: tanggal aktivasi tenant.
5. Effective end: null (sampai dinonaktifkan).

Aturan override tenant:

1. Owner/HCM Admin bisa menonaktifkan allowance default yang tidak dipakai.
2. Owner/HCM Admin bisa menambah allowance baru di luar baseline.
3. Owner/HCM Admin bisa mengubah nama tampilan, nominal default, dan scope assignment.
4. Semua perubahan tetap dicatat di history policy/assignment untuk audit.

## Gap Dan Catatan Lanjutan

Gap utama yang harus ditutup sebelum implementasi runtime penuh:

1. Formula rule untuk allowance variable (mis. prorate, hadir minimum, site allowance) perlu definisi eksplisit.
2. Mekanisme override owner ikut payroll lintas payroll engine masih perlu parameterisasi tenant-level.

## Status

- Status implementation: in progress (runtime baseline active)
- Tracker: tracker.md
- Snapshot saat ini: policy + assignment + compliance baseline sudah aktif di runtime tenant-admin, lanjutan fokus ke variable formula dan hardening integrasi payroll engine.
