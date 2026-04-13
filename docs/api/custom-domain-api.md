# Custom Domains API Documentation

## Overview

The Custom Domains API enables SaaS administrators to manage custom domain configurations for their applications. This includes domain registration, verification, and lifecycle management with comprehensive audit logging.

**Base URI:** `/v1/saas`  
**Authentication:** Bearer Token (API Token)  
**Response Format:** JSON

---

## Data Model

### CustomDomain

```json
{
  "id": 1,
  "companyId": 1,
  "company": {
    "id": 1,
    "code": "COMP001",
    "name": "Company Name"
  },
  "domain": "app.example.com",
  "status": "verified",
  "verificationToken": "verify_abc123def456",
  "verifiedAt": "2026-04-13T10:30:00Z",
  "verificationFailedAt": null,
  "verificationMethod": "dns",
  "verificationRecord": "v=arcav verify_abc123def456",
  "verificationAttempts": 1,
  "lastVerificationAttemptAt": "2026-04-13T10:30:00Z",
  "activeFrom": "2026-04-13",
  "activeUntil": "2027-04-13",
  "notes": "Production domain",
  "verificationLogs": [
    {
      "id": 1,
      "status": "verified",
      "verificationMethod": "dns",
      "details": "DNS record found",
      "attemptedAt": "2026-04-13T10:30:00Z"
    }
  ],
  "isActive": true,
  "isPending": false,
  "hasFailed": false,
  "createdAt": "2026-04-13T10:25:00Z",
  "updatedAt": "2026-04-13T10:30:00Z"
}
```

### Domain Status

- **pending** - Domain created but not yet verified
- **verified** - Domain ownership verified and active
- **failed** - Verification failed after 5 attempts
- **inactive** - Domain previously verified but currently inactive

### Verification Methods

- **dns** - DNS TXT record verification
- **file** - File-based verification (HTTP endpoint)

---

## Endpoints

### 1. List Domains

Retrieve a paginated list of custom domains with optional filtering.

**Endpoint:** `GET /domains`

**Authentication:** Required (Admin)

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| status | string | Filter by domain status (pending, verified, failed, inactive) |
| company_id | integer | Filter by company ID |
| domain | string | Search domains by name (partial match) |
| page | integer | Pagination page number (default: 1) |
| per_page | integer | Items per page (default: 15, max: 100) |

**Example Request:**

```http
GET /v1/saas/domains?status=verified&page=1
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "domain": "app.example.com",
      "status": "verified",
      "companyId": 1,
      "isActive": true,
      "isPending": false,
      "hasFailed": false,
      "verificationAttempts": 1,
      "verifiedAt": "2026-04-13T10:30:00Z"
    }
  ],
  "pagination": {
    "total": 42,
    "per_page": 15,
    "current_page": 1,
    "last_page": 3
  }
}
```

---

### 2. Get Domain Details

Retrieve detailed information about a specific domain including verification logs.

**Endpoint:** `GET /domains/{id}`

**Authentication:** Required (Admin)

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Domain ID |

**Example Request:**

```http
GET /v1/saas/domains/1
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "companyId": 1,
    "company": {
      "id": 1,
      "code": "COMP001",
      "name": "Acme Corp"
    },
    "domain": "app.example.com",
    "status": "verified",
    "verificationToken": "verify_abc123def456",
    "verifiedAt": "2026-04-13T10:30:00Z",
    "verificationFailedAt": null,
    "verificationMethod": "dns",
    "verificationRecord": "v=arcav verify_abc123def456",
    "verificationAttempts": 1,
    "lastVerificationAttemptAt": "2026-04-13T10:30:00Z",
    "activeFrom": "2026-04-13",
    "activeUntil": "2027-04-13",
    "notes": "Production domain",
    "verificationLogs": [
      {
        "id": 1,
        "status": "verified",
        "verificationMethod": "dns",
        "details": "DNS record found",
        "attemptedAt": "2026-04-13T10:30:00Z"
      }
    ],
    "isActive": true,
    "isPending": false,
    "hasFailed": false,
    "createdAt": "2026-04-13T10:25:00Z",
    "updatedAt": "2026-04-13T10:30:00Z"
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "error": "Domain not found"
}
```

