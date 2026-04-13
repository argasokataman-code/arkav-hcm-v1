# Super Admin Dashboard Module

Comprehensive analytics dan monitoring Dashboard untuk super admin dalam sistem SaaS.

## Overview

Module **Super Admin Dashboard** menyediakan:
- Real-time KPI monitoring (companies, users, revenue)
- Growth analytics (MRR, ARR, churn rate)
- Company performance metrics
- User activity trends
- Revenue forecasting
- Custom reports builder

## Key Metrics

### KPIs
- **Total Companies** — active company count
- **Total Users** — active user count across all companies
- **MRR (Monthly Recurring Revenue)** — current month revenue
- **ARR (Annual Recurring Revenue)** — projected yearly revenue
- **Churn Rate** — % companies lost per month
- **Customer Lifetime Value (CLV)** — average revenue per company
- **NRR (Net Revenue Retention)** — how much revenue retained from existing customers

### Breakdown Metrics
- Revenue by plan (Basic, Pro, Enterprise)
- Revenue by region/country
- Users by department
- Top companies (by revenue, user count, activity)
- Signup trend (daily/weekly/monthly)
- Subscription status breakdown (active, trial, expired, cancelled)

## Data Model

### DashboardMetric (cached table: `dashboard_metrics`)
Cached metrics untuk performa (updated setiap jam):
- `id` — primary key
- `metric_date` — date untuk metric
- `metric_key` — `total_companies`, `total_users`, `mrr`, `arr`, `active_subscriptions`, `churn_rate` (lebih)
- `metric_value` — numeric value
- `metric_metadata` — JSON (breakdown by plan, region, etc)
- `calculated_at` — when metric was calculated
- `next_calculation_at` — when next recalculation scheduled

### AuditLog (table: `audit_logs`)
- `id` — primary key
- `super_admin_id` — which super admin
- `action` — `view_dashboard`, `modify_subscription`, `delete_company`, `refund_transaction` (lebih)
- `target_type` — `company`, `user`, `subscription`, `transaction` (lebih)
- `target_id` — ID dari target
- `details` — JSON with action details
- `ip_address` — from where action came
- `user_agent` — browser info
- `created_at`

## API Endpoints

### Dashboard KPIs
- `GET /v1/saas/dashboard/kpi` — Get top-level KPIs (returns all major metrics)
- `GET /v1/saas/dashboard/kpi/{metric_key}` — Get specific metric with trend

### Company Analytics
- `GET /v1/saas/dashboard/companies` — List companies with stats (revenue, user count, health)
- `GET /v1/saas/dashboard/companies/{id}/details` — Deep dive company metrics
- `GET /v1/saas/dashboard/companies/top-performers` — Top 10 companies by revenue

### User Analytics
- `GET /v1/saas/dashboard/users` — User statistics by department, role
- `GET /v1/saas/dashboard/users/activity` — User activity heatmap (daily logins)
- `GET /v1/saas/dashboard/users/retention` — User retention cohort analysis

### Revenue Analytics
- `GET /v1/saas/dashboard/revenue/monthly` — MRR trend (last 12 months)
- `GET /v1/saas/dashboard/revenue/by-plan` — Revenue breakdown by subscription plan
- `GET /v1/saas/dashboard/revenue/forecast` — Revenue forecast (next 3-12 months)
- `GET /v1/saas/dashboard/revenue/churn` — Churn analysis

### Subscription Analytics
- `GET /v1/saas/dashboard/subscriptions/status` — Breakdown by status
- `GET /v1/saas/dashboard/subscriptions/health` — Health score analysis
- `GET /v1/saas/dashboard/subscriptions/upgrades` — Upgrade/downgrade trends

### Reports
- `GET /v1/saas/dashboard/reports/export` — Export report (CSV/PDF) dengan date range
- `GET /v1/saas/dashboard/reports/custom` — Custom report builder endpoint

### Audit
- `GET /v1/saas/dashboard/audit-logs` — List audit logs (filter by super admin, action, date)
- `GET /v1/saas/dashboard/audit-logs/{id}` — Get audit log details

## Frontend Components

### Dashboard Widgets
- **KPI Cards** — Total Companies, Total Users, MRR, ARR (large numbers dengan trend indicator)
- **Revenue Chart** — Line chart showing MRR trend (last 12 months)
- **Plan Distribution** — Pie/Donut chart (Basic vs Pro vs Enterprise revenue %)
- **Top Companies** — Table dengan company name, users, revenue, growth %
- **Recent Signups** — Timeline/list of last 10 company signups
- **Churn Analysis** — Table of recently cancelled subscriptions (reason, date)
- **User Activity Heatmap** — Calendar heatmap showing daily active users

### Filters/Controls
- Date range picker (last 7 days, 30 days, 90 days, YTD, custom)
- Region/country filter
- Subscription plan filter
- Company status filter (active, trial, inactive)
- Export button (CSV, PDF, Excel)
- Refresh button (manual cache update)

## Features

- ✅ Real-time KPI tracking
- ✅ Growth analytics (MRR, ARR, churn)
- ✅ Company performance ranking
- ✅ Revenue forecasting
- ✅ User retention analysis
- ✅ Audit logging
- ✅ Custom date ranges
- ✅ Export to CSV/PDF
- ⏳ Predictive analytics (churn prediction)
- ⏳ Alerting (send Slack notification on threshold breach)
- ⏳ Custom dashboard per super admin
- ⏳ API webhooks untuk third-party dashboards

## Performance Considerations

- Metrics cached setiap 1 jam (configurable)
- Dashboard KPI endpoint membalikkan cached data, bukan real-time compute
- Historical data stored dalam `dashboard_metrics` untuk quick access
- Detailed analytics (company-specific) computed on-demand dengan pagination

## Related Modules

- **Subscriptions** — data source untuk subscription metrics
- **Companies** — company-level analytics
- **Purchase Transaction** — revenue data
- **Users** — user activity tracking
