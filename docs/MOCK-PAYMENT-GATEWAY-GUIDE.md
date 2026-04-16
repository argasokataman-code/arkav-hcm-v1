# Mock Payment Gateway Guide

## Overview

The mock payment gateway allows you to **test payment flows without subscribing to Stripe/Xendit**. All transactions are simulated locally and can be reversed/reset easily.

**⚠️ Development Only:** These endpoints are disabled in production and only available in local/development environment.

---

## Quick Start

### 1. Create & Pay Invoice in One Step

```bash
curl -X POST http://localhost:8007/v1/mock/invoices/create-and-pay \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 500000,
    "description": "Test billing invoice",
    "currency": "IDR"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Mock invoice and payment created successfully",
  "data": {
    "invoice": {
      "id": 123,
      "number": "MOCK-20260416143022",
      "amount": 500000,
      "status": "paid"
    },
    "payment": {
      "id": 456,
      "gateway_reference": "mock_661c4a8b9c1f2",
      "status": "completed",
      "amount": 500000
    }
  }
}
```

---

## API Endpoints

### POST `/api/v1/mock/payments/create`

**Create a single mock payment for an existing invoice**

```bash
curl -X POST http://localhost:8007/v1/mock/payments/create \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_id": 123,
    "amount": 500000,
    "payment_method": "mock_card",
    "simulate_failure": false
  }'
```

**Parameters:**
- `invoice_id` (required) — ID of invoice to pay
- `amount` (required) — Payment amount
- `payment_method` (optional) — `mock_card` | `mock_bank` | `mock_ewallet`
- `simulate_failure` (optional) — Set `true` to simulate payment failure

**Success Response:**
```json
{
  "success": true,
  "message": "Mock payment processed successfully",
  "data": {
    "payment": {
      "id": 456,
      "gateway_reference": "mock_661c4a8b9c1f2",
      "status": "completed",
      "amount": 500000,
      "paid_at": "2026-04-16T14:30:22+07:00"
    },
    "invoice": {
      "id": 123,
      "status": "paid",
      "paid_at": "2026-04-16T14:30:22+07:00"
    }
  }
}
```

---

### POST `/api/v1/mock/invoices/create-and-pay`

**Create a new invoice AND process payment in one call**

```bash
curl -X POST http://localhost:8007/v1/mock/invoices/create-and-pay \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 500000,
    "description": "Subscription renewal - April 2026",
    "currency": "IDR",
    "simulate_failure": false
  }'
```

**Parameters:**
- `amount` (required) — Invoice amount
- `description` (optional) — Invoice description
- `currency` (optional) — `IDR` (default) | `USD`
- `simulate_failure` (optional) — Set `true` to simulate failure

**Response:** Same as `create` endpoint above

---

### GET `/api/v1/mock/test-cards`

**Get list of test card numbers for simulation**

```bash
curl http://localhost:8007/v1/mock/test-cards \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "number": "4242 4242 4242 4242",
      "name": "Visa Success",
      "result": "success",
      "description": "Payment will succeed"
    },
    {
      "number": "4000 0000 0000 0002",
      "name": "Visa Declined",
      "result": "fail",
      "description": "Payment will be declined"
    },
    ...
  ],
  "note": "These are test card numbers for mock payment simulation..."
}
```

---

### POST `/api/v1/mock/webhook/charge-succeeded`

**Manually trigger a charge.succeeded webhook event**

Useful for testing webhook handlers without waiting for async processing.

```bash
curl -X POST http://localhost:8007/v1/mock/webhook/charge-succeeded \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "payment_id": 456
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Webhook simulated",
  "data": {
    "payment_id": 456,
    "status": "completed",
    "paid_at": "2026-04-16T14:30:22+07:00"
  }
}
```

---

## Usage Scenarios

### Scenario 1: Test Invoice Payment Flow

```bash
# 1. Create invoice via mock endpoint
RESPONSE=$(curl -X POST http://localhost:8007/v1/mock/invoices/create-and-pay \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100000,
    "description": "Test Invoice",
    "currency": "IDR"
  }')

# Extract invoice ID
INVOICE_ID=$(echo $RESPONSE | jq '.data.invoice.id')
PAYMENT_ID=$(echo $RESPONSE | jq '.data.payment.id')

echo "Invoice: $INVOICE_ID"
echo "Payment: $PAYMENT_ID"

# 2. Verify payment was created
curl -X GET http://localhost:8000/api/v1/payments/$PAYMENT_ID \
  -H "Authorization: Bearer TOKEN"
```

### Scenario 2: Test Subscription Renewal

