# Domain Management Module

Mengelola custom domain untuk tenant companies dalam sistem SaaS.

## Overview

Module **Domain Management** memungkinkan:
- Companies membeli/assign custom domain
- DNS verification dan validation
- SSL/TLS certificate management
- Domain-to-company mapping
- Subdomain isolation untuk multi-tenancy

## Data Model

### CustomDomain (table: `custom_domains`)
- `id` — primary key
- `company_id` — which company owns this domain
- `domain_name` — domain yang digunakan (e.g., hr.acme.com)
- `domain_type` — `subdomain`, `custom_domain`
- `status` — `pending`, `active`, `inactive`, `expired`, `failed_verification`
- `verification_method` — `cname`, `txt_record`, `file_upload`
- `verification_code` — unique code untuk verification
- `verified_at` — kapan domain diverifikasi
- `expires_at` — kapan domain expires (nullable untuk CNAME)
- `ssl_status` — `pending`, `issued`, `expired`, `error`
- `ssl_certificate_path` — path to saved certificate
- `ssl_expires_at` — SSL cert expiry date
- `primary_domain` — boolean (main domain atau alias)
- `ip_address` — resolve IP address saat active
- `last_checked_at` — kapan last health check
- `health_status` — `healthy`, `warning`, `error`
- `notes` — admin notes
- `created_at`, `updated_at`

### DomainVerificationLog (table: `domain_verification_logs`)
- `id` — primary key
- `custom_domain_id` — which domain
- `verification_type` — `cname_check`, `txt_record_check`, `ssl_issuance`, `health_check`
- `status` — `success`, `failed`, `pending`
- `result_details` — JSON with verification details
- `error_message` — if failed
- `checked_at` — when verification/check happened
- `created_at`

## API Endpoints

### Domains (Admin)
- `GET /v1/saas/domains` — List domains (filter by company, status)
- `POST /v1/saas/domains` — Register new domain
- `GET /v1/saas/domains/{id}` — Get domain details
- `PUT /v1/saas/domains/{id}` — Update domain
- `DELETE /v1/saas/domains/{id}` — Delete domain (super admin only)

### Verification
- `GET /v1/saas/domains/{id}/verify` — Check verification status
- `POST /v1/saas/domains/{id}/verify` — Trigger manual verification
- `GET /v1/saas/domains/{id}/verification-instructions` — Get DNS setup instructions (public)

### Health & SSL
- `POST /v1/saas/domains/{id}/check-health` — Check domain health (super admin)
- `POST /v1/saas/domains/{id}/renew-ssl` — Renew SSL certificate (super admin)
- `GET /v1/saas/domains/{id}/ssl-status` — Get SSL certificate details

## Features

- ✅ Domain registration tracking
- ✅ DNS verification (CNAME, TXT record)
- ✅ SSL/TLS certificate management
- ✅ Domain-to-company mapping
- ✅ Health monitoring
- ✅ Verification history
- ⏳ Auto SSL renewal
- ⏳ Domain marketplace integration
- ⏳ Wildcard domain support
- ⏳ Email MX record setup

## Verification Methods

### CNAME Verification
```
Target: yourdomain.com
Alias: yourdomain.arcav.app
TTL: 300
```

### TXT Record Verification
```
Name: _acrav.yourdomain.com
Value: acrav-verification-code-123abc
TTL: 300
```

## Related Modules

- **Companies** — company owns domain
- **Subscriptions** — custom domain as premium feature
- **Purchase Transaction** — track domain purchase
