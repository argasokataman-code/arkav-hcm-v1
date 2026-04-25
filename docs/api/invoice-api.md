# Invoice API (Admin)

Base path: `/v1/saas/invoices`

## Otorisasi

- Wajib bearer token.
- Endpoint admin SaaS hanya boleh diakses global HCM admin.
- Identifier invoice memakai UUID route binding.

## GET `/v1/saas/invoices/{invoice}`

Mengambil detail invoice untuk halaman detail invoice admin.

### Response penting

- Ringkasan invoice: `uuid`, `invoiceNumber`, `status`, `isPaid`, `dueDate`, `amountDue`
- Ringkasan company: `company.id`, `company.uuid`, `company.code`, `company.name`
- Ringkasan subscription: `subscription.uuid`, `status`, `billingCycle`, `startsAt`, `endsAt`, `planCode`, `packageId`, `packageName`, `amount`
- `latestEmail`: ringkasan email terakhir
- `emailLogs[]`: riwayat penuh log email invoice, urut terbaru lebih dulu

### Contoh response

```json
{
  "success": true,
  "data": {
    "id": 99,
    "uuid": "d6f8f0e7-3b2e-4f59-9ff1-1d0b3b7c5aca",
    "invoiceNumber": "INV-000099",
    "status": "paid",
    "isPaid": true,
    "dueDate": "2026-04-23",
    "amountDue": 199000,
    "company": {
      "id": 1,
      "uuid": "9dfebc8e-1d5e-4bb5-8f44-8d0b949ce220",
      "code": "acme",
      "name": "ACME Corp"
    },
    "subscription": {
      "uuid": "c40fe1d5-8d5f-4760-8b30-3f85d9d5f4cb",
      "status": "pending_payment",
      "billingCycle": "monthly",
      "startsAt": "2026-04-16T00:00:00.000000Z",
      "endsAt": "2026-05-16T00:00:00.000000Z",
      "planCode": "pro",
      "packageId": "2cd0b8f7-6fb4-46c5-aef8-a4e8d2e19d0f",
      "packageName": "Pro Plan",
      "amount": 199000
    },
    "latestEmail": {
      "uuid": "d8fa8277-7fea-4fd0-84f6-fb4990e3a4da",
      "toEmail": "billing@acme.test",
      "status": "sent",
      "providerMessageId": "msg-123",
      "errorMessage": null,
      "createdAt": "2026-04-16T12:00:00.000000Z"
    },
    "emailLogs": [
      {
        "uuid": "d8fa8277-7fea-4fd0-84f6-fb4990e3a4da",
        "toEmail": "billing@acme.test",
        "status": "sent",
        "providerMessageId": "msg-123",
        "errorMessage": null,
        "createdAt": "2026-04-16T12:00:00.000000Z",
        "updatedAt": "2026-04-16T12:00:00.000000Z"
      },
      {
        "uuid": "b2a730d5-1204-4dd2-a5c8-8073c4d8bb57",
        "toEmail": "billing@acme.test",
        "status": "failed",
        "providerMessageId": null,
        "errorMessage": "SMTP timeout",
        "createdAt": "2026-04-16T11:00:00.000000Z",
        "updatedAt": "2026-04-16T11:00:00.000000Z"
      }
    ]
  }
}
```

## POST `/v1/saas/invoices/{invoice}/send-email`

Mengirim ulang email invoice dan menambah log baru ke `invoice_email_logs`.

### Guard status subscription

- Jika `subscription.status = pending_payment`, endpoint ini akan ditolak (`422`) dengan code `PENDING_PAYMENT_REMINDER_ONLY`.
- Untuk tenant pending payment, gunakan flow **Payment Reminder** (halaman `/saas/reminders` atau cron reminder), bukan kirim invoice baru.

### Body

```json
{
  "email": "billing@acme.test"
}
```

`email` opsional. Jika kosong, backend memakai email billing/default yang tersedia.

## Error utama

- `401 UNAUTHORIZED`
- `403 ADMIN_REQUIRED`
- `404 NOT_FOUND`
- `422 VALIDATION_ERROR`
- `422 PENDING_PAYMENT_REMINDER_ONLY`