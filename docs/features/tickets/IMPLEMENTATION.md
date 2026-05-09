# Tickets — Implementation

Status: Implemented (Ticket CRUD + Category + Comment + Attachment)
Updated: 2026-05-08

## Overview

Modul tickets adalah sistem internal helpdesk/issue tracking untuk karyawan dan admin. Karyawan dapat membuat tiket, menambahkan komentar dan lampiran. Admin dapat mengkategorikan tiket, menugaskan penanganan, dan mengupdate status.

## Controller

- `backend/app/Http/Controllers/Api/HcmTicketController.php`

## Web Surfaces

- `backend/resources/views/tickets.blade.php` — daftar tiket
- `backend/resources/views/tickets-grid.blade.php` — tampilan grid tiket
- `backend/resources/views/ticket-details.blade.php` — detail tiket
- `backend/resources/views/ticket-master.blade.php` — master kategori tiket (admin)

## Route File

`backend/routes/api/ticket.php` — prefix `v1/hcm`, middleware: `api.token`, `tenant.context`

## Main API Endpoints

### Tickets
- `GET /v1/hcm/tickets` — daftar tiket (admin: semua; employee: milik sendiri)
- `POST /v1/hcm/tickets` — buat tiket baru
- `GET /v1/hcm/tickets/{id}` — detail tiket
- `PUT /v1/hcm/tickets/{id}` — update tiket (status, prioritas, assignee)
- `DELETE /v1/hcm/tickets/{id}` — hapus tiket
- `GET /v1/hcm/tickets/assignable-users` — daftar user yang bisa di-assign tiket
- `GET /v1/hcm/tickets/category-options` — opsi kategori untuk form

### Ticket Categories (admin only)
- `GET /v1/hcm/tickets/categories` — daftar kategori
- `POST /v1/hcm/tickets/categories` — buat kategori
- `PUT /v1/hcm/tickets/categories/{id}` — update kategori
- `DELETE /v1/hcm/tickets/categories/{id}` — hapus kategori

### Comments & Attachments
- `POST /v1/hcm/tickets/{id}/comments` — tambah komentar ke tiket
- `POST /v1/hcm/tickets/{id}/attachments` — upload lampiran
- `GET /v1/hcm/tickets/{id}/attachments/{attachmentId}/preview` — preview lampiran
- `GET /v1/hcm/tickets/{id}/attachments/{attachmentId}/download` — unduh lampiran

## Data Models

- `Ticket` — tiket (judul, deskripsi, status, prioritas, kategori, assignee)
- `TicketCategory` — master kategori tiket per tenant
- `TicketComment` — komentar pada tiket
- `TicketAttachment` — lampiran file pada tiket
- `TicketAssignmentHistory` — riwayat perubahan assignee

## Tenant Scope

Semua tiket dikunci ke `company_id` aktif. Assignment hanya boleh ke user dalam tenant yang sama.
