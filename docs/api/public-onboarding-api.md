# Public Onboarding API

Endpoint publik untuk self-serve onboarding: buat **Company + Owner user + Subscription**, dan opsional langsung buat **Invoice** (mode `pending_payment`).

## Base Path

```
/v1/public
```

## Authentication

Tidak memerlukan token (guest). Endpoint ini **wajib** rate-limited (lihat OpenAPI: `429`).

## POST /v1/public/onboarding

Membuat tenant/company baru beserta owner, lalu memulai subscription:
- `start_mode=trial` (default): buat subscription `trial`
- `start_mode=pending_payment`: buat subscription `pending_payment` + buat invoice `draft` dan kirim email invoice async (best-effort)

Catatan window billing:
- Mode `pending_payment` memakai window pembayaran default **24 jam** sejak registrasi.
- Invoice awal mode `pending_payment` default `due_date` = **H+1** (tanggal berikutnya).

### Anti-bruteforce (captcha)

Endpoint ini bisa diproteksi dengan **Cloudflare Turnstile**:

- Kirim `turnstile_token` dari form field Turnstile `cf-turnstile-response`
- Field honeypot `website` harus kosong
- Saat Turnstile aktif, backend melakukan verifikasi token ke endpoint Cloudflare `siteverify` (server-side). Token invalid/expired akan ditolak `422`.

### Request Body

```json
{
  "package_uuid": "d6f8f0e7-3b2e-4f59-9ff1-1d0b3b7c5aca",
  "billing_cycle": "monthly",
  "start_mode": "trial",
  "turnstile_token": "<token>",
  "website": "",
  "company": {
    "name": "ACME Corp",
    "legal_name": "PT ACME Corp",
    "timezone": "Asia/Jakarta",
    "currency": "IDR",
    "country_code": "ID",
    "contact_phone": "+62 812-3456-7890",
    "contact_person_name": "Siti HR Admin",
    "contact_person_role": "HR Admin",
    "address": "Jl. Sudirman Kav. 52-53",
    "city": "Jakarta Selatan",
    "postal_code": "12190"
  },
  "owner": {
    "name": "Jane Doe",
    "email": "owner@acme.test",
    "phone": "+62 812-3456-7890",
    "password": "StrongPass1",
    "confirmPassword": "StrongPass1"
  },
  "billingEmail": "billing@acme.test"
}
```

### Validation (ringkas)

- `package_uuid`: harus ada, status package `active`
- `billing_cycle`: `monthly|yearly`
- `start_mode`: `trial|pending_payment` (default `trial`)
- `turnstile_token`: optional, **required jika Turnstile enabled**
- `website`: optional (honeypot), harus kosong
- `consent_accepted`: wajib `true`/accepted
- `company.code`: **optional**. Jika dikirim, harus unik dan regex `^[A-Za-z0-9_-]+$`. Jika tidak dikirim, server auto-generate code unik.
- `company.name`: wajib, minimal 2 karakter, maksimal 255 karakter.
- `company.contact_phone`: optional, max 20 karakter, regex `^[0-9+\-\s().]{6,20}$`
- `company.contact_person_name`: optional, max 120, regex `^[A-Za-z\s'.\-]+$` (hanya huruf/spasi/punctuation umum)
- `company.contact_person_role`: optional, max 120, regex `^[A-Za-z0-9\s'.\-\/&,]+$`
- `company.address`: wajib (max 500)
- `company.city`: wajib (max 120)
- `company.postal_code`: optional, regex `^[0-9]{3,12}$`
- `owner.name`: regex `^[A-Za-z][A-Za-z\s'.-]{1,149}$`
- `owner.phone`: optional, max 20 karakter, regex `^[0-9+\-\s().]{6,20}$`
- `owner.password`: regex `^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$`
- `billingEmail`: optional, email

### Response (201 Created)

```json
{
  "success": true,
  "data": {
    "company": { "id": 1, "code": "acme", "name": "ACME Corp" },
    "owner": { "id": 10, "name": "Jane Doe", "email": "owner@acme.test" },
    "subscription": {
      "id": 5,
      "status": "trial",
      "startsAt": "2026-04-16T00:00:00.000000Z",
      "endsAt": "2026-05-16T00:00:00.000000Z",
      "trialEndsAt": "2026-05-16T00:00:00.000000Z",
      "billingCycle": "monthly",
      "amount": 199000,
      "packageId": "d6f8f0e7-3b2e-4f59-9ff1-1d0b3b7c5aca",
      "packageCode": "pro",
      "packageName": "Pro Plan"
    },
    "invoice": null
  }
}
```

### Response tambahan untuk `start_mode=pending_payment`

Saat mode onboarding langsung berbayar (`pending_payment`), node `invoice` berisi metadata billing dan breakdown pricing:

```json
{
  "invoice": {
    "id": 101,
    "invoiceNumber": "INV-20260510-0001",
    "issueDate": "2026-05-10",
    "dueDate": "2026-05-11",
    "amountDue": 555000,
    "isPaid": false,
    "status": "draft",
    "billingTaxRateSnapshot": 11,
    "pricingBreakdown": {
      "base_amount": 500000,
      "subscription_tax_rate": 11,
      "subscription_tax_amount": 55000,
      "service_fee_rate": 0,
      "service_fee_amount": 0,
      "total_amount": 555000,
      "components": [
        {
          "key": "subscription_tax_rate",
          "label": "Pajak langganan",
          "rate": 11,
          "amount": 55000
        }
      ]
    }
  }
}
```

### Errors

- `422 VALIDATION_ERROR`: payload tidak valid / duplikat `company.code` / duplikat `owner.email`
- `429 TOO_MANY_REQUESTS`: rate limit tercapai

