# SaaS Subscription Auto-Management Features

## Overview

This document describes the automatic subscription management system that handles:
1. **Auto-Termination** - Subscriptions expire automatically when `ends_at` passes
2. **Auto-Suspension** - Services suspend when invoices are overdue (7+ days past due)
3. **Employee Count Enforcement** - Services suspend when employees exceed plan limit

> For end-to-end behaviour by scenario (happy path + negative handling), see **`SCENARIOS.md`**.

## Problem Statement

Previously, the SaaS system lacked critical business logic:

### Issue 1: Auto Putus Layanan (Service Not Auto-Terminating)
**Problem:** "auto putus layanan yg di subscribe tidak otomatis ketika kontrak invoicenya udah habis"

**Translation:** Service not auto-terminating when contract/invoice expires

**Impact:** Companies could continue using the system after subscription ends

### Issue 2: Employee Count Violation Handling
**Problem:** "customer tiba2 menambah karyawan dan itu semua scenario negative handlingnya belum ada workarroundnya"

**Translation:** No negative scenario handling when customers add employees exceeding plan limit

**Impact:** Companies could exceed their licensed employee count with no enforcement

## Solution Architecture

### 1. SubscriptionTerminationService

**Location:** `/backend/app/Services/SubscriptionTerminationService.php`

**Responsibilities:**
- Terminate expired subscriptions (status becomes 'expired')
- Suspend services due to overdue invoices (status becomes 'suspended')
- Suspend services due to employee count violations
- Reactivate suspended subscriptions after resolution
- Query subscriptions requiring action

**Key Methods:**

#### `terminateExpiredSubscription(Subscription $subscription, ?string $reason = null): bool`
Terminates a subscription when its `ends_at` has passed.

```php
$service = app(SubscriptionTerminationService::class);
$service->terminateExpiredSubscription($subscription, 'End date expired');
```

**Status Change:** `active|trial` → `expired`  
**Audit Trail:** Stores `terminated_at` and `termination_reason`

---

#### `suspendDueToOverdueInvoice(Subscription $subscription, Invoice $invoice): bool`
Suspends service when invoice is 7+ days past due date without payment.

```php
$service->suspendDueToOverdueInvoice($subscription, $invoice);
```

**Status Change:** `active|trial` → `suspended`  
**Reason:** "Invoice {NUMBER} overdue by {N} days"  
**Grace Period:** 7 days (configurable)

---

#### `suspendDueToEmployeeCountViolation(Subscription $subscription, int $currentCount, int $planLimit): bool`
Suspends service when active employee count exceeds plan limit.

```php
$service->suspendDueToEmployeeCountViolation($subscription, 150, 100);
// Suspends because 150 employees > 100 limit
```

**Status Change:** `active|trial` → `suspended`  
**Reason:** "Employee count (150) exceeds plan limit (100) by 50"

---

#### `reactivateSuspended(Subscription $subscription, string $reason): bool`
Reactivates a suspended subscription after issue is resolved.

```php
// After payment received
$service->reactivateSuspended($subscription, 'Payment received');

// Or after reducing employee count
$service->reactivateSuspended($subscription, 'Employee count corrected');
```

**Status Change:** `suspended` → `active`  
**Clears:** `suspended_at`, `suspension_reason`

---

#### Query Methods

**Get Expired Subscriptions:**
```php
$expired = $service->getExpiredSubscriptions(now());
// Returns subscriptions with status='active|trial' AND ends_at < now
```

**Get Subscriptions with Overdue Invoices:**
```php
$overdue = $service->getSubscriptionsWithOverdueInvoices(graceDays: 7);
// Returns [$subscription, $invoice] pairs with 7+ days overdue
```

**Get Employee Violations:**
```php
$violations = $service->getSubscriptionsWithEmployeeViolations();
// Returns [$subscription, $currentCount, $planLimit] tuples
```

---

### 2. EmployeeCountValidator

**Location:** `/backend/app/Services/EmployeeCountValidator.php`

**Responsibilities:**
- Validate if company can add employees without exceeding limit
- Check employee count against plan limits
- Detect and report violations
- Enforce plan limits before operations

**Key Methods:**

#### `canAddEmployees(Company $company, int $countToAdd = 1): array`
Check if company can add employees without exceeding plan limit.

