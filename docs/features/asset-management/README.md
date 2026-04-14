# Asset Management

## Overview

Asset Management adalah modul HCM company-scoped untuk mengelola asset master, kategori asset, assignment ke employee, histori log, attachment, dan reporting issue ke ticketing.

## Documents

- [IMPLEMENTATION.md](IMPLEMENTATION.md)
- [E2E-TESTING.md](E2E-TESTING.md)

## Key Points

- Asset tidak menyimpan `employee_id` langsung.
- Assignment history disimpan di `asset_assignments`.
- Semua tabel memakai `company_id`.
- Feature gate memakai `asset_management` dari `package_features`.
- Issue asset direport ke `tickets`.
