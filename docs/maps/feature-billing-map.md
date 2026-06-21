# Feature Map: Billing & Subscription (SaaS)

## 1. Entry Points (API)
| Method | Path | Controller | Permission |
|--------|------|------------|------------|
| GET | `/v1/hcm/invoices` | `InvoiceController` | `billing.view` |
| GET | `/v1/hcm/company-invoices` | `HcmCompanyInvoiceController` | `billing.view` |
| POST | `/v1/hcm/subscription/checkout` | `HcmSubscriptionCheckoutController` | `billing.checkout` |
| POST | `/v1/hcm/subscription/change` | `HcmSubscriptionChangeController` | `billing.change` |
| POST | `/v1/hcm/payment/webhook` | `PaymentWebhookController` | (external) |
| GET/POST | `/v1/saas/packages` | `PackageController` | `saas.manage` |
| GET/POST | `/v1/saas/subscriptions` | `SubscriptionController` | `saas.manage` |
| GET/POST | `/v1/saas/transactions` | `TransactionController` | `saas.manage` |

## 2. Controllers
- `backend/app/Http/Controllers/Api/Billing/HcmCompanyInvoiceController.php` — Tenant invoices
- `backend/app/Http/Controllers/Api/Billing/HcmSubscriptionCheckoutController.php` — Checkout flow
- `backend/app/Http/Controllers/Api/Billing/HcmSubscriptionChangeController.php` — Upgrade/downgrade
- `backend/app/Http/Controllers/Api/Billing/InvoiceController.php` — Invoice CRUD
- `backend/app/Http/Controllers/Api/Payment/PaymentController.php` — Payment processing
- `backend/app/Http/Controllers/Api/Payment/PaymentWebhookController.php` — Webhook handler
- `backend/app/Http/Controllers/Api/Payment/MockPaymentController.php` — Mock gateway
- `backend/app/Http/Controllers/Api/Saas/PackageController.php` — Package management
- `backend/app/Http/Controllers/Api/Saas/SubscriptionController.php` — Subscription management
- `backend/app/Http/Controllers/Api/Saas/SuperAdminDashboardController.php` — Super admin dashboard

## 3. Services
- `backend/app/Services/InvoiceService.php` — Invoice generation
- `backend/app/Services/PaymentGatewayService.php` — Payment gateway interface
- `backend/app/Services/MidtransService.php` — Midtrans integration
- `backend/app/Services/MockPaymentGatewayService.php` — Mock for testing
- `backend/app/Services/SubscriptionActivationFromInvoiceService.php` — Activate after payment
- `backend/app/Services/SubscriptionTerminationService.php` — Terminate expired
- `backend/app/Services/BillingTaxCalculationService.php` — Tax calculation
- `backend/app/Services/AddonRecurringSubscriptionService.php` — Addon billing

## 4. Models
- `App\Models\Subscription` — Tenant subscription (status: active, trial, expired, terminated)
- `App\Models\Invoice` — Invoice records
- `App\Models\Payment` — Payment records
- `App\Models\Transaction` — Transaction log
- `App\Models\Package` — SaaS packages
- `App\Models\PackageFeature` — Features per package
- `App\Models\PackageAddon` — Addons
- `App\Models\PurchaseTransaction` — Purchase records
- `App\Models\HcmSubscriptionChangeRequest` — Upgrade/downgrade requests
- `App\Models\SubscriptionEvent` — Event log
- `App\Models\PlatformRevenueTransaction` — Platform revenue

## 5. Jobs
- `ProcessSubscriptionBilling` — Recurring billing
- `ProcessRecurringSubscriptionBilling` — Auto-renewal
- `ApplySubscriptionChangeJob` — Apply upgrade/downgrade
- `TerminateExpiredSubscriptionsJob` — Auto-terminate
- `SuspendServicesForOverdueInvoicesJob` — Suspend overdue
- `SendPaymentReminder` — Reminder emails
- `SendInvoiceEmailJob` — Invoice emails
- `ConvertExpiredTrialsToPendingPaymentJob` — Trial expiry

## 6. Events
- `SubscriptionCreated` — New subscription
- `AddonPurchased` — Addon bought

## 7. Key Relations
```
Subscription -> Company (N:1)
Subscription -> Package (N:1)
Invoice -> Subscription (N:1)
Payment -> Invoice (N:1)
Package -> PackageFeature (1:N)
Package -> PackageAddon (1:N)
```