```php
$validator = app(EmployeeCountValidator::class);
$result = $validator->canAddEmployees($company, countToAdd: 5);

// Returns:
[
    'canAdd' => true,
    'remaining' => 10,
    'limit' => 50,
    'current' => 40,
    'after_add' => 45,
    'message' => 'Can add 5 employee(s). 10 slots remaining.'
]
```

---

#### `validateCanAddEmployees(Company $company, int $countToAdd = 1): void`
Throw exception if company cannot add employees.

```php
try {
    $validator->validateCanAddEmployees($company, countToAdd: 5);
    // Safe to proceed with adding 5 employees
} catch (SubscriptionValidationException $e) {
    // Return error: "Cannot add 5 employee(s). Only 2 slots available."
    return response()->json($e->render(), 422);
}
```

**Exception Code:** `EMPLOYEE_COUNT_EXCEEDED`  
**HTTP Status:** 422 Unprocessable Entity

---

#### `getActiveEmployeeCount(int $companyId): int`
Get current number of non-terminated employees.

```php
$count = $validator->getActiveEmployeeCount($company->id);
// Returns: 42 (not counting terminated employees)
```

---

#### `getPlanEmployeeLimit(Company $company): ?int`
Get employee limit from company's subscription plan.

```php
$limit = $validator->getPlanEmployeeLimit($company);
// Returns: 50 (or null if unlimited)
```

---

#### `getRemainingSlots(Company $company): ?int`
Get number of available employee slots.

```php
$remaining = $validator->getRemainingSlots($company);
// Returns: 8 (or null if unlimited)
```

---

#### `isInViolation(Company $company): bool`
Check if company exceeds employee limit.

```php
$isBreach = $validator->isInViolation($company);
// Returns: true if employees > limit
```

---

#### `getViolationDetails(Company $company): array`
Get detailed violation information.

```php
$details = $validator->getViolationDetails($company);

// Returns:
[
    'isViolating' => true,
    'current' => 152,
    'limit' => 100,
    'excess' => 52,
]
```

---

## Scheduled Jobs

### 1. TerminateExpiredSubscriptionsJob

**Schedule:** Daily at 00:30 (Asia/Jakarta)  
**Frequency:** Once per day  
**Config:** `app.saas.auto_termination_enabled` (default: true)

```php
// Runs daily and terminates subscriptions with ends_at < now()
Schedule::job(new TerminateExpiredSubscriptionsJob())
    ->dailyAt('00:30')
    ->name('saas-terminate-expired-subscriptions');
```

---

### 2. SuspendServicesForOverdueInvoicesJob

**Schedule:** Twice daily at 06:00 and 18:00 (Asia/Jakarta)  
**Frequency:** Two times per day  
**Grace Period:** 7 days (configurable in job)  
**Config:** `app.saas.auto_suspension_enabled` (default: true)

```php
// Runs 2x daily and suspends services with invoices 7+ days overdue
Schedule::job(new SuspendServicesForOverdueInvoicesJob())
    ->twiceDaily(6, 18)
    ->name('saas-suspend-overdue-services');
```

**Workflow:**
1. Find invoices: `is_paid=false` AND `due_date < now()-7days`
2. For each invoice, get active subscription for company
3. Call `suspendDueToOverdueInvoice()` to suspend
4. Company receives notification (optional - implement in service)

---

### 3. CheckEmployeeCountLimitsJob

**Schedule:** Daily at 01:00 (Asia/Jakarta)  
**Frequency:** Once per day  
**Config:** `app.saas.employee_limit_enforcement_enabled` (default: true)

```php
// Runs daily and checks for employee count violations
Schedule::job(new CheckEmployeeCountLimitsJob())
    ->dailyAt('01:00')
    ->name('saas-check-employee-count-limits');
```

**Workflow:**
1. Get all active/trial/suspended subscriptions with packages
2. For each subscription, get max_employees feature limit
3. Count active employees for company
4. If count > limit, call `suspendDueToEmployeeCountViolation()`
5. Company receives notification (optional - implement in service)

---

## Database Schema Changes

### Subscription Table

Added columns to track suspension and termination:

```sql
ALTER TABLE subscriptions ADD COLUMN terminated_at TIMESTAMP NULL AFTER ends_at;
ALTER TABLE subscriptions ADD COLUMN termination_reason TEXT NULL;
ALTER TABLE subscriptions ADD COLUMN suspended_at TIMESTAMP NULL;
ALTER TABLE subscriptions ADD COLUMN suspension_reason TEXT NULL;
```

**Migration:** `database/migrations/2026_04_23_150000_add_termination_suspension_to_subscriptions.php`