```bash
# 1. Create renewal invoice
curl -X POST http://localhost:8007/v1/mock/invoices/create-and-pay \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 500000,
    "description": "Subscription renewal - Premium Plan",
    "currency": "IDR"
  }'

# 2. Verify subscription was renewed
curl -X GET http://localhost:8000/api/v1/subscriptions/active \
  -H "Authorization: Bearer TOKEN"
```

### Scenario 3: Test Payment Failure Handling

```bash
# 1. Create invoice first
INVOICE=$(curl -X POST http://localhost:8000/api/v1/invoices \
  -H "Authorization: Bearer TOKEN" \
  -d '{"company_id": 1, "amount": 100000, ...}')
INVOICE_ID=$(echo $INVOICE | jq '.data.id')

# 2. Simulate payment failure
curl -X POST http://localhost:8007/v1/mock/payments/create \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"invoice_id\": $INVOICE_ID,
    \"amount\": 100000,
    \"simulate_failure\": true
  }"

# 3. Verify invoice remains unpaid
curl -X GET http://localhost:8000/api/v1/invoices/$INVOICE_ID \
  -H "Authorization: Bearer TOKEN"
```

---

## FAQ

### Q: Are mock payments saved to the database?

**A:** Yes. Mock payments create real `Payment` and `Invoice` records so you can test the full database flow. You can delete them if needed:

```bash
# Delete mock payment
DELETE /api/v1/payments/{payment_id}

# Delete mock invoice
DELETE /api/v1/invoices/{invoice_id}
```

### Q: Can I use these in production?

**A:** No. Mock payment endpoints are automatically disabled in production. Attempts will return:
```json
{
  "success": false,
  "error": {
    "code": "MOCK_DISABLED",
    "message": "Mock payments are disabled."
  }
}
```

### Q: How do I test webhook handling?

**A:** Use the `/webhook/charge-succeeded` endpoint to manually trigger events:

```bash
curl -X POST http://localhost:8007/v1/mock/webhook/charge-succeeded \
  -H "Authorization: Bearer TOKEN" \
  -d '{"payment_id": 456}'
```

### Q: Can I pay an invoice with both mock and real gateways?

**A:** No. Each invoice can only be paid once. After a successful payment (mock or real), the invoice status becomes `paid` and cannot be re-paid.

To test again, create a new mock invoice.

### Q: What happens to unpaid mock invoices?

**A:** They behave exactly like real unpaid invoices:
- Appear in aging reports
- Can be marked as overdue
- Can be manually marked as paid

You can clean them up via API or database:
```bash
# Mark as paid
curl -X PUT http://localhost:8000/api/v1/invoices/{id}/mark-paid

# Delete
curl -X DELETE http://localhost:8000/api/v1/invoices/{id}
```

---

## Environment Variables

By default, mock payments are enabled in development (when `APP_ENV=local`).

To explicitly enable/disable:

```bash
# .env
APP_MOCK_PAYMENTS_ENABLED=true    # Force enable (even if not local)
APP_MOCK_PAYMENTS_ENABLED=false   # Force disable
```

---

## Testing Checklist

- [ ] Create invoice + pay in single call
- [ ] Pay existing invoice
- [ ] Simulate payment failure
- [ ] Verify invoice marked as paid
- [ ] Verify payment record created
- [ ] Test webhook simulation
- [ ] Delete mock payments
- [ ] Verify mock endpoints disabled in production config

---

## Implementation Details

### Service: `MockPaymentGatewayService`

Located in `app/Services/MockPaymentGatewayService.php`

**Methods:**
- `createPayment()` — Simulate successful payment
- `createFailedPayment()` — Simulate payment failure
- `createInvoiceAndPay()` — Create invoice + process payment

### Controller: `MockPaymentController`

Located in `app/Http/Controllers/Api/MockPaymentController.php`

All endpoints check `isMockModeEnabled()` which:
- Allows in development (`app()->isLocal()`)
- Can be forced via config (`config('app.mock_payments_enabled')`)
- Disabled in production

### Routes

```php
Route::prefix('mock')->group(function () {
    Route::post('/payments/create', 'createPayment');
    Route::post('/invoices/create-and-pay', 'createInvoiceAndPay');
    Route::get('/test-cards', 'getTestCards');
    Route::post('/webhook/charge-succeeded', 'simulateChargeSucceeded');
});
```

All routes require authentication (`api.token` middleware) except webhooks.

---

## See Also

- [Payment Controllers](../app/Http/Controllers/Api/PaymentController.php) — Real payment handling
- [Stripe Webhook Handling](../app/Http/Controllers/Api/PaymentWebhookController.php) — Production webhook security
- [Invoice Management](../app/Http/Controllers/Api/InvoiceController.php) — Invoice lifecycle
