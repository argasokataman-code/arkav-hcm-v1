# Notifications E2E Testing Blueprint

## Tujuan

Memastikan notifikasi lintas modul terkirim ke penerima yang benar, muncul di inbox/email sesuai preference, dan tidak melanggar isolasi tenant serta gate admin platform.

## Data Setup

- Tenant A: 1 admin, 1 manager, 2 employee.
- Tenant B: 1 admin, 1 employee.
- Platform: 1 primary super admin code-1, 1 secondary super admin (non-code-1).
- Event fixture:
  - asset assigned/returned,
  - subscription change request,
  - invoice due soon/overdue,
  - password reset.

## Skenario Prioritas Tinggi

## S1 - Asset Assignment In-App

1. Assign asset ke employee Tenant A.
2. Verifikasi employee Tenant A menerima inbox notification.
3. Verifikasi user Tenant B tidak menerima event tersebut.

Expected:
- event key `asset.assigned` tercatat.
- unread count employee A bertambah 1.

## S2 - Asset Return In-App

1. Return asset dari employee Tenant A.
2. Verifikasi notifikasi `asset.returned` masuk.

Expected:
- payload mengandung `assetCode` dan tanggal return.

## S3 - Subscription Approval Queue (Platform Gate)

1. Tenant submit upgrade request.
2. Verifikasi only primary super admin code-1 menerima notif approval-needed.
3. Secondary super admin tidak menerima notif.

Expected:
- tidak ada leak platform approval notif ke non-code-1.

## S4 - Invoice Email Logging

1. Trigger send invoice email dari endpoint billing.
2. Verifikasi `invoice_email_logs` terisi `sent`/`failed` sesuai hasil mail transport.

Expected:
- audit trail email ada, termasuk error message saat gagal.

## S5 - Payment Reminder Scheduler

1. Siapkan invoice due soon + overdue.
2. Jalankan scheduler reminder.
3. Verifikasi reminder dikirim ke recipient canonical sesuai desain target.

Expected:
- reminder tidak silent fail.
- status delivery tercatat.

## S6 - Password Reset Mail

1. User minta reset password.
2. Verifikasi email reset terkirim dan token valid.

Expected:
- event security tidak dipengaruhi preference non-security.

## S7 - Preference Toggle (Target)

1. User mematikan informational email tertentu.
2. Trigger event informational.
3. Verifikasi inbox tetap masuk jika policy mewajibkan, email mengikuti toggle.

Expected:
- policy precedence benar antara default system vs user preference.

## S8 - Mark Read / Read All (Target)

1. User buka inbox.
2. Mark satu item sebagai read.
3. Jalankan read-all.

Expected:
- unread count sinkron realtime/polling.

## Non-Functional Checks

- Load test ringan: burst 1.000 event in-app dalam 5 menit.
- Retry behavior: simulasikan SMTP down dan verifikasi retry + failure log.
- Security: uji akses endpoint notifikasi lintas company harus `403/404` sesuai policy.

## Evidence Yang Wajib Disimpan

- Screenshot/UI recording inbox.
- Potongan response API list/read/read-all.
- Log queue worker + delivery status.
- Query evidence pada tabel `notifications` dan `invoice_email_logs`.
- Ringkasan pass/fail per role.