---

## Subscription Status Flow

```
┌─────────────────────────────────────────────────────┐
│                    TRIAL                            │
│ (trial_ends_at = future)                           │
└────────────────┬──────────────────────────────────┘
                 │
                 ├─→ [Upgraded] ─→ ACTIVE (status='active')
                 │
                 └─→ [Not Renewed] ─→ EXPIRED (status='expired')

┌─────────────────────────────────────────────────────┐
│                    ACTIVE                           │
│ (ends_at = future)                                 │
└────┬──────────────────────────────┬────────────────┘
     │                              │
     ├─→ [ends_at < now] ─────────→ EXPIRED
     │   (Job: TerminateExpiredSubscriptionsJob)
     │
     ├─→ [Invoice 7+ days overdue] ─→ SUSPENDED
     │   (Job: SuspendServicesForOverdueInvoicesJob)
     │
     └─→ [Employees > limit] ─────→ SUSPENDED
         (Job: CheckEmployeeCountLimitsJob)

┌─────────────────────────────────────────────────────┐
│                   SUSPENDED                         │
│ (suspension_reason = set)                          │
└────┬──────────────────────────────────────────────┘
     │
     ├─→ [Payment Received] ─→ ACTIVE
     │   (Call: reactivateSuspended())
     │
     └─→ [Employees < limit] ─→ ACTIVE
         (Call: reactivateSuspended())

┌─────────────────────────────────────────────────────┐
│                    EXPIRED                          │
│ (terminated_at = set)                              │
└───────────────────────────────────────────────────┘
   (No automatic reactivation - requires manual intervention)
```

---

## Usage Examples

### Example 1: Create Employee with Validation

```php
use App\Services\EmployeeCountValidator;
use App\Exceptions\SubscriptionValidationException;

class CreateEmployeeController
{
    public function store(Request $request)
    {
        $company = auth()->user()->company;
        $validator = app(EmployeeCountValidator::class);

        // Validate before creating
        try {
            $validator->validateCanAddEmployees($company, countToAdd: 1);
        } catch (SubscriptionValidationException $e) {
            return response()->json($e->render(), 422);
        }

        // Safe to create
        $employee = Employee::create($request->validated());
        return response()->json($employee, 201);
    }
}
```

## Runtime Enforcement (Workaround for negative scenarios)

Employee plan limits are now enforced **before creating employees** at the API layer:

- **`POST /v1/hcm/employees`**: throws `EMPLOYEE_COUNT_EXCEEDED` (422) before validating payload when the company is already over the plan limit.
- **`POST /v1/hcm/employees/bulk-upload`**: throws `EMPLOYEE_COUNT_EXCEEDED` (422) during import when the first new employee would exceed the plan limit (import remains all-or-nothing).

Implementation lives in `backend/app/Http/Controllers/Api/HcmEmployeeController.php` and uses `App\Services\EmployeeCountValidator`.

---

## Negative Handling Summary (UI + BE)

Ringkasan paling penting agar UI dan backend “nggak melenceng”:

- **`ends_at` wajib untuk status `active|trial`**
  - Create: enforced via validator
  - Update: enforced via manual 422 guard (prevents “active without end”)

- **Overdue invoice hanya diproses jika `due_date` terisi**
  - Job skip invoice `due_date=null`
  - Create invoice API sudah require `due_date` → data baru aman; data lama perlu audit/backfill bila ada.

- **Employee limit enforcement ada dua lapis**
  - Runtime block saat create/bulk employee: `EMPLOYEE_COUNT_EXCEEDED` (422)
  - Daily checker: suspend subscription jika sudah terlanjur over-limit (drift)

- **Tenant context wajib untuk create/bulk employee**
  - Error `TENANT_CONTEXT_REQUIRED` (422)
  - UI harus menampilkan error ini sebagai indikasi bug/flow company context.

---

### Example 2: Manual Reactivation After Payment

```php
use App\Services\SubscriptionTerminationService;

class PaymentWebhookController
{
    public function handlePaymentSuccess(Request $request)
    {
        $invoice = Invoice::findOrFail($request->input('invoice_id'));
        $service = app(SubscriptionTerminationService::class);

        // Mark invoice as paid
        $invoice->markAsPaid();

        // Get subscription
        $subscription = Subscription::where('company_id', $invoice->company_id)
            ->where('status', 'suspended')
            ->firstOrFail();

        // Reactivate if suspended
        if (str_contains($subscription->suspension_reason, 'overdue')) {
            $service->reactivateSuspended($subscription, 'Invoice payment received');
            
            // Notify customer
            // $subscription->company->notify(new ServiceReactivated());
        }

        return response()->json(['status' => 'success']);
    }
}
```