---

### 3. Create Domain

Register a new custom domain.

**Endpoint:** `POST /domains`

**Authentication:** Required (Admin Only)

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| company_id | integer | Yes | Company ID |
| domain | string | Yes | Domain name (must be unique) |
| verification_method | string | Yes | Verification method (dns or file) |
| active_from | date | No | Activation date (YYYY-MM-DD) |
| active_until | date | No | Expiration date (YYYY-MM-DD) |
| notes | string | No | Additional notes |

**Example Request:**

```http
POST /v1/saas/domains
Authorization: Bearer YOUR_ACCESS_TOKEN
Content-Type: application/json

{
  "company_id": 1,
  "domain": "newapp.example.com",
  "verification_method": "dns",
  "active_from": "2026-04-13",
  "active_until": "2027-04-13",
  "notes": "Production domain"
}
```

**Success Response (201):**

```json
{
  "success": true,
  "data": {
    "id": 2,
    "companyId": 1,
    "company": {
      "id": 1,
      "code": "COMP001",
      "name": "Acme Corp"
    },
    "domain": "newapp.example.com",
    "status": "pending",
    "verificationToken": "verify_xyz789abc123",
    "verificationMethod": "dns",
    "verificationRecord": "v=arcav verify_xyz789abc123",
    "activeFrom": "2026-04-13",
    "activeUntil": "2027-04-13",
    "notes": "Production domain",
    "isPending": true,
    "isActive": false,
    "hasFailed": false,
    "createdAt": "2026-04-13T11:00:00Z",
    "updatedAt": "2026-04-13T11:00:00Z"
  }
}
```

**Error Response (422):**

```json
{
  "success": false,
  "errors": {
    "domain": ["The domain field must be unique."]
  }
}
```

**Error Response (403):**

```json
{
  "success": false,
  "error": {
    "code": "ADMIN_REQUIRED",
    "message": "Admin access required."
  }
}
```

---

### 4. Update Domain

Modify domain configuration and status.

**Endpoint:** `PUT /domains/{id}`

**Authentication:** Required (Admin Only)

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Domain ID |

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| status | string | No | Domain status (pending, verified, failed, inactive) |
| active_from | date | No | Activation date |
| active_until | date | No | Expiration date |
| notes | string | No | Additional notes |

**Example Request:**

```http
PUT /v1/saas/domains/1
Authorization: Bearer YOUR_ACCESS_TOKEN
Content-Type: application/json

{
  "status": "verified",
  "active_from": "2026-04-13",
  "active_until": "2027-04-13",
  "notes": "Updated: Production verified domain"
}
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "domain": "app.example.com",
    "status": "verified",
    "activeFrom": "2026-04-13",
    "activeUntil": "2027-04-13",
    "notes": "Updated: Production verified domain",
    "updatedAt": "2026-04-13T11:15:00Z"
  }
}
```

---

### 5. Delete Domain

Soft-delete a custom domain (marks as deleted but keeps audit trail).

**Endpoint:** `DELETE /domains/{id}`

**Authentication:** Required (Admin Only)

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Domain ID |

**Example Request:**

```http
DELETE /v1/saas/domains/1
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Domain deleted successfully"
}
```

---

### 6. Verify Domain

Trigger domain ownership verification process.

**Endpoint:** `POST /domains/{id}/verify`

**Authentication:** Required (Admin Only)

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Domain ID |

**Verification Logic:**

