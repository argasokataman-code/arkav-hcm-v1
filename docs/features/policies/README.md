# Policies

## Scope

- Company policies CRUD di modul HCM
- Relasi opsional ke department
- Catatan attachment/storage mengikuti service media

## API utama (`/v1/hcm`)

- `GET /policies`
- `POST /policies`
- `PUT /policies/{id}`
- `DELETE /policies/{id}`

## Data model ringkas

- `policies`:
  - `department_id` (nullable)
  - `name`
  - `description`
  - `effective_date`

## Catatan implementasi

- Controller policy berada di `HcmEmployeeController` (belum dipisah ke controller khusus).
- Endpoint update saat ini menerima variasi `PUT` dan fallback `POST` pada path yang sama untuk kompatibilitas template/form.
