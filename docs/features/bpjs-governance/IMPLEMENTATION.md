# BPJS Governance — Implementation

Status: Implemented (Policy CRUD + Employee Membership + Reports + Rate Baselines)
Updated: 2026-05-08

## Overview

Modul BPJS Governance mengelola kebijakan iuran BPJS Ketenagakerjaan dan BPJS Kesehatan per tenant. Mencakup referensi program BPJS, konfigurasi kebijakan, history kebijakan, manajemen kepesertaan karyawan, laporan kepatuhan, dan baseline tarif iuran.

## Controller

- `backend/app/Http/Controllers/Api/HcmBpjsGovernanceController.php`

## Web Surfaces

- Halaman BPJS Governance terintegrasi dalam halaman settings/governance HCM.

## Route File

`backend/routes/api/bpjs-governance.php` — prefix `v1/hcm/bpjs-governance`, middleware: `api.token`, `tenant.context`

## Main API Endpoints

### Reference & Rate Baselines
- `GET /v1/hcm/bpjs-governance/reference` — referensi program BPJS (daftar program, tipe iuran)
- `GET /v1/hcm/bpjs-governance/rate-baselines` — tarif baseline per program BPJS
- `PUT /v1/hcm/bpjs-governance/rate-baselines/{programCode}/{contributionParty}` — update tarif baseline (admin global)

### Policies
- `GET /v1/hcm/bpjs-governance/policies` — kebijakan BPJS tenant aktif
- `GET /v1/hcm/bpjs-governance/policies/history` — riwayat perubahan kebijakan
- `POST /v1/hcm/bpjs-governance/policies` — buat kebijakan baru
- `PUT /v1/hcm/bpjs-governance/policies/{policyRef}` — update kebijakan (policyRef = string/UUID reference)
- `DELETE /v1/hcm/bpjs-governance/policies/{policyRef}` — hapus kebijakan

### Employee Membership
- `GET /v1/hcm/bpjs-governance/employee-membership` — status kepesertaan BPJS per karyawan
- `PUT /v1/hcm/bpjs-governance/employee-membership/{userId}` — update status kepesertaan karyawan

### Reports
- `GET /v1/hcm/bpjs-governance/reports` — laporan iuran BPJS tenant
- `GET /v1/hcm/bpjs-governance/reports/export` — export laporan (CSV/Excel)

## Data Models

- `HcmBpjsGovernancePolicy` — kebijakan BPJS aktif per tenant
- `HcmBpjsGovernancePolicyHistory` — riwayat perubahan kebijakan
- `HcmBpjsGovernanceRateBaseline` — tarif baseline per program BPJS

## Tenant Scope

Semua kebijakan dan kepesertaan dikunci ke `company_id` aktif.

## Integrasi

- **Payroll**: iuran BPJS (JKK, JKM, JHT, JP, BPJS Kesehatan) yang dihitung dari kebijakan dan kepesertaan ini dimasukkan sebagai komponen deduction dalam kalkulasi slip gaji payroll run.
