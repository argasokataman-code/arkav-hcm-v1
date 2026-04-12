# Phase 1 Feature Relation Flowchart

Dokumen ini dibuat agar alur antar fitur mudah dibaca saat planning dan development.
Seluruh flow di bawah mengacu ke konsep arsitektur microservice.

## 1) Development dependency flow

```mermaid
flowchart TD
    A[Identity Service\nregister/login/logout/me] --> B[Auth Guard di Frontend]
    B --> C[Dashboard Landing]
    A --> D[Core HCM Service]
    D --> F[Department Feature\nlist/add/edit]
    D --> G[Designation Feature\nlist/add/edit]
    F --> E[Employee Feature\nlist/detail/create]
    G --> E
    A --> H[Leave Service]
    E --> I[Leave Feature\nsubmit/status/approve]
    H --> I
    E --> C
    I --> C
```

## 2) Service interaction flow (gateway-centric)

```mermaid
flowchart LR
    FG[Frontend Gateway]
    IS[Identity Service]
    HC[Core HCM Service]
    LS[Leave Service]
    FG -->|auth/me, login, logout| IS
    FG -->|employees, departments, designations| HC
    FG -->|leaves, leave-types, attendance| LS
    LS -->|employee lookup reference| HC
```

## 3) API touchpoints per service

### Identity Service
- `POST /v1/identity/auth/register`
- `POST /v1/identity/auth/login`
- `POST /v1/identity/auth/logout`
- `GET /v1/identity/auth/me`

### Core HCM Service
- `GET /v1/hcm/employees`
- `GET /v1/hcm/employees/{id}`
- `POST /v1/hcm/employees`
- `GET /v1/hcm/departments`
- `POST /v1/hcm/departments`
- `PUT /v1/hcm/departments/{id}`
- `GET /v1/hcm/designations`
- `POST /v1/hcm/designations`
- `PUT /v1/hcm/designations/{id}`

### Leave & Attendance Service
- `GET /v1/leave/leaves`
- `POST /v1/leave/leaves`
- `POST /v1/leave/leaves/{id}/approve`
- `GET /v1/leave/attendance`
- `POST /v1/leave/attendance`
- `GET /v1/leave/leave-types`

## 4) Common API conventions

- Base URL convention:
  - Identity: `/v1/identity/*`
  - Core HCM: `/v1/hcm/*`
  - Leave: `/v1/leave/*`
- Error response:
  - `code` (string)
  - `message` (string)
  - `details` (optional object)
  - `traceId` (optional string)
- Health endpoint:
  - `GET /health` per service
