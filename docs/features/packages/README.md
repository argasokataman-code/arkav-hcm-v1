# Packages Module

Mengelola pricing tiers/packages dan feature allocation per tier.

## Overview

Module **Packages** mendefinisikan:
- Subscription tiers (Basic, Pro, Enterprise)
- Pricing untuk setiap tier
- Features included dalam setiap tier
- Limitations (employee count, API rate limits, storage)
- Add-ons dan upsells

## Data Model

### Package (table: `packages`)
- `id` — primary key
- `code` — `basic`, `pro`, `enterprise` (unique)
- `name` — display name (Basic Plan, Pro Plan, Enterprise)
- `description` — plan description
- `monthly_price` — price per month (dalam Rupiah)
- `yearly_price` — price per year
- `billing_unit` — `user`, `company`, `flat` (how to calculate cost)
- `status` — `active`, `inactive`, `archived`
- `color` — UI badge color (hex)
- `sort_order` — display order
- `created_at`, `updated_at`

### PackageFeature (table: `package_features`)
- `id` — primary key
- `package_id` — which package
- `feature_code` — `employee_management`, `payroll`, `attendance`, `leave`, `performance`, `analytics`, `api_access`, `custom_domain`, `sso` (kebih)
- `feature_name` — display name
- `limit` — feature limit (null = unlimited, 0 = not included, > 0 = specific limit)
- `created_at`, `updated_at`

### PackageAddon (table: `package_addons`)
- `id` — primary key
- `code` — `extra_users`, `extra_companies`, `api_calls`, `storage` (lebih)
- `name` — display name
- `description` — what is being added
- `price_per_unit` — price untuk 1 unit (e.g., per 10 users, per GB)
- `unit_name` — `users`, `companies`, `1M API calls`, `GB`
- `status` — `active`, `inactive`
- `created_at`, `updated_at`

## API Endpoints

### Packages
- `GET /v1/saas/packages` — List all packages (public)
- `GET /v1/saas/packages/{id}` — Get package details (public)
- `POST /v1/saas/packages` — Create package (super admin)
- `PUT /v1/saas/packages/{id}` — Update package (super admin)
- `DELETE /v1/saas/packages/{id}` — Delete package (super admin)

### Package Features
- `GET /v1/saas/packages/{id}/features` — Get features dalam package
- `POST /v1/saas/packages/{id}/features` — Add feature ke package (super admin)
- `PUT /v1/saas/packages/features/{feature_id}` — Update feature limit (super admin)
- `DELETE /v1/saas/packages/features/{feature_id}` — Remove feature (super admin)

### Add-ons
- `GET /v1/saas/addons` — List all add-ons
- `POST /v1/saas/addons` — Create add-on (super admin)
- `PUT /v1/saas/addons/{id}` — Update add-on (super admin)
- `DELETE /v1/saas/addons/{id}` — Delete add-on (super admin)

## Features

- ✅ Package CRUD
- ✅ Feature allocation per package
- ✅ Add-on management
- ✅ Tiered pricing support
- ✅ Feature limit configuration
- ⏳ Package templates/presets
- ⏳ Custom package builder

## Related Modules

- **Subscriptions** — assign packages ke company
- **Purchase Transaction** — track add-on purchases
- **Companies** — check feature availability