1. For DNS verification: checks for DNS TXT record with verification token
2. For file verification: validates HTTP endpoint returns verification token
3. Logs single attempt to `domain_verification_logs` table
4. Updates domain status based on verification result:
   - **Success** → status = "verified", sets verified_at timestamp
   - **Failure** → increments attempts, persists status as "pending" or "failed" (after 5 attempts)
5. Returns updated domain with all audit logs

**Example Request:**

```http
POST /v1/saas/domains/1/verify
Authorization: Bearer YOUR_ACCESS_TOKEN
```

**Success Response (200 - Verified):**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "domain": "app.example.com",
    "status": "verified",
    "verificationAttempts": 1,
    "verifiedAt": "2026-04-13T11:30:00Z",
    "lastVerificationAttemptAt": "2026-04-13T11:30:00Z",
    "verificationResponse": "Domain ownership verified",
    "verificationLogs": [
      {
        "id": 1,
        "status": "verified",
        "verificationMethod": "dns",
        "details": "DNS record found",
        "attemptedAt": "2026-04-13T11:30:00Z"
      }
    ],
    "isActive": true
  },
  "message": "Domain verified successfully"
}
```

**Response (200 - Failed Verification):**

```json
{
  "success": false,
  "data": {
    "id": 1,
    "domain": "app.example.com",
    "status": "pending",
    "verificationAttempts": 2,
    "lastVerificationAttemptAt": "2026-04-13T11:40:00Z",
    "verificationResponse": "Verification failed: DNS record not found",
    "verificationLogs": [
      {
        "id": 2,
        "status": "failed",
        "verificationMethod": "dns",
        "details": "DNS record not found",
        "attemptedAt": "2026-04-13T11:40:00Z"
      }
    ],
    "isPending": true
  },
  "message": "Domain verification failed"
}
```

---

## Common Patterns

### Verification Token Format

Verification tokens are auto-generated on domain creation using:

```
verify_{random_hex_16_chars}
```

Example: `verify_a1b2c3d4e5f6g7h8`

### DNS Verification Record

For DNS verification method, use this TXT record:

```
v=arcav {verification_token}
```

Example: `v=arcav verify_a1b2c3d4e5f6g7h8`

### Domain Lifecycle

```
pending → (verify) → verified/(failed after 5 attempts)
         ↓
       inactive (if expired or manually set)
```

---

## Error Handling

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Resource created |
| 400 | Bad request |
| 403 | Forbidden (admin required) |
| 404 | Not found |
| 422 | Validation error |
| 500 | Server error |

### Error Response Structure

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Error description"
  }
}
```

---

## Audit Logging

All verification attempts are logged in the `domain_verification_logs` table with:

- Domain ID reference
- Verification method used
- Verification status (verified/failed)
- Detailed response message
- Timestamp of attempt

This provides a complete audit trail for compliance and debugging purposes.

---

## Rate Limiting

- No specific rate limits on domain operations
- Verification attempts can be made as frequently as needed
- Excessive failures (5+) automatically mark domain as "failed" to prevent abuse

---

## Example Workflows

### Setting Up a New Domain

```bash
# 1. Create domain
POST /v1/saas/domains
{
  "company_id": 1,
  "domain": "api.example.com",
  "verification_method": "dns"
}
# Response includes verification_token

# 2. Client adds DNS record:
# TXT api.example.com: v=arcav verify_token_value

# 3. Trigger verification
POST /v1/saas/domains/{id}/verify

# 4. Check domain status
GET /v1/saas/domains/{id}
```

### Monitoring Domain Status

```bash
# View all pending domains
GET /v1/saas/domains?status=pending

# View verification history for domain
GET /v1/saas/domains/{id}
# Check verificationLogs array

# Retry failed verification
POST /v1/saas/domains/{id}/verify
```

---

## Testing

Domain verification endpoints include a 70% success rate simulation for testing purposes. In production, actual DNS/file lookups are performed.

---

**API Version:** 1.0  
**Last Updated:** 2026-04-13  
**Status:** Production Ready