---

### Example 3: Check Current Status

```php
use App\Services\EmployeeCountValidator;

class SubscriptionDashboardController
{
    public function show(Company $company)
    {
        $validator = app(EmployeeCountValidator::class);

        $status = [
            'subscription' => $company->activeSubscription(),
            'employee_limit' => $validator->getPlanEmployeeLimit($company),
            'active_employees' => $validator->getActiveEmployeeCount($company->id),
            'remaining_slots' => $validator->getRemainingSlots($company),
            'is_in_violation' => $validator->isInViolation($company),
        ];

        return response()->json($status);
    }
}
```

---

## Testing

Run feature tests:

```bash
php artisan test tests/Feature/SubscriptionManagementTest.php
```

Tests included:
- ✅ Terminate expired subscriptions
- ✅ Query expired subscriptions
- ✅ Suspend due to overdue invoices
- ✅ Query overdue invoices
- ✅ Employee count validation (within limit)
- ✅ Employee count validation (exceeding limit)
- ✅ Detect employee violations
- ✅ Suspend due to employee violation
- ✅ Reactivate suspended subscriptions
- ✅ Reject reactivation of non-suspended subscriptions
- ✅ Allow unlimited employees

---

## Configuration

Add to `.env` or `config/app.php`:

```env
# SaaS Auto-Management
SAAS_AUTO_TERMINATION_ENABLED=true
SAAS_AUTO_SUSPENSION_ENABLED=true
SAAS_EMPLOYEE_LIMIT_ENFORCEMENT_ENABLED=true

# Grace periods (in days)
SAAS_INVOICE_OVERDUE_GRACE_DAYS=7
```

Disable individual features:

```php
// config/app.php
'saas' => [
    'auto_termination_enabled' => env('SAAS_AUTO_TERMINATION_ENABLED', true),
    'auto_suspension_enabled' => env('SAAS_AUTO_SUSPENSION_ENABLED', true),
    'employee_limit_enforcement_enabled' => env('SAAS_EMPLOYEE_LIMIT_ENFORCEMENT_ENABLED', true),
],
```

---

## Future Enhancements

1. **Notifications** - Implement email notifications in termination service
2. **Grace Period Customization** - Allow companies to configure grace periods
3. **Auto-Renewal** - Implement automatic renewal logic (field exists, not implemented)
4. **Pro-rata Refunds** - Calculate partial refunds on mid-period cancellation
5. **Feature Usage Tracking** - Track actual feature usage vs. plan limits
6. **Upgrade/Downgrade Workflow** - Handle plan changes with proration
7. **Payment Retry** - Retry failed payments before suspension
8. **Webhook Notifications** - Notify customer systems of status changes

---

## Troubleshooting

### Subscriptions Not Being Terminated
- Check if job is running: `php artisan schedule:list`
- Check logs: `storage/logs/laravel.log`
- Manually test: `php artisan queue:work` or invoke directly in tinker

### Employees Still Adding When Over Limit
- Ensure validator is called before creating employees
- Check if plan has employee limit feature defined
- Verify feature.limit is set (not null/0)

### Service Not Reactivating After Payment
- Ensure `reactivateSuspended()` is called in payment webhook
- Check if subscription status is actually 'suspended'
- Verify suspension_reason doesn't prevent reactivation

---

## Related Files

- **Services:** `/backend/app/Services/SubscriptionTerminationService.php`, `EmployeeCountValidator.php`
- **Jobs:** `/backend/app/Jobs/TerminateExpiredSubscriptionsJob.php`, `SuspendServicesForOverdueInvoicesJob.php`, `CheckEmployeeCountLimitsJob.php`
- **Models:** `/backend/app/Models/Subscription.php`, `Invoice.php`, `Company.php`, `Package.php`, `PackageFeature.php`
- **Routes:** `/backend/routes/console.php` (scheduler registration)
- **Tests:** `/backend/tests/Feature/SubscriptionManagementTest.php`
- **Migrations:** `/backend/database/migrations/2026_04_23_150000_add_termination_suspension_to_subscriptions.php`

---

**Last Updated:** 2026-04-23  
**Status:** Implementation Complete ✅
