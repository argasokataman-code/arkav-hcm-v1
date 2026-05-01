# Calendar API

## Ringkasan

API ini menyediakan CRUD untuk event kalender personal user pada tenant aktif. Data event custom ini dipakai halaman Calendar bersama overlay read-only dari holiday dan leave request.

Base path: /v1/hcm

Middleware:
- api.token
- tenant.context

## Kontrak Response

Semua endpoint mengembalikan envelope:
- success: boolean
- data: payload (opsional)
- error: object (saat gagal)

## Endpoint

### GET /calendar/events

List semua event custom milik user login pada company aktif.

Response 200:
- success: true
- data[]:
  - id: integer (numeric legacy)
  - uuid: string
  - title: string
  - location: string|null
  - description: string|null
  - startAt: ISO datetime
  - endAt: ISO datetime|null
  - allDay: boolean
  - createdAt: ISO datetime
  - updatedAt: ISO datetime

### POST /calendar/events

Buat event custom baru.

Body JSON:
- title: required, string, max 255
- location: nullable, string, max 255
- description: nullable, string, max 10000
- startAt: required, date/datetime
- endAt: nullable, date/datetime, after_or_equal startAt
- allDay: nullable, boolean

Response 201:
- success: true
- data: event object

Error:
- 422 validation error
- 401 unauthorized

### PUT /calendar/events/{id}

Update event custom milik user login pada company aktif.

Body JSON (partial update):
- title, location, description, startAt, endAt, allDay

Response 200:
- success: true
- data: event object terbaru

Error:
- 404 jika id tidak ditemukan dalam scope user + company
- 422 validation error
- 401 unauthorized

### DELETE /calendar/events/{id}

Hapus permanen event custom milik user login pada company aktif.

Response 200:
- success: true

Error:
- 404 jika id tidak ditemukan dalam scope user + company
- 401 unauthorized

## Scope dan Isolasi

Semua query dibatasi server-side:
- user_id = user terautentikasi
- company_id = activeCompanyId dari tenant.context

Dengan ini user tidak bisa membaca/mengubah event user lain atau tenant lain.

## Identifier Transisi

Endpoint ini saat ini menerima path id numeric legacy pada route /calendar/events/{id}. Payload juga menyertakan uuid untuk kebutuhan transisi identifier di UI/integrasi berikutnya.
