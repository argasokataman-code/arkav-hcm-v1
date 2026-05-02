# FAQ API

## Base

- Prefix: /v1/hcm
- Middleware: api.token, tenant.context
- Auth: Bearer token via Authorization header
- Tenant: X-Company-Id header (active company scope)

## Access Control

Semua endpoint FAQ mewajibkan user sebagai HCM admin pada tenant aktif. User non-admin akan menerima 403 Forbidden.

## Endpoints

### GET /v1/hcm/faqs

List FAQ entries untuk tenant aktif.

Response 200:

{
  "success": true,
  "data": [
    {
      "id": 12,
      "uuid": "7ddde983-a0ee-40f4-b5b8-54d7457f3f9a",
      "category": "Payroll",
      "question": "How do I process payroll in the workspace?",
      "answer": "Review payroll inputs and execute the payroll run after validation.",
      "createdBy": 10,
      "updatedBy": 10,
      "updatedAt": "2026-05-02T10:20:00+00:00",
      "createdAt": "2026-05-02T10:20:00+00:00"
    }
  ]
}

Error responses: 401, 403, 422 (tenant context missing)

### POST /v1/hcm/faqs

Create FAQ entry.

Request body:

{
  "category": "Payroll",
  "question": "How do I process payroll in the workspace?",
  "answer": "Review payroll inputs and execute the payroll run after validation."
}

Validation:

- category: required, string, max 100
- question: required, string, max 500
- answer: required, string, max 10000

Response 201:

{
  "success": true,
  "data": {
    "id": 13,
    "uuid": "7f4d37d6-3ea3-418f-9123-c5779ebc4f5a",
    "category": "Payroll",
    "question": "How do I process payroll in the workspace?",
    "answer": "Review payroll inputs and execute the payroll run after validation.",
    "createdBy": 10,
    "updatedBy": 10,
    "updatedAt": "2026-05-02T10:20:00+00:00",
    "createdAt": "2026-05-02T10:20:00+00:00"
  }
}

Error responses: 401, 403, 422

### PUT /v1/hcm/faqs/{id}

Update FAQ entry by numeric id (partial update supported).

Request body (any subset):

{
  "category": "General",
  "question": "Updated question",
  "answer": "Updated answer"
}

Response 200:

{
  "success": true,
  "data": {
    "id": 13,
    "uuid": "7f4d37d6-3ea3-418f-9123-c5779ebc4f5a",
    "category": "General",
    "question": "Updated question",
    "answer": "Updated answer",
    "createdBy": 10,
    "updatedBy": 10,
    "updatedAt": "2026-05-02T10:25:00+00:00",
    "createdAt": "2026-05-02T10:20:00+00:00"
  }
}

Error responses: 401, 403, 404, 422

### DELETE /v1/hcm/faqs/{id}

Delete one FAQ entry.

Response 200:

{
  "success": true
}

Error responses: 401, 403, 404

### POST /v1/hcm/faqs/bulk-delete

Delete multiple FAQ entries in one request.

Request body:

{
  "ids": [13, 14, 15]
}

Response 200:

{
  "success": true,
  "data": {
    "deletedCount": 3
  }
}

Error responses: 401, 403, 422

## Identifier Status

FAQ API saat ini memakai numeric id untuk URL path parameter. UUID ikut dikembalikan di payload response untuk kompatibilitas transisi identifier bila dibutuhkan pada fase berikutnya.
