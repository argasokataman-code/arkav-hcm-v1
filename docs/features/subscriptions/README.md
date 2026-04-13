# Subscriptions Module

Mengelola subscription plans dan membership companies dalam sistem SaaS.

## Overview

Module **Subscriptions** bertanggung jawab untuk:
- Membuat dan mengelola subscription plans (tier: Basic, Pro, Enterprise)
- Assign subscription ke company
- Track subscription lifecycle (active, trial, expired, cancelled)
- Manage auto-renewal dan billing cycles
- Support multiple billing periods (monthly, yearly)

## Data Model

### Subscription (table: `subscriptions`)
- `id` — primary key
- `company_id` — which company owns this subscription
- `plan_id` — which plan is subscribed
- `plan_code` — denormalized plan code (basic, pro, enterprise)
- `status` — `active`, `trial`, `inactive`, `expired`, `cancelled`
- `starts_at` — subscription start date
- `ends_at` — subscription end date
- `trial_ends_at` — trial period end (nullable)
- `auto_renew` — boolean, auto-renew on expiry
- `billing_cycle` — `monthly`, `yearly`
- `amount` — subscription cost (for current period)
- `created_at`, `updated_at`

### SubscriptionPlan (table: `subscription_plans`)
- `id` — primary key
- `code` — `basic`, `pro`, `enterprise` (unique)
- `name` — display name
- `description` — plan description
- `monthly_price` — price per month
- `yearly_price` — price per year
- `features` — JSON array of features included
- `max_employees` — employee limit (null = unlimited)
- `max_companies` — company limit (null = unlimited)
- `status` — `active`, `inactive`, `archived`
- `created_at`, `updated_at`

## API Endpoints

### Plans
- `GET /v1/saas/plans` — List all plans
- `POST /v1/saas/plans` — Create plan (super admin)
- `PUT /v1/saas/plans/{id}` — Update plan (super admin)
- `DELETE /v1/saas/plans/{id}` — Delete plan (super admin)

### Subscriptions (Company)
- `GET /v1/saas/subscriptions` — List subscriptions (admin)
- `POST /v1/saas/subscriptions` — Create subscription (admin)
- `PUT /v1/saas/subscriptions/{id}` — Update subscription (admin)
- `DELETE /v1/saas/subscriptions/{id}` — Cancel subscription (admin)
- `GET /v1/saas/subscriptions/{id}/current` — Get active subscription for company

## Features

- ✅ Plan management (CRUD)
- ✅ Subscription lifecycle
- ✅ Trial period support
- ✅ Auto-renewal configuration
- ✅ Multiple billing cycles
- ✅ Feature-based access control
- ⏳ Renewal notifications
- ⏳ Downgrade/upgrade workflow

## Related Modules

- **Packages** — subscription tiers and pricing
- **Purchase Transaction** — billing history
- **Companies** — company context
