# Purchase Transactions Module

Mengelola invoice, payment history, dan transaction tracking untuk subscription.

## Overview

Module **Purchase Transactions** bertanggung jawab untuk:
- Track semua transaksi (subscription, add-ons, manual adjustments)
- Generate dan manage invoices
- Record payment history
- Support multiple payment methods
- Generate reports untuk billing

## Data Model

### PurchaseTransaction (table: `purchase_transactions`)
- `id` — primary key
- `transaction_code` — unique invoice number (TXN-2024-001001)
- `company_id` — which company
- `subscription_id` — linked subscription (nullable untuk standalone)
- `transaction_type` — `subscription`, `addon`, `refund`, `credit`, `manual`
- `description` — transaction details
- `amount` — transaction amount (Rupiah)
- `currency` — `IDR`, `USD`
- `tax_amount` — pajak (if applicable)
- `discount_amount` — discount (if applicable)
- `total_amount` — final amount (amount + tax - discount)
- `billing_period_start` — periode start
- `billing_period_end` — periode end
- `due_date` — payment due date
- `paid_at` — kapan dibayar (nullable)
- `payment_method` — `bank_transfer`, `credit_card`, `e_wallet`, `cash`
- `payment_reference` — bukti pembayaran (invoice/receipt reference)
- `status` — `draft`, `issued`, `sent`, `paid`, `overdue`, `cancelled`
- `notes` — remarks
- `created_at`, `updated_at`

### Invoice (table: `invoices`)
- `id` — primary key
- `invoice_number` — formatted invoice number (INV-2024-001)
- `company_id` — which company
- `purchase_transaction_id` — linked transaction
- `issue_date` — kapan invoice dibuat
- `due_date` — payment deadline
- `amount_due` — still owing
- `is_paid` — boolean
- `paid_date` — kapan dibayar
- `pdf_path` — stored PDF file path
- `status` — `draft`, `sent`, `viewed`, `paid`, `expired`
- `created_at`, `updated_at`

### Payment (table: `payments`)
- `id` — primary key
- `purchase_transaction_id` — which transaction
- `invoice_id` — which invoice
- `amount` — payment amount
- `payment_method` — `bank_transfer`, `credit_card`, `e_wallet`, `check` (lebih)
- `payment_gateway` — akses ke payment gateway `manual`, `stripe`, `xendit` (lebih)
- `reference_number` — payment gateway reference
- `paid_at` — payment timestamp
- `verified_at` — when payment was verified
- `status` — `pending`, `completed`, `failed`, `disputed`
- `notes` — notes tentang payment
- `created_at`, `updated_at`

## API Endpoints

### Transactions
- `GET /v1/saas/transactions` — List transactions (admin + filter by date, type, status, company)
- `POST /v1/saas/transactions` — Manual transaction creation (super admin)
- `GET /v1/saas/transactions/{id}` — Get transaction details
- `PUT /v1/saas/transactions/{id}` — Update transaction (super admin)

### Invoices
- `GET /v1/saas/invoices` — List invoices (admin)
- `POST /v1/saas/invoices` — Generate invoice (auto dari transaction)
- `GET /v1/saas/invoices/{id}` — Get invoice details
- `PUT /v1/saas/invoices/{id}/send` — Mark as sent
- `GET /v1/saas/invoices/{id}/pdf` — Download PDF
- `PUT /v1/saas/invoices/{id}/mark-paid` — Mark as paid (super admin)

### Payments
- `POST /v1/saas/payments` — Record payment (admin)
- `GET /v1/saas/payments/{id}` — Get payment details
- `PUT /v1/saas/payments/{id}/verify` — Verify/confirm payment (super admin)

### Reports
- `GET /v1/saas/reports/revenue` — Monthly/yearly revenue
- `GET /v1/saas/reports/aging` — Aging report (overdue invoices)
- `GET /v1/saas/reports/churn` — Churn report (cancelled subscriptions)

## Features

- ✅ Transaction recording
- ✅ Invoice generation
- ✅ Payment tracking
- ✅ Multi-currency support
- ✅ Tax calculation
- ⏳ Email invoices
- ⏳ Automated payment reminders
- ⏳ Payment gateway integration (Stripe/Xendit)
- ⏳ Custom invoice templates
- ⏳ Bulk payment upload

## Related Modules

- **Subscriptions** — auto-generate transaction saat renewal
- **Packages** — pricing source
- **Super Admin Dashboard** — revenue analytics
