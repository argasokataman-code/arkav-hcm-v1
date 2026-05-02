# AI Assistant — Intent Catalog v1

Katalog ini mendefinisikan setiap intent yang dikenali AI Assistant:  
mapping dari pertanyaan natural language → endpoint internal → parameter yang diperlukan.

**Aturan:**
- Intent baru wajib ditambahkan di sini **sebelum** diimplementasikan.
- Setiap intent wajib punya: ID, contoh pertanyaan, endpoint, parameter, dan contoh jawaban.
- Intent yang belum punya endpoint siap: status `planned`, tidak boleh di-ship ke production.

---

## Status Legend

| Status | Arti |
|--------|------|
| `implemented` | Backend service sudah diimplementasikan (AiIntentResolver v1) |
| `ready` | Endpoint internal sudah ada, bisa diimplementasikan |
| `planned` | Endpoint belum ada atau belum siap, perlu dibangun dulu |
| `deferred` | Dipertimbangkan untuk v2+ |

---

## Scope: Employee Self-Service

### `leave.balance.self`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Berapa sisa cuti saya?"
- "Cuti tahunan saya masih berapa hari?"
- "Saldo cuti saya sekarang?"

**Endpoint:** `GET /v1/hcm/leaves/balance`  
**Parameter wajib:**
- `userId` = self (diisi otomatis dari token, bukan dari input user)
- `companyId` = dari tenant context

**Contoh data internal:**
```json
{ "remaining": 8, "used": 4, "total": 12, "period": "2026", "type": "annual" }
```

**Contoh jawaban AI:**
> "Sisa cuti tahunan kamu di tahun 2026 adalah **8 hari** (sudah terpakai 4 dari 12 hari)."

---

### `leave.history.self`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Riwayat cuti saya bulan ini"
- "Kapan saja saya ambil cuti tahun ini?"
- "Cuti saya yang terakhir kapan?"

**Endpoint:** `GET /v1/hcm/leaves?userId=self&limit=10`  
**Parameter wajib:** `userId` = self, `companyId` = tenant context  
**Parameter opsional:** `month`, `year`, `status`

**Contoh jawaban AI:**
> "Kamu punya 2 riwayat cuti bulan Mei 2026: cuti tahunan 2-3 Mei (approved) dan cuti sakit 15 Mei (approved)."

---

### `attendance.today.self`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Sudah absen hari ini?"
- "Saya sudah clock-in belum?"
- "Jam berapa tadi saya masuk?"

**Endpoint:** `GET /v1/hcm/attendance/today?userId=self`  
**Parameter wajib:** `userId` = self

**Contoh data internal:**
```json
{ "date": "2026-05-01", "clock_in": "08:02", "clock_out": null, "status": "present" }
```

**Contoh jawaban AI:**
> "Kamu sudah clock-in hari ini pukul 08:02. Belum clock-out."

---

### `attendance.history.self`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Absensi saya minggu lalu"
- "Berapa hari saya masuk bulan April?"
- "Rekap kehadiran saya"

**Endpoint:** `GET /v1/hcm/attendance?userId=self&month=X&year=Y`  
**Parameter wajib:** `userId` = self  
**Parameter opsional:** `month`, `year`, `limit`

---

### `payslip.latest.self`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Payslip bulan lalu saya berapa?"
- "Gaji saya bulan April berapa?"
- "Slip gaji terakhir saya"

**Endpoint:** `GET /v1/hcm/payslip/latest?userId=self`  
**Parameter wajib:** `userId` = self

**Contoh data internal:**
```json
{
  "period": "April 2026",
  "gross": 8500000,
  "deductions": 850000,
  "net": 7650000,
  "status": "paid"
}
```

**Contoh jawaban AI:**
> "Payslip bulan April 2026: gaji kotor Rp 8.500.000, potongan Rp 850.000, **gaji bersih Rp 7.650.000** (sudah dibayar)."

---

### `payslip.history.self`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Riwayat payslip saya"
- "Payslip 3 bulan terakhir"

**Endpoint:** `GET /v1/hcm/payslip?userId=self&limit=6`

---

### `ticket.status.self`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Tiket pengaduan saya sudah diproses?"
- "Status tiket IT saya nomor #TKT-001"
- "Ada balasan tiket saya?"

**Endpoint:** `GET /v1/hcm/tickets/{id}` atau `GET /v1/hcm/tickets?scope=self&status=open`  
**Parameter:** ticket ID dari pertanyaan (jika disebut), atau list open tickets milik self

