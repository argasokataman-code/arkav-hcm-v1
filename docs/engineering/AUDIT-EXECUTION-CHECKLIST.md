# AUDIT EXECUTION CHECKLIST - Customer Account Security Issues

## Summary of Findings
- **7 CRITICAL issues** identified in customer account (multi-tenant data leakage, missing subscription gates, incomplete payment integration)
- **Context7 References**: Used Laravel authorization patterns, Vue conditional rendering, Stripe webhook standards
- **Status**: Partially fixed (migration + models created, controllers 30% updated)

---

## PHASE 1: RUN CRITICAL FIXES (15 min)

### Step 1: Run Migration for Company ID Scoping
```bash
cd /Users/vanviakingali/arcav_new_v2/backend

# Apply the new migration that adds company_id to promotion/resignation/termination tables
php artisan migrate --step

# Verify migration ran:
php artisan migrate:status | grep "2026_04_26_120000"
```

### Step 2: Test Multi-Tenant Isolation (Quick Smoke Test)
```bash
# Use SQLite/MySQL client to verify data is properly scoped:
# SQL: SELECT COUNT(*) FROM hcm_promotions WHERE company_id IS NULL;
# Should be 0 (or matches legacy records with backfill)
```

---

## PHASE 2: FINISH CONTROLLER UPDATES (Remaining Tenant Scoping)

### File: HcmResignationController
**Location**: `backend/app/Http/Controllers/Api/HcmResignationController.php`

**Methods to update** (add activeCompanyId validation + company_id filtering):
1. `show()` - Line ~89: Add tenant context check + filter by company_id
2. `resignationsForUser()` - Line ~113: Filter resignations by company_id
3. `store()` - Line ~132: Add company_id when creating
4. `update()` - Line ~155: Filter by company_id before updating
5. `destroy()` - Add company_id filter before deleting

**Template** (use same pattern as HcmPromotionController):
```php
// At start of method
$activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
if ($activeCompanyId <= 0) {
    return response()->json(['success' => false, 'error' => ['code' => 'TENANT_CONTEXT_REQUIRED']], 422);
}

// In query
->where('company_id', $activeCompanyId)

// In create payload
'company_id' => $activeCompanyId,
```

### File: HcmTerminationController
**Location**: `backend/app/Http/Controllers/Api/HcmTerminationController.php`

**Same update pattern as HcmResignationController** - Apply to all CRUD methods

---

## PHASE 3: ADD SUBSCRIPTION FEATURE GATE

### File: HcmTicketController
**Location**: `backend/app/Http/Controllers/Api/HcmTicketController.php`
**Method**: `store()` - Line ~87

**Add this check after auth validation**:
```php
// Check subscription includes 'tickets' feature
$subscription = Subscription::where('company_id', $activeCompanyId)
    ->where('status', '!=', 'cancelled')
    ->where(function ($q) {
        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
    })
    ->first();

if (!$subscription || !$subscription->hasFeature('tickets')) {
    return response()->json([
        'success' => false,
        'error' => [
            'code' => 'SUBSCRIPTION_REQUIRED',
            'message' => 'Ticket feature requires an active subscription.',
        ],
    ], 403);
}
```

### Frontend: Show Subscription Modal (Vue)
**Location**: `frontend/resources/js/pages/tickets.vue` or ticket creation modal

```vue
<template>
  <div v-if="error?.code === 'SUBSCRIPTION_REQUIRED'" class="alert alert-warning">
    <h5>{{ error.message }}</h5>
    <button @click="goToUpgrade">Upgrade Subscription</button>
  </div>
</template>

<script>
const goToUpgrade = () => {
  window.location.href = '/packages';
};
</script>
```

---

## PHASE 4: PAYMENT GATEWAY SECURITY (High Priority)

### Stripe Webhook Signature Validation
**Location**: `backend/app/Http/Controllers/Api/PaymentController.php` (webhook handler)

```php
// At top of webhook handler
$payload = $request->getContent();
$sig_header = $request->header('stripe-signature');
$secret = config('services.stripe.webhook_secret');

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
} catch(\UnhandledMatchException $e) {
    return response('Invalid signature', 400);
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    return response('Signature verification failed', 400);
}
```

**Required .env**:
```
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxx
```

---

## PHASE 5: XENDIT COMPLETION

### Status Check
```bash
# Files to check:
- backend/app/Services/PaymentGatewayService.php
  - ✓ chargeXendit() implemented
  - ✗ verifyXenditCharge() is STUB - needs implementation
  - ✗ No webhook handler
```

### Implementation TODO
1. Update `verifyXenditCharge()` to call Xendit API
2. Add webhook signature validation for Xendit
3. Store idempotency keys for charge requests

---

## PHASE 6: RECURRING BILLING & NOTIFICATIONS

### Create Scheduled Task
**File**: `backend/routes/console.php`

```php
Schedule::call(new \App\Jobs\ProcessSubscriptionRenewals)
    ->daily()
    ->at('02:00')
    ->name('process-subscription-renewals');
```

### Create Job
```bash
php artisan make:job ProcessSubscriptionRenewals
```

---

## VERIFICATION CHECKLIST

After all fixes, run these tests:

```bash
cd /Users/vanviakingali/arcav_new_v2/backend

# 1. Test multi-tenant isolation
php artisan tinker
> Auth::loginUsingId(1); // HCM admin from Company A
> HcmPromotion::count(); // Should only show Company A records

# 2. Test subscription gate
# Create request with expired subscription → should get 403

# 3. Test webhook signature
# Use Stripe CLI to send test event:
stripe listen --forward-to localhost:8000/webhook/stripe

# 4. Check database migrations
php artisan migrate:status | grep "2026_04"
```

---

## DEPLOYMENT STEPS

```bash
cd /Users/vanviakingali/arcav_new_v2

# 1. Backup database
mysqldump -u root arcav_hcm > backups/pre-audit-fix.sql

# 2. Run migrations
cd backend && php artisan migrate

# 3. Run tests
php artisan test --filter=HcmPromotion

# 4. Deploy
git add -A
git commit -m "SECURITY: Fix multi-tenant data leakage in promotion/resignation/termination tables + add subscription feature gate"
git push origin main
```

---

## Timeline Estimate
- **Phase 1-2** (Tenant scoping): 30 min
- **Phase 3** (Subscription gate): 15 min
- **Phase 4-5** (Payment security): 1 hour
- **Phase 6** (Recurring billing): 1 hour
- **Total**: ~2.5 hours for full implementation

---

## Links to Reference
- Laravel Authorization: https://laravel.com/docs/11.x/authorization
- Vue Conditional Rendering: https://vuejs.org/guide/essentials/conditional.html
- Stripe Webhooks: https://stripe.com/docs/webhooks/verify-signature
- Xendit Verification: https://apireference.xendit.co/#verify-charge
