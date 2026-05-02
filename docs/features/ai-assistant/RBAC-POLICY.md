# AI Assistant — RBAC Policy (Allow / Deny Matrix)

Dokumen ini mendefinisikan **secara tegas** intent mana yang diizinkan untuk role mana.  
Ini adalah sumber kebenaran untuk implementasi gate di `AiChatController`.

---

## Prinsip Dasar

- **Deny by default** — jika intent tidak ada di tabel ini, selalu DENY.
- **Server-side enforcement** — gate ini diimplementasikan di backend, bukan di LLM prompt saja.
- **Least privilege** — karyawan hanya dapat data dirinya sendiri; tidak ada "coba dulu lalu filter di output".
- **Cross-tenant block** — AI tidak pernah memasukkan `company_id` selain dari token user aktif.

---

## Tabel Allow / Deny

### Legend
- ✅ = Allowed (AI boleh fetch data dan jawab)
- ❌ = Deny (AI wajib tolak, kirim pesan deny standar)
- `-` = Not applicable

| Intent ID | Karyawan | HCM Admin (tenant) | Global Admin | Catatan |
|-----------|----------|--------------------|--------------|---------|
| `leave.balance.self` | ✅ | ✅ | ✅ | Hanya saldo user yang sedang login |
| `leave.balance.other` | ❌ | ✅ | ✅ | Admin boleh tanya saldo user lain di company sendiri |
| `leave.history.self` | ✅ | ✅ | ✅ | History milik user aktif |
| `leave.history.other` | ❌ | ✅ | ✅ | Admin + scope company aktif |
| `leave.summary.company` | ❌ | ✅ | ✅ | Ringkasan leave usage per company |
| `attendance.today.self` | ✅ | ✅ | ✅ | Status clock-in/out hari ini |
| `attendance.history.self` | ✅ | ✅ | ✅ | Riwayat absensi sendiri |
| `attendance.summary.company` | ❌ | ✅ | ✅ | Ringkasan absensi seluruh karyawan |
| `payslip.latest.self` | ✅ | ✅ | ✅ | Payslip terakhir user aktif |
| `payslip.history.self` | ✅ | ✅ | ✅ | List payslip milik user aktif |
| `payroll.run.status` | ❌ | ✅ | ✅ | Status payroll run bulan aktif |
| `payroll.run.summary` | ❌ | ✅ | ✅ | Ringkasan nominal total run aktif |
| `ticket.status.self` | ✅ | ✅ | ✅ | Status tiket milik user aktif |
| `ticket.list.self` | ✅ | ✅ | ✅ | List tiket milik user aktif |
| `ticket.list.all` | ❌ | ✅ | ✅ | Semua tiket di company (admin only) |
| `profile.info.self` | ✅ | ✅ | ✅ | Nama, departemen, jabatan user aktif |
| `employee.list.company` | ❌ | ✅ | ✅ | Headcount aktif di company |
| `department.info` | ❌ | ✅ | ✅ | Info departemen di company aktif |
| `saas.company.summary` | ❌ | ❌ | ✅ | Lintas tenant — global admin only |
| `saas.billing.summary` | ❌ | ❌ | ✅ | Billing/subscription — global admin only |
| `saas.tax.monthly` | ❌ | ❌ | ✅ | Ringkasan pajak platform bulan aktif — global admin only |
| `general.knowledge.*` | ❌ | ❌ | ❌ | Pertanyaan di luar scope HRMS — selalu DENY |
| `unknown` | ❌ | ❌ | ❌ | Intent tidak dikenali — selalu DENY |

---

## Pesan Deny Standar

Pesan deny harus konsisten dan tidak bocorkan informasi tentang struktur internal.

| Kasus | Pesan |
|-------|-------|
| Intent tidak dikenali | `"Saya hanya dapat membantu pertanyaan seputar HRMS Arkav. Coba tanyakan hal lain seperti cuti, absensi, atau payslip kamu."` |
| Intent dikenali tapi tidak ada izin | `"Kamu tidak memiliki akses untuk informasi ini."` |
| Data milik user lain (cross-user attempt) | `"Kamu tidak punya akses ke data user lain."` |
| Cross-tenant attempt | `"Permintaan ini tidak dapat diproses untuk akun ini."` |
| Endpoint gagal / timeout | `"Maaf, data tidak bisa diambil saat ini. Silakan coba beberapa saat lagi atau buka halaman terkait langsung."` |
| Data tidak ditemukan (404 dari endpoint) | `"Data tidak ditemukan. Mungkin belum ada data untuk periode ini."` |

---

## Gate Implementasi (Backend)

Pseudocode gate yang wajib ada di `AiChatController` sebelum LLM dipanggil:

```php
// 1. Klasifikasikan intent dari pesan user
$intent = $this->intentClassifier->classify($message);

// 2. Cek apakah intent dikenali
if ($intent === 'unknown' || !$this->intentRegistry->exists($intent)) {
    return $this->denyResponse('intent_unknown');
}

// 3. Cek RBAC: apakah user aktif boleh akses intent ini
$allowed = $this->intentGate->check($intent, $user, $companyId);
if (!$allowed) {
    return $this->denyResponse('permission_denied');
}

// 4. Ambil data dari endpoint internal (dengan token user aktif)
$data = $this->intentResolver->resolve($intent, $user, $companyId, $params);
if ($data === null) {
    return $this->denyResponse('data_not_found');
}

// 5. Compose jawaban melalui LLM dengan data sebagai context (bukan query bebas)
$reply = $this->llm->compose($intent, $data, $message);

// 6. Log audit
$this->auditLog->record($user, $companyId, $intent, $allowed, $data['sources'] ?? []);

return $this->allowResponse($reply, $data['sources'] ?? []);
```

**Kunci:** LLM **hanya dipanggil di langkah 5** setelah data sudah di-fetch secara aman. LLM tidak diberi akses langsung ke DB atau API — dia hanya merangkai jawaban dari data yang sudah disiapkan.

---

## Cross-Tenant Enforcement (Wajib)

Setiap intent resolver yang melakukan HTTP call ke endpoint internal **wajib** menyertakan:

```php
$headers = [
    'Authorization' => 'Bearer ' . $user->currentToken()->token,
    'X-Company-Id'  => (string) $companyId,  // HARUS dari token, bukan dari input user
];
```

`$companyId` **tidak boleh** diambil dari body request chat — hanya dari `activeCompanyId($request)` yang sudah divalidasi oleh middleware `tenant.context`.

---

## Audit Log Schema

Kolom minimum untuk tabel `ai_chat_logs`:

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `user_id` | bigint FK | User yang bertanya |
| `company_id` | int nullable | Tenant aktif saat chat |
| `session_id` | varchar | UUID sesi multi-turn |
| `intent` | varchar | Intent yang terklasifikasi |
| `message_hash` | varchar | Hash SHA-256 pesan user (bukan plaintext untuk privacy) |
| `allowed` | boolean | true = diizinkan, false = deny |
| `deny_reason` | varchar nullable | 'intent_unknown', 'permission_denied', 'data_not_found', 'endpoint_error' |
| `source_endpoints` | json | Array endpoint yang dipanggil |
| `created_at` | timestamp | |

> Jangan simpan teks pesan user atau teks jawaban AI di log — hash saja untuk privacy; detail bisa di conversation table terpisah jika butuh replay.