**Contoh jawaban AI:**
> "Tiket #TKT-042 kamu saat ini berstatus **In Progress** dan sudah ditangani sejak 30 April 2026."

---

### `profile.info.self`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Saya di departemen apa?"
- "Jabatan saya apa?"
- "Data karyawan saya"
- "Nama atasan saya siapa?"

**Endpoint:** `GET /v1/hcm/employees/self`  
**Parameter wajib:** implicit from token

**Contoh jawaban AI:**
> "Kamu tercatat sebagai **Software Engineer** di departemen **Engineering**, bergabung sejak 1 Maret 2024."

---

## Scope: HCM Admin (Tenant)

### `leave.balance.other`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Berapa sisa cuti Budi?"
- "Saldo cuti karyawan dengan ID 42"

**Endpoint:** `GET /v1/hcm/leaves/balance?userId={target}`  
**Gate:** RBAC cek bahwa `targetUserId` berada di company aktif admin  
**Cross-user scope:** hanya boleh dalam satu company — bukan lintas tenant

---

### `leave.summary.company`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Berapa banyak karyawan yang sedang cuti hari ini?"
- "Rekap penggunaan cuti bulan ini"

**Endpoint:** `GET /v1/hcm/leaves/summary?period=current-month`

---

### `attendance.summary.company`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Berapa persen kehadiran hari ini?"
- "Siapa saja yang tidak masuk hari ini?"

**Endpoint:** `GET /v1/hcm/attendance/summary?date=today`

---

### `payroll.run.status`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Payroll bulan ini sudah di-run?"
- "Status payroll Mei 2026"

**Endpoint:** `GET /v1/hcm/payroll-runs?period=current`

---

### `payroll.run.summary`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Total pengeluaran gaji bulan ini berapa?"
- "Berapa total payroll yang sudah dibayar?"

**Endpoint:** `GET /v1/hcm/payroll-runs/{id}/summary`

---

### `employee.list.company`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Berapa jumlah karyawan aktif sekarang?"
- "Ada berapa karyawan di departemen Engineering?"

**Endpoint:** `GET /v1/hcm/employees?status=active&count=true`

---

## Scope: Global Admin

### `saas.company.summary`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Berapa jumlah company yang aktif berlangganan?"
- "Company mana yang trial-nya hampir habis?"

**Endpoint:** `GET /v1/saas/companies/billing-overview` (sudah ada)

---

### `saas.billing.summary`
**Status:** `implemented`  
**Contoh pertanyaan:**
- "Total revenue bulan ini berapa?"
- "Ada berapa invoice yang belum dibayar?"

**Endpoint:** `GET /v1/saas/dashboard/*` (sudah ada sebagian)

### `saas.tax.monthly`
**Status:** `implemented`
**Contoh pertanyaan:**
- "Berapa pajak yang kita bayarkan ke pemerintah bulan ini?"
- "Pajak platform bulan ini berapa?"
- "Tax paid this month"

**Endpoint:** Resolver internal AI (`PlatformRevenueTransaction` + rekap `BillingTaxCalculationService`)
**Output utama:**
- `government_tax_paid_this_month` (pajak dari transaksi cleared bulan aktif)
- `government_tax_due_this_month` (kewajiban pajak dari rekap compliance bulan aktif)

---

## Intent yang Selalu Ditolak (Hardcoded DENY)

| Intent Pattern | Alasan |
|----------------|--------|
| `general.knowledge.*` | Di luar scope HRMS |
| `*.other_company.*` | Cross-tenant, selalu ditolak di level gate |
| `admin.*` jika dipanggil karyawan biasa | Escalation privilege, ditolak |
| `unknown` | Tidak terklasifikasi |

---

## Cara Menambah Intent Baru

1. Tambahkan baris di dokumen ini dengan status `planned`.
2. Pastikan endpoint yang akan dipakai sudah terdokumentasi di [docs/api/openapi.yaml](../../api/openapi.yaml).
3. Tambahkan baris di tabel [RBAC-POLICY.md](./RBAC-POLICY.md) untuk setiap role.
4. Implementasikan `IntentResolver` yang memanggil endpoint tersebut dengan auth context yang benar.
5. Tulis test negative: cross-user, cross-tenant, endpoint down.
6. Update status dari `planned` → `ready`.
