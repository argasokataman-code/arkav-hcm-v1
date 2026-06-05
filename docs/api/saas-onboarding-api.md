# SaaS Onboarding API

## Overview

The public onboarding endpoint enables self-serve company registration without authentication. It's a guest-accessible endpoint that creates a complete tenant setup including company, owner user, subscription, and default company policies.

## Endpoints

### POST `/v1/public/onboarding`

**Status:** Production  
**Rate Limit:** Throttled (see `PostsPerMinute` setting)  
**Authentication:** None (guest)

#### Description

Creates a new company tenant with owner user and subscription. Automatically provisions 8 default HR policy templates (Indonesian context) for immediate use by the admin.

**Resources Created:**
- User (company owner)
- Company (tenant)
- CompanyUser (owner mapping)
- Subscription (trial or pending_payment mode)
- Default Policies (8 templates):
  1. Employee Attendance
  2. Code of Conduct
  3. Leave Management
  4. Data Security and Privacy
  5. Performance Management
  6. Workplace Health and Safety
  7. Compensation and Benefits
  8. Conflict of Interest
- Optional Invoice (if `start_mode: "pending_payment"`)

#### Request

```json
{
  "package_uuid": "uuid",
  "billing_cycle": "monthly|yearly",
  "start_mode": "trial|pending_payment",
  "consent_accepted": true,
  "billingEmail": "optional@email.com",
  "turnstile_token": "optional token",
  "website": "",
  "company": {
    "name": "Company Name",
    "code": "COMP-CODE",
    "legal_name": "Legal Company Name",
    "timezone": "Asia/Jakarta",
    "currency": "IDR",
    "country_code": "ID",
    "contact_phone": "+62812345678",
    "contact_person_name": "John Doe",
    "contact_person_role": "Director",
    "address": "Street Address",
    "city": "Jakarta",
    "postal_code": "12345"
  },
  "owner": {
    "name": "John Doe",
    "email": "owner@company.com",
    "phone": "+62812345678",
    "password": "SecurePass123",
    "confirmPassword": "SecurePass123"
  }
}
```

**Validation Rules:**
- `package_uuid`: Required, must be valid package UUID
- `billing_cycle`: Required, must be `monthly` or `yearly`
- `consent_accepted`: Required, must be `true`
- `start_mode`: Optional, defaults to `trial`
- `company.name`: Required, max 255 chars
- `company.code`: Optional, auto-generated if omitted (format: alphanumeric + underscore/dash)
- `owner.password`: Required, min 8 chars, must include uppercase, lowercase, digit
- `turnstile_token`: Required if server has Turnstile enabled
- `website`: Honeypot field (must be empty or null)

#### Response (201 Created)

```json
{
  "success": true,
  "data": {
    "company": {
      "id": 123,
      "code": "COMP-CODE",
      "name": "Company Name"
    },
    "owner": {
      "id": 456,
      "name": "John Doe",
      "email": "owner@company.com"
    },
    "subscription": {
      "id": 789,
      "status": "trial|pending_payment",
      "startsAt": "2026-01-01T00:00:00Z",
      "endsAt": "2026-02-01T00:00:00Z",
      "trialEndsAt": "2026-02-01T00:00:00Z",
      "billingCycle": "monthly|yearly",
      "amount": 0,
      "packageId": "uuid",
      "packageCode": "STARTER",
      "packageName": "Starter Plan"
    },
    "invoice": {
      "id": 999,
      "invoiceNumber": "INV-2026-001",
      "issueDate": "2026-01-01",
      "dueDate": "2026-01-15",
      "amountDue": 500000,
      "isPaid": false,
      "status": "draft",
      "billingTaxRateSnapshot": 10,
      "pricingBreakdown": {
        "base_amount": 450000,
        "subscription_tax_rate": 10,
        "subscription_tax_amount": 50000,
        "total_amount": 500000,
        "components": [...]
      }
    }
  }
}
```

#### Error Responses

**422 Unprocessable Entity** — Validation failed

```json
{
  "success": false,
  "message": "The given data was invalid",
  "errors": {
    "package_uuid": ["The package_uuid field is required"],
    "owner.email": ["The owner.email field must be a valid email address"],
    "company.code": ["The company code has already been taken"]
  }
}
```

**429 Too Many Requests** — Rate limit exceeded

```json
{
  "message": "Too Many Requests"
}
```

## Policies Seeding

On successful onboarding, 8 default company policies are automatically created for the new company:

| Policy | Description |
|--------|-------------|
| Employee Attendance | Guidelines for attendance and absence/leave compliance |
| Code of Conduct | Professional behavior standards for all employees |
| Leave Management | Leave types, duration, approval process, and benefits |
| Data Security and Privacy | Data protection and IT system usage policies |
| Performance Management | Performance evaluation process and career development |
| Workplace Health and Safety | Occupational health, safety, and wellness policies |
| Compensation and Benefits | Salary, allowances, insurance, and welfare packages |
| Conflict of Interest | Conflict of interest disclosure and resolution |

All policies are:
- Created with Indonesian business descriptions
- Scoped to the company (no cross-tenant data leakage)
- Set effective immediately (today's date)
- Available for admin to view, edit, or delete from the Policy management page

## Trial vs Pending Payment Modes

### Trial Mode (Default)

- Subscription status: `trial`
- No invoice created
- 30-day trial period (configurable)
- Full feature access during trial
- Company automatically activates

### Pending Payment Mode

- Subscription status: `pending_payment`
- Invoice created in draft status
- Invoice number is auto-generated per company
- Requires payment before full access
- Company status: pending activation

## Security Considerations

- **No authentication required** — guest access only
- **Rate-limited** — prevents automated abuse
- **Turnstile validation** — optional bot protection (server-configurable)
- **Email uniqueness** — owner email must be globally unique (prevents takeover)
- **Company code uniqueness** — enforced per tenant (prevents collision)
- **Honeypot field** — `website` field filtered to catch bots

## Integration Notes

After onboarding completes:
1. Owner user has full HCM admin permissions in their company
2. Company is automatically active and ready for use
3. Policies are viewable in **Settings → Policies** page
4. Subscription terms apply immediately (trial clock starts)
5. Invoice (if generated) is available in **Billing** section

## Related Endpoints

- `GET /v1/hcm/settings` — Retrieve company settings (requires auth)
- `GET /v1/hcm/policies` — List company policies (requires auth)
- `POST /v1/hcm/policies` — Create custom policies (requires auth)
