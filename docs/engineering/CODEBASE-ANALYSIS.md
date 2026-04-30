# Codebase Structure Analysis Report
**Date:** April 16, 2026  
**Scope:** Auth/RBAC, Data Visibility, Payment/Billing, Ticket System, File Mappings

---

## 1. AUTH & RBAC ISSUES

### Route Guards & Middleware Overview

#### **Authentication Flow**
- **Middleware Chain:** `api.token` → `tenant.context`
  - [AuthenticateApiToken.php](backend/app/Http/Middleware/AuthenticateApiToken.php#L1-L41): Validates Bearer token, sets `$request->user()`
  - [ResolveTenantContext.php](backend/app/Http/Middleware/ResolveTenantContext.php#L1-L48): Resolves active company from user's memberships, sets `activeCompanyId`

#### **Routes Analyzed**

**1. /promotions Routes** [routes/api.php](backend/routes/api.php#L334-L340)
```
GET    /v1/hcm/promotions               → HcmPromotionController::index
POST   /v1/hcm/promotions               → HcmPromotionController::store
GET    /v1/hcm/promotions/{id}          → HcmPromotionController::show
PUT    /v1/hcm/promotions/{id}          → HcmPromotionController::update
DELETE /v1/hcm/promotions/{id}          → HcmPromotionController::destroy
GET    /v1/hcm/promotions/users/{userId}/promotions → HcmPromotionController::promotionsForUser
```

**Guard Status:** ✅ **PROPERLY GUARDED**
- [HcmPromotionController.php](backend/app/Http/Controllers/Api/HcmPromotionController.php#L1-L200) uses `EnsuresHcmAdmin` trait
- `index()` [L29-67]: Calls `$this->ensureHcmAdmin($request)` → returns 403 if not admin
- `store()` [L147-186]: Requires HCM Admin
- `update()` [L200-240]: Requires HCM Admin
- `destroy()` [L244-250]: Calls `$this->ensureHcmAdmin($request)`
- `show()` [L76-97]: **PARTIAL GUARD** - Allows employees to view own promotion records
- `promotionsForUser()` [L103-128]: Employee can view own; admin can view any

**Authorization Mechanism:** [EnsuresHcmAdmin.php](backend/app/Http/Controllers/Api/Concerns/EnsuresHcmAdmin.php)
```php
protected function ensureHcmAdmin(Request $request): ?JsonResponse
  - If activeCompanyId exists: calls ensureHcmAdminForCompany($request, $companyId)
  - Otherwise: checks $user->isHcmAdmin() globally
  - Returns 403 JSON if unauthorized
```

---

**2. /policies Routes** [routes/api.php](backend/routes/api.php#L116-L120)
```
GET    /v1/hcm/policies               → HcmEmployeeController::policies
POST   /v1/hcm/policies               → HcmEmployeeController::storePolicy
PUT    /v1/hcm/policies/{id}          → HcmEmployeeController::updatePolicy
DELETE /v1/hcm/policies/{id}          → HcmEmployeeController::destroyPolicy
GET    /v1/hcm/policies/export        → HcmEmployeeController::exportPolicies
```

**Guard Status:** ✅ **PROPERLY GUARDED**
- [HcmEmployeeController.php](backend/app/Http/Controllers/Api/HcmEmployeeController.php#L1998-L2120) uses `EnsuresHcmAdmin` trait
- `policies()` [L1998-L2062]: Requires active company context, checks tenant scope
- `storePolicy()` [L2065-L2120]: Calls `$this->ensureHcmAdmin($request)` in first check
- `updatePolicy()`: Calls admin check
- `destroyPolicy()`: Calls admin check
- **Data Scoping:** All policy queries filtered by `company_id` matching active tenant

---

**3. /overtime-types Routes** [routes/api.php](backend/routes/api.php#L193-L196)
```
GET    /v1/hcm/overtime-types         → HcmOvertimeTypeController::index
POST   /v1/hcm/overtime-types         → HcmOvertimeTypeController::store
PUT    /v1/hcm/overtime-types/{id}    → HcmOvertimeTypeController::update
DELETE /v1/hcm/overtime-types/{id}    → HcmOvertimeTypeController::destroy
```

**Guard Status:** ✅ **PROPERLY GUARDED**
- [HcmOvertimeTypeController.php](backend/app/Http/Controllers/Api/HcmOvertimeTypeController.php) uses `EnsuresHcmAdmin`
- `index()` [L17-26]: GET returns both active and inactive for admins; inactive-only for employees
- `store()` [L28-60]: Requires `ensureHcmAdmin($request)` check
- `update()` [L62-100]: Requires `ensureHcmAdmin($request)` check
- `destroy()` [L102-110]: Requires `ensureHcmAdmin($request)` check

---

**4. /tickets Routes** [routes/api.php](backend/routes/api.php#L265-L286)
```
GET    /v1/hcm/tickets                → HcmTicketController::index
POST   /v1/hcm/tickets                → HcmTicketController::store
GET    /v1/hcm/tickets/{id}           → HcmTicketController::show
PUT    /v1/hcm/tickets/{id}           → HcmTicketController::update
DELETE /v1/hcm/tickets/{id}           → HcmTicketController::destroy
POST   /v1/hcm/tickets/{id}/comments  → HcmTicketController::addComment
POST   /v1/hcm/tickets/{id}/attachments → HcmTicketController::addAttachment
```

**Guard Status:** ✅ **PROPERLY GUARDED WITH TENANT SCOPING**
- [HcmTicketController.php](backend/app/Http/Controllers/Api/HcmTicketController.php#L1-L100)
- **No explicit admin check** - Available to all authenticated users in tenant context
- `index()` [L20-80]: 
  - Validates `activeCompanyId` exists (scoped to tenant)
  - Employees see own tickets only (`where('user_id', $user->id)`)
  - Admins see all tickets in active company
  - Tenant isolation: `whereHas('reporter.companyMemberships', ...)` filters by active company membership
- `store()` [L87-128]: Creates ticket for current user in active company
- Individual ticket access checked via `authorizedTicket()` method [L489-510]:
  - Validates reporter's active company membership matches tenant context
  - Checks if requester is admin OR ticket owner

**⚠️ NO SUBSCRIPTION CHECK for Ticket Creation** - This is accessible to all authenticated users, regardless of subscription status. Frontend may have modal, but API has no feature gate.

---

## 2. DATA VISIBILITY ISSUES

### /attendance/admin Endpoint

**Route:** [routes/api.php](backend/routes/api.php#L122-L123)
```
GET   /v1/hcm/attendance/admin        → AttendanceController::adminIndex
PUT   /v1/hcm/attendance/admin/record → AttendanceController::adminUpsertRecord
```

**Guard Status:** ✅ **REQUIRES HCM ADMIN + PROPER TENANT SCOPING**

#### `adminIndex()` Implementation [AttendanceController.php](backend/app/Http/Controllers/Api/AttendanceController.php#L248-L358)

**Authorization:**
```php
public function adminIndex(Request $request): JsonResponse
{
    $forbidden = $this->ensureHcmAdmin($request);  // Line 156
    if ($forbidden) return $forbidden;
    
    $activeCompanyId = $this->activeCompanyId($request); // Line 169
```

**Data Filtering Logic:**

1. **User Scope** - `adminAttendanceFilteredQuery()` [L130-L155]:
   ```php
   // Line 140-148: Tenant context applied to LEFT JOIN
   ->leftJoin('attendance_records as ar', function ($join) use ($dateYmd, $companyId) {
       $join->on('ar.user_id', '=', 'users.id')
           ->whereDate('ar.work_date', '=', $dateYmd);
       
       if ($companyId) {
           $join->where(function ($inner) use ($companyId): void {
               $inner->where('ar.company_id', '=', $companyId)
                   ->orWhereNull('ar.company_id');  // Allows legacy NULL records
           });
       }
   });
   ```
   **Issue Identified:** `orWhereNull('ar.company_id')` creates data leakage risk. Legacy records with NULL company_id can appear in multiple tenants. Test confirms this is intentional for backward compatibility [AttendanceAdminTenantScopeTest.php](backend/tests/Feature/AttendanceAdminTenantScopeTest.php#L69-L102).

2. **Employee Profile Scope** [L151-154]:
   ```php
   if ($companyId) {
       $q->where(function ($inner) use ($companyId): void {
           $inner->where('ep.company_id', $companyId)->orWhereNull('ep.company_id');
       });
   }
   ```

3. **Attendance Record Retrieval** [L289-295]:
   ```php
   $recordsQuery = AttendanceRecord::query();
   $this->applyTenantScope($recordsQuery, $activeCompanyId); // Enforces company_id
   $records = $recordsQuery
       ->whereIn('user_id', $userIds)
       ->whereDate('work_date', $dateYmd)
       ->get();
   ```

**Summary:** ✅ Properly scoped per tenant via `activeCompanyId`, though allows NULL company_id for legacy data.

---

### /policies Endpoint Data Filtering

**Route:** [routes/api.php](backend/routes/api.php#L116)

**Implementation:** [HcmEmployeeController.php](backend/app/Http/Controllers/Api/HcmEmployeeController.php#L1998-L2062)

```php
public function policies(Request $request): JsonResponse
{
    if ($forbidden = $this->ensureHcmAdmin($request)) {
        return $forbidden;  // Line 2002
    }
    
    $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
    
    $paginator = Policy::query()
        ->where('company_id', $companyId)  // Line 2026 - STRICT TENANT SCOPE
        ->orderByDesc('created_at')
        ->paginate($perPage);
```

**Summary:** ✅ Properly scoped to active company only. No legacy NULL company_id allowance.

---

## 3. PAYMENT & BILLING

### Payment Gateway Integration

**Current Status:** 🟡 **STRIPE INTEGRATION PRESENT, XENDIT SKELETON ONLY**

#### PaymentGatewayService [Services/PaymentGatewayService.php](backend/app/Services/PaymentGatewayService.php#L1-L150)

```php
class PaymentGatewayService {
    protected string $gateway;
    
    public function __construct(string $gateway = 'stripe')
    
    public function charge(array $data): array {
        return match ($this->gateway) {
            'stripe' => $this->chargeWithStripe($data),
            'xendit' => $this->chargeWithXendit($data),
            default => ['success' => false, 'error' => 'Unsupported gateway'],
        };
    }
```

**Stripe Implementation:**
- ✅ `chargeWithStripe()` [L55-87]: Makes HTTP POST to `https://api.stripe.com/v1/charges`
  - Converts amount to cents
  - Uses `config('services.stripe.secret')` for authentication
  - Returns `gateway_reference` (charge ID), status
  - Handles exceptions

- ✅ `verifyWithStripe()` [L88-108]: Queries charge status via GET `/v1/charges/{id}`

- ✅ `handleStripeWebhook()` [L109-130]: Processes `charge.succeeded` webhook
  - Updates Payment model with `status: 'completed'`, `verified_at`
  - Returns 200 success for all webhook events

**Xendit Implementation:**
- 🔴 `chargeWithXendit()` [L133-155]: Skeleton only
  - HTTP POST to `https://api.xendit.co/charges`
  - Uses `config('services.xendit.key')` for basic auth
  - Supports CARD payment type

- 🔴 `verifyWithXendit()` [L157-165]: Stub (not implemented)

- 🔴 `handleXenditWebhook()` [L167-171]: Returns success but does nothing

---

### Invoice Generation

**Model:** [Models/Invoice.php](backend/app/Models/Invoice.php)

**Controllers:**
1. [InvoiceController.php](backend/app/Http/Controllers/Api/InvoiceController.php) - SaaS invoices (admin)
2. [HcmCompanyInvoiceController.php](backend/app/Http/Controllers/Api/HcmCompanyInvoiceController.php) - Tenant invoices

#### HcmCompanyInvoiceController Methods:

**`index()`** [HcmCompanyInvoiceController.php](backend/app/Http/Controllers/Api/HcmCompanyInvoiceController.php#L18-L72)
- Lists invoices for active company
- Requires `ensureHcmAdmin($request)` (tenant admin or owner)
- Returns formatted invoices with pagination

**`show()`** [L76-92]
- Retrieves single invoice for active company
- Requires tenant admin

**`download()`** [L96-121]
- Generates/downloads invoice PDF
- Path: `private/{pdf_path}`
- Uses [InvoiceService::generatePdf()](backend/app/Services/InvoiceService.php)

**`mockPay()`** [L125-163]
- **Development-only mock payment flow**
- Updates invoice: `is_paid = true`, `status = 'paid'`
- If subscription exists, calls `SubscriptionActivationFromInvoiceService::activateFromPaidInvoice()`
- Routes: [routes/api.php](backend/routes/api.php#L319) - `POST /v1/hcm/billing/invoices/{id}/mock-pay`

---

### Recurring Billing Automation

**Status:** 🔴 **NOT IMPLEMENTED**

**Subscription Model:** [Models/Subscription.php](backend/app/Models/Subscription.php)
- Fields: `status` (trial|pending_payment|active|expired|cancelled)
- Fields: `billing_cycle` (monthly|yearly), `auto_renew`, `trial_ends_at`, `ends_at`

**No Cronjob Found For:**
- ❌ Expiry checks and renewal invoicing
- ❌ Auto-renew processing
- ❌ Subscription state transitions
- ❌ Failed payment retries

---

### Billing Flow Summary

1. **Tenant Checkout** → [HcmSubscriptionCheckoutController::checkout()](backend/app/Http/Controllers/Api/HcmSubscriptionCheckoutController.php#L25-L180)
   - Creates/reuses `pending_payment` subscription
   - Generates invoice (status: `draft`)
   - Returns subscription + invoice ID

2. **Payment (Mock)** → [HcmCompanyInvoiceController::mockPay()](backend/app/Http/Controllers/Api/HcmCompanyInvoiceController.php#L125-L163)
   - Sets invoice `is_paid = true`, `status = 'paid'`
   - Activates subscription if linked

3. **Real Payment** → [PaymentController::store()](backend/app/Http/Controllers/Api/PaymentController.php#L70-L110)
   - Records payment with gateway reference
   - Admin-only endpoint

---

## 4. TICKET SYSTEM

### Ticket Routes & 403 Response

**Routes:** [routes/api.php](backend/routes/api.php#L265-L286)

**403 Forbidden Responses Come From:**

1. **Missing Tenant Context** [HcmTicketController.php](backend/app/Http/Controllers/Api/HcmTicketController.php#L24-L33)
   ```php
   if (!$activeCompanyId) {
       return response()->json([
           'success' => false,
           'error' => [
               'code' => 'TENANT_CONTEXT_REQUIRED',
               'message' => 'Active company context is required.',
           ],
       ], 422);  // 422, NOT 403
   }
   ```

2. **Unauthorized Ticket Access** - `forbidden()` method [L546-553]
   ```php
   private function forbidden(): JsonResponse {
       return response()->json([
           'error' => ['code' => 'AUTH_FORBIDDEN', 'message' => 'Forbidden.'],
       ], 403);
   }
   ```
   Called by:
   - `update()` [L174]: Non-admin cannot update status/assignee/sla_due_at
   - `delete()` [L257]: Calls `authorizedTicket()` check

3. **Failed Authorization Check** - `authorizedTicket()` [L489-510]
   ```php
   private function authorizedTicket(Request $request, int $id): ?Ticket {
       // Ticket must exist in active company
       $query->whereHas('reporter.companyMemberships', function ($m) use ($activeCompanyId): void {
           $m->where('company_id', $activeCompanyId)->where('status', 'active');
       });
       
       if (!$isAdmin) {
           $query->where('user_id', $user?->id);  // Employee sees own only
       }
       return $query->find($id);  // Returns null if unauthorized
   }
   ```

---

### Subscription Check Modal

**Current Status:** 🔴 **NO SUBSCRIPTION CHECK FOR TICKET CREATION**

- No `feature_required` check in backend
- `store()` method [HcmTicketController.php](backend/app/Http/Controllers/Api/HcmTicketController.php#L87-L128) accepts any authenticated user in active tenant
- Frontend likely has modal (not examined in this analysis)
- **API allows ticket creation for trial/expired subscriptions**

---

## 5. FILE LOCATIONS & MAPPINGS

### Route → Controller Mapping

| Route | Method | Controller | File Path | Line |
|-------|--------|------------|-----------|------|
| `GET /v1/hcm/promotions` | index | HcmPromotionController | [HcmPromotionController.php](backend/app/Http/Controllers/Api/HcmPromotionController.php) | 29 |
| `POST /v1/hcm/promotions` | store | HcmPromotionController | [HcmPromotionController.php](backend/app/Http/Controllers/Api/HcmPromotionController.php) | 147 |
| `GET /v1/hcm/promotions/{id}` | show | HcmPromotionController | [HcmPromotionController.php](backend/app/Http/Controllers/Api/HcmPromotionController.php) | 76 |
| `PUT /v1/hcm/promotions/{id}` | update | HcmPromotionController | [HcmPromotionController.php](backend/app/Http/Controllers/Api/HcmPromotionController.php) | 200 |
| `DELETE /v1/hcm/promotions/{id}` | destroy | HcmPromotionController | [HcmPromotionController.php](backend/app/Http/Controllers/Api/HcmPromotionController.php) | 244 |
| `GET /v1/hcm/policies` | policies | HcmEmployeeController | [HcmEmployeeController.php](backend/app/Http/Controllers/Api/HcmEmployeeController.php) | 1998 |
| `POST /v1/hcm/policies` | storePolicy | HcmEmployeeController | [HcmEmployeeController.php](backend/app/Http/Controllers/Api/HcmEmployeeController.php) | 2065 |
| `GET /v1/hcm/policies/{id}` | policy | HcmEmployeeController | [HcmEmployeeController.php](backend/app/Http/Controllers/Api/HcmEmployeeController.php) | ~2120 |
| `PUT /v1/hcm/policies/{id}` | updatePolicy | HcmEmployeeController | [HcmEmployeeController.php](backend/app/Http/Controllers/Api/HcmEmployeeController.php) | ~2200 |
| `DELETE /v1/hcm/policies/{id}` | destroyPolicy | HcmEmployeeController | [HcmEmployeeController.php](backend/app/Http/Controllers/Api/HcmEmployeeController.php) | ~2250 |
| `GET /v1/hcm/overtime-types` | index | HcmOvertimeTypeController | [HcmOvertimeTypeController.php](backend/app/Http/Controllers/Api/HcmOvertimeTypeController.php) | 17 |
| `POST /v1/hcm/overtime-types` | store | HcmOvertimeTypeController | [HcmOvertimeTypeController.php](backend/app/Http/Controllers/Api/HcmOvertimeTypeController.php) | 28 |
| `PUT /v1/hcm/overtime-types/{id}` | update | HcmOvertimeTypeController | [HcmOvertimeTypeController.php](backend/app/Http/Controllers/Api/HcmOvertimeTypeController.php) | 62 |
| `DELETE /v1/hcm/overtime-types/{id}` | destroy | HcmOvertimeTypeController | [HcmOvertimeTypeController.php](backend/app/Http/Controllers/Api/HcmOvertimeTypeController.php) | 102 |
| `GET /v1/hcm/tickets` | index | HcmTicketController | [HcmTicketController.php](backend/app/Http/Controllers/Api/HcmTicketController.php) | 20 |
| `POST /v1/hcm/tickets` | store | HcmTicketController | [HcmTicketController.php](backend/app/Http/Controllers/Api/HcmTicketController.php) | 87 |
| `GET /v1/hcm/tickets/{id}` | show | HcmTicketController | [HcmTicketController.php](backend/app/Http/Controllers/Api/HcmTicketController.php) | 131 |
| `PUT /v1/hcm/tickets/{id}` | update | HcmTicketController | [HcmTicketController.php](backend/app/Http/Controllers/Api/HcmTicketController.php) | 154 |
| `DELETE /v1/hcm/tickets/{id}` | destroy | HcmTicketController | [HcmTicketController.php](backend/app/Http/Controllers/Api/HcmTicketController.php) | 239 |
| `GET /v1/hcm/attendance/admin` | adminIndex | AttendanceController | [AttendanceController.php](backend/app/Http/Controllers/Api/AttendanceController.php) | 248 |
| `PUT /v1/hcm/attendance/admin/record` | adminUpsertRecord | AttendanceController | [AttendanceController.php](backend/app/Http/Controllers/Api/AttendanceController.php) | ~450 |
| `GET /v1/hcm/billing/invoices` | index | HcmCompanyInvoiceController | [HcmCompanyInvoiceController.php](backend/app/Http/Controllers/Api/HcmCompanyInvoiceController.php) | 18 |
| `GET /v1/hcm/billing/invoices/{id}` | show | HcmCompanyInvoiceController | [HcmCompanyInvoiceController.php](backend/app/Http/Controllers/Api/HcmCompanyInvoiceController.php) | 76 |
| `POST /v1/hcm/billing/invoices/{id}/mock-pay` | mockPay | HcmCompanyInvoiceController | [HcmCompanyInvoiceController.php](backend/app/Http/Controllers/Api/HcmCompanyInvoiceController.php) | 125 |
| `POST /v1/hcm/billing/checkout` | checkout | HcmSubscriptionCheckoutController | [HcmSubscriptionCheckoutController.php](backend/app/Http/Controllers/Api/HcmSubscriptionCheckoutController.php) | 25 |

---

### Middleware Chain

**Applied to all `/v1/hcm/*` routes:**
```
Route::prefix('v1/hcm')
  ->middleware(['api.token', 'tenant.context'])
  ->group(...)
```

**Middleware Classes:**
1. [AuthenticateApiToken.php](backend/app/Http/Middleware/AuthenticateApiToken.php) - Validates Bearer token
2. [ResolveTenantContext.php](backend/app/Http/Middleware/ResolveTenantContext.php) - Resolves active company

---

### Helper Traits & Utilities

| Trait | File | Purpose |
|-------|------|---------|
| `EnsuresHcmAdmin` | [EnsuresHcmAdmin.php](backend/app/Http/Controllers/Api/Concerns/EnsuresHcmAdmin.php) | Validates HCM Admin role, tenant-aware |
| `applyTenantScope()` | [AttendanceController.php](backend/app/Http/Controllers/Api/AttendanceController.php#L43-L48) | Scopes AttendanceRecord queries by company_id |

---

## 6. KEY FINDINGS & RECOMMENDATIONS

### ✅ Strengths

1. **Consistent RBAC Pattern**: All admin endpoints use `EnsuresHcmAdmin` trait consistently
2. **Tenant Isolation**: Active company context properly enforced via middleware
3. **Stripe Integration**: Basic production-ready Stripe charge/verify implementation
4. **Scoped Queries**: Policy, ticket, attendance all properly scoped to tenant

### 🔴 Critical Issues

1. **Ticket Subscription Gate Missing**
   - No feature check for ticket creation
   - Anyone with valid subscription status can create tickets
   - Recommend: Add subscription status check before `store()`, or verify feature is intended as free

2. **Xendit Implementation Incomplete**
   - Skeleton structure present but `verify()` and webhook handling are stubs
   - No production readiness

3. **No Recurring Billing Automation**
   - No cronjob or scheduled job for subscription renewal
   - Manual `mockPay()` required for testing
   - Recommend: Implement subscription expiry checks + renewal invoice generation

4. **Legacy NULL company_id in AttendanceRecord**
   - Intentional backward compatibility but creates subtle data leakage risk
   - Test confirms behavior [AttendanceAdminTenantScopeTest.php](backend/tests/Feature/AttendanceAdminTenantScopeTest.php)
   - Recommend: Document explicitly or migrate NULL records to explicit company_id

### 🟡 Design Observations

1. **No Subscription Feature Matrix**
   - PaymentController / SubscriptionController handle SaaS subscriptions
   - No explicit feature gating based on subscription plan
   - Recommend: Implement feature flag check in critical flows

2. **Mock Payment Strategy**
   - `mockPay()` endpoint useful for testing but could be a security risk if exposed in production
   - Recommend: Verify environment guards or remove before going live

3. **No Webhook Signature Validation**
   - `handleStripeWebhook()` processes all Stripe events without signature verification
   - Recommend: Add HMAC signature validation using `stripe_signing_secret`

---

## 7. TESTS CONFIRMING EXPECTED BEHAVIOR

- [AttendanceAdminTenantScopeTest.php](backend/tests/Feature/AttendanceAdminTenantScopeTest.php): Confirms attendance records properly scoped by company_id, with NULL company_id fallback
- [HcmUserManagementApiTest.php](backend/tests/Feature/HcmUserManagementApiTest.php): Confirms role/permission scoping per tenant
- [ReconciliationExportApiTest.php](backend/tests/Feature/ReconciliationExportApiTest.php): Confirms feature-based access control

---

**Generated:** 2026-04-16  
**Prepared For:** Architecture Review & Security Audit
