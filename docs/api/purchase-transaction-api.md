# Purchase Transaction API

Purchase transactions track billing and payment records for subscriptions and add-on purchases.

## Base Path
```
/v1/saas
```

## Authentication
All endpoints require Bearer token authentication via `Authorization` header. Mutation endpoints (POST, PUT) require admin access.

---

## Endpoints

### GET /transactions
List transactions with filtering, sorting, and pagination.

**Query Parameters:**
- `status` (string, optional) - Filter by transaction status: `draft`, `issued`, `sent`, `paid`, `overdue`, `cancelled`
- `company_id` (integer, optional) - Filter by company ID
- `transaction_type` (string, optional) - Filter by type: `subscription`, `addon`, `refund`, `credit`, `manual`
- `from_date` (date, optional) - Filter transactions created on or after this date
- `to_date` (date, optional) - Filter transactions created on or before this date

**Response:** 
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "transactionCode": "TXN-2026-123456",
      "companyId": 10,
      "company": {
        "id": 10,
        "code": "ACME",
        "name": "ACME Corp"
      },
      "subscriptionId": 5,
      "subscription": {
        "id": 5,
        "planCode": "pro",
        "status": "active"
      },
      "transactionType": "subscription",
      "description": "Monthly billing",
      "amount": 100000.0,
      "taxAmount": 10000.0,
      "discountAmount": 5000.0,
      "totalAmount": 105000.0,
      "billingPeriodStart": "2026-04-01",
      "billingPeriodEnd": "2026-04-30",
      "dueDate": "2026-05-10T00:00:00Z",
      "paidAt": null,
      "paymentMethod": null,
      "paymentReference": null,
      "status": "issued",
      "isPaid": false,
      "isOverdue": false,
      "notes": null,
      "createdAt": "2026-04-13T02:00:00Z",
      "updatedAt": "2026-04-13T02:00:00Z"
    }
  ],
  "pagination": {
    "total": 25,
    "per_page": 15,
    "current_page": 1,
    "last_page": 2
  }
}
```

**Examples:**

List all paid transactions:
```bash
curl -X GET "http://api.example.com/v1/saas/transactions?status=paid" \
  -H "Authorization: Bearer <token>"
```

List transactions for specific company:
```bash
curl -X GET "http://api.example.com/v1/saas/transactions?company_id=10" \
  -H "Authorization: Bearer <token>"
```

List addon purchases by date range:
```bash
curl -X GET "http://api.example.com/v1/saas/transactions?transaction_type=addon&from_date=2026-04-01&to_date=2026-04-30" \
  -H "Authorization: Bearer <token>"
```

---

### GET /transactions/{transaction}
Get details of a specific transaction.

**Path Parameters:**
- `transaction` (integer, required) - Transaction ID

**Response:** Same structure as the item in list response.

**Example:**
```bash
curl -X GET "http://api.example.com/v1/saas/transactions/1" \
  -H "Authorization: Bearer <token>"
```

---

### POST /transactions
Create a new transaction (admin only).

**Request Body:**
```json
{
  "company_id": 10,
  "subscription_id": 5,
  "transaction_type": "subscription",
  "description": "Monthly subscription charge",
  "amount": 100000,
  "tax_amount": 10000,
  "discount_amount": 5000,
  "status": "issued"
}
```

**Fields:**
- `company_id` (integer, required) - Company ID
- `subscription_id` (integer, optional) - Subscription ID
- `transaction_type` (string, required) - Type: `subscription`, `addon`, `refund`, `credit`, `manual`
- `description` (string, optional) - Transaction description
- `amount` (number, required) - Transaction amount (min: 0)
- `tax_amount` (number, optional, default: 0) - Tax amount
- `discount_amount` (number, optional, default: 0) - Discount amount
- `status` (string, required) - Status: `draft`, `issued`, `sent`, `paid`, `overdue`, `cancelled`

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "transactionCode": "TXN-2026-123456",
    "companyId": 10,
    "company": {...},
    "subscriptionId": 5,
    "subscription": {...},
    "transactionType": "subscription",
    "description": "Monthly subscription charge",
    "amount": 100000.0,
    "taxAmount": 10000.0,
    "discountAmount": 5000.0,
    "totalAmount": 105000.0,
    "status": "issued",
    "isPaid": false,
    "isOverdue": false,
    "createdAt": "2026-04-13T02:00:00Z",
    "updatedAt": "2026-04-13T02:00:00Z"
  }
}
```

**Note:** `totalAmount` is automatically calculated as: `amount + taxAmount - discountAmount`

**Example:**
```bash
curl -X POST "http://api.example.com/v1/saas/transactions" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": 10,
    "subscription_id": 5,
    "transaction_type": "subscription",
    "description": "Monthly billing",
    "amount": 100000,
    "tax_amount": 10000,
    "discount_amount": 5000,
    "status": "issued"
  }'
```

---

### PUT /transactions/{transaction}
Update a transaction (admin only).

**Path Parameters:**
- `transaction` (integer, required) - Transaction ID

**Request Body:**
```json
{
  "status": "paid",
  "paid_at": "2026-04-13T10:00:00Z",
  "payment_method": "bank_transfer",
  "payment_reference": "TRX-2026-123",
  "notes": "Payment received"
}
```

**Fields (all optional):**
- `status` (string) - New status: `draft`, `issued`, `sent`, `paid`, `overdue`, `cancelled`
- `paid_at` (datetime, ISO 8601) - Payment date/time
- `payment_method` (string) - Payment method: `bank_transfer`, `credit_card`, `e_wallet`, `cash`
- `payment_reference` (string) - Payment reference number or ID
- `notes` (string) - Additional notes

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "transactionCode": "TXN-2026-123456",
    "status": "paid",
    "paid_at": "2026-04-13T10:00:00Z",
    "paymentMethod": "bank_transfer",
    "paymentReference": "TRX-2026-123",
    "isPaid": true,
    "isOverdue": false,
    ...
  }
}
```

**Example - Mark transaction as paid:**
```bash
curl -X PUT "http://api.example.com/v1/saas/transactions/1" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "paid",
    "paid_at": "2026-04-13T10:00:00Z",
    "payment_method": "bank_transfer",
    "payment_reference": "TRX-2026-123"
  }'
```

---

## Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK - Request successful |
| 201 | Created - Transaction created successfully |
| 400 | Bad Request - Invalid request body or parameters |
| 403 | Forbidden - User does not have admin access |
| 404 | Not Found - Transaction not found |
| 422 | Unprocessable Entity - Validation failed |
| 500 | Server Error - Internal server error |

---

## Transaction Type Reference

| Type | Description |
|------|-------------|
| `subscription` | Regular subscription billing charge |
| `addon` | Add-on or upsell purchase |
| `refund` | Refund or credit reversal |
| `credit` | Account credit or promotional credit |
| `manual` | Manually created transaction |

---

## Transaction Status Lifecycle

| Status | Description |
|--------|-------------|
| `draft` | Transaction saved but not yet finalized |
| `issued` | Transaction issued and ready for payment |
| `sent` | Transaction sent to customer |
| `paid` | Transaction paid in full |
| `overdue` | Transaction past due date without payment |
| `cancelled` | Transaction cancelled and voided |

---

## Fields Reference

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique transaction ID |
| `transactionCode` | string | Unique transaction code in format `TXN-YYYY-XXXXXX` |
| `companyId` | integer | Associated company ID |
| `subscriptionId` | integer (nullable) | Associated subscription ID |
| `transactionType` | string | Type of transaction |
| `description` | string (nullable) | Transaction description |
| `amount` | float | Base transaction amount |
| `taxAmount` | float | Tax amount (minimum 0) |
| `discountAmount` | float | Discount amount (minimum 0) |
| `totalAmount` | float | Total = amount + tax - discount |
| `billingPeriodStart` | date (nullable) | Billing period start date |
| `billingPeriodEnd` | date (nullable) | Billing period end date |
| `dueDate` | datetime (nullable) | Payment due date |
| `paidAt` | datetime (nullable) | Payment received date/time |
| `paymentMethod` | string (nullable) | Payment method used |
| `paymentReference` | string (nullable) | Payment reference number |
| `status` | string | Transaction status |
| `isPaid` | boolean (computed) | True if status is 'paid' and payment received |
| `isOverdue` | boolean (computed) | True if status is 'overdue' and due date has passed |
| `notes` | string (nullable) | Additional transaction notes |
| `createdAt` | datetime | Creation timestamp (ISO 8601) |
| `updatedAt` | datetime | Last update timestamp (ISO 8601) |

---

## Error Responses

**Admin Access Required (403):**
```json
{
  "success": false,
  "error": {
    "code": "ADMIN_REQUIRED",
    "message": "Admin access required."
  }
}
```

**Validation Error (422):**
```json
{
  "message": "The company id field is required. (and 2 more errors)",
  "errors": {
    "company_id": ["The company id field is required."],
    "transaction_type": ["The transaction type field is required."],
    "status": ["The status field is required."]
  }
}
```

---

## Filtering & Pagination

All list endpoints support:
- **Query parameters** for filtering (see endpoint documentation)
- **Pagination** with 15 items per page
- **Sorting** by creation date (newest first)

Pagination response includes:
- `total` - Total number of matching records
- `per_page` - Items per page (default: 15)
- `current_page` - Current page number
- `last_page` - Last available page number
