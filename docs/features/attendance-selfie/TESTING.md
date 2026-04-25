# Attendance Selfie Feature - Testing Guide

## Status
- ✅ FE: Camera modal + capture UI implemented
- ✅ API: `POST /v1/hcm/attendance/me/selfie`, `GET /v1/hcm/attendance/me/selfie/status`, dan admin download endpoint aktif
- ✅ DB: Selfie fields + hash storage added
- ✅ Security: Private storage + SHA256 hash integrity
- ✅ Validation hardening: wajib punch-in (`check_in_at`) + MIME whitelist (`jpeg/png/webp`) + max 5MB

---

## Test Approach A: Manual UI Testing (Browser)

### Setup
1. Ensure dev server running:
   ```bash
   cd backend && php artisan serve --host=0.0.0.0 --port=8000
   ```

2. Login as test user:
   - **URL**: http://localhost:8000
   - **Email**: `employee1@arcav.test` 
   - **Password**: `password`
   - **Company**: Arcav Inc (default)

### Test Steps

#### A1: Punch In First
1. Navigate to **Attendance → Absensi Saya**
2. Click **"Punch In"** button (appears in left punch card)
3. Allow GPS + Location access
4. Verify "Status hari ini" shows "Belum absen" → "Sudah absen"

#### A2: Open Selfie Camera Modal
1. In same attendance page, locate **"Ambil Selfie"** button (green)
2. Click button → Should open modal with:
   - ✅ Video stream live
   - ✅ "Ambil Foto" button
   - ✅ Encryption badge ("Foto akan dienkripsi...")
   - ✅ "Batal" button

#### A3: Capture Photo
1. Position face in camera view
2. Click **"Ambil Foto"** button
3. Verify:
   - ✅ Video stream stops
   - ✅ Canvas preview shows captured image
   - ✅ "Ulangi" button appears (light gray)
   - ✅ "Simpan Selfie" button appears (dark)

#### A4: Retake (Optional)
1. Click **"Ulangi"** button
2. Verify camera stream restarts
3. Capture again

#### A5: Submit Selfie
1. Click **"Simpan Selfie"** button
2. Watch for loading state: "Mengirim..." spinner
3. Expect toast notification:
   - ✅ Green toast: "Selfie berhasil disimpan dan dienkripsi"
4. Modal should close auto
5. Attendance data should refresh

#### A6: Verify Storage
1. Open browser **DevTools** → **Network** tab
2. Look for POST request: `/v1/hcm/attendance/me/selfie`
3. Check response:
   ```json
   {
     "success": true,
     "data": {
       "attendance_id": 123,
       "selfie_path": "selfie/1/456_2026-04-15_1713182400.jpg",
       "uploaded_at": "2026-04-15T..."
     }
   }
   ```

---

## Test Approach B: API Testing (curl/PHP)

### Setup
1. Server running (see A1)
2. Get bearer token:
   ```bash
  curl -X POST http://localhost:8000/v1/auth/login \
     -H 'Content-Type: application/json' \
     -d '{"email":"employee1@arcav.test","password":"password"}'
   ```
   Copy the `data.token` value

### Test Steps

#### B1: Check Attendance Status
```bash
curl -X GET http://localhost:8000/v1/hcm/attendance/me/today \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'X-Company-Id: 1'
```

Expected: `200 OK` with attendance record for today

#### B2: Upload Selfie (base64 image)
```bash
curl -X POST http://localhost:8000/v1/hcm/attendance/me/selfie \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'X-Company-Id: 1' \
  -H 'Content-Type: application/json' \
  -d '{
    "selfie_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/...",
    "timestamp": 1713182400
  }'
```

Expected response `200`:
```json
{
  "success": true,
  "data": {
    "attendance_id": 123,
    "selfie_path": "selfie/1/456_2026-04-15_1713182400.jpg",
    "uploaded_at": "2026-04-15T10:30:00.000000Z"
  }
}
```

#### B3: Check Selfie Status
```bash
curl -X GET http://localhost:8000/v1/hcm/attendance/me/selfie/status \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'X-Company-Id: 1'
```

Expected:
```json
{
  "success": true,
  "data": {
    "has_selfie": true,
    "selfie": {
      "path": "selfie/1/456_2026-04-15_1713182400.jpg",
      "uploaded_at": "2026-04-15T10:30:00.000000Z",
      "is_encrypted": true
    }
  }
}
```

#### B4: Verify Hash in Database
```sql
SELECT 
  id,
  user_id,
  work_date,
  selfie_path,
  selfie_encrypted_hash,
  LENGTH(selfie_encrypted_hash) as hash_length
FROM attendance_records
WHERE work_date = CURDATE()
AND user_id = YOUR_USER_ID;
```

Expected:
- `selfie_path`: NOT NULL (e.g., `selfie/1/456_2026-04-15_...jpg`)
- `selfie_encrypted_hash`: SHA256 (64 chars hex)
- `hash_length`: 64

### Run Automated Test (Optional)
```bash
php test-selfie-api.php
```
Responds with prompts - enter your token when asked.

---

## Validation Checklist

### Frontend (UI)
- [ ] Camera modal opens on "Ambil Selfie" click
- [ ] Video stream displays live (facingMode: user)
- [ ] "Ambil Foto" captures to canvas
- [ ] Preview shows captured image
- [ ] "Ulangi" restarts camera
- [ ] "Simpan Selfie" sends POST request
- [ ] Loading spinner shows during upload
- [ ] Success toast appears after upload
- [ ] Modal closes on submission
- [ ] Encryption badge visible ("Foto akan dienkripsi")

### API
- [ ] `POST /v1/hcm/attendance/me/selfie` returns 200 on valid image
- [ ] Response includes `selfie_path` + `uploaded_at`
- [ ] `GET /v1/hcm/attendance/me/selfie/status` returns `data.has_selfie: true`
- [ ] Invalid base64 returns 422 `VALIDATION_ERROR`
- [ ] Non-image payload returns 422 `VALIDATION_ERROR`
- [ ] Oversize payload (>5MB decoded) returns 422 `VALIDATION_ERROR`
- [ ] No attendance/check-in returns 422 `ATTENDANCE_NOT_STARTED`
- [ ] Unauthenticated request returns 401

### Database
- [ ] `attendance_records.selfie_path` populated (not NULL)
- [ ] `attendance_records.selfie_encrypted_hash` populated (64 chars)
- [ ] Hash is valid SHA256 hex string

### Security
- [ ] Image stored in private filesystem (not publicly accessible)
- [ ] Hash matches `hash('sha256', $imageBinary)` for integrity
- [ ] Same image uploaded twice = different paths but verifiable hash
- [ ] Tenant isolated (company_id scopes both storage + DB query)

---

## Error Cases to Test

### 1. No Attendance Record
- **Setup**: Don't punch in
- **Action**: Try to upload selfie
- **Expected**: `422 Unprocessable Entity`
- **Code**: `ATTENDANCE_NOT_STARTED`
- **Message**: "Harap lakukan punch in terlebih dahulu sebelum mengambil selfie."

### 2. Invalid Base64
- **Setup**: Submit `selfie_base64: "not-base64"`
- **Expected**: `422 Unprocessable Entity`
- **Code**: `VALIDATION_ERROR`
- **Message**: "Data selfie tidak valid..."

### 2b. Non-image Payload
- **Setup**: Submit `selfie_base64` base64 plain text atau binary acak
- **Expected**: `422 Unprocessable Entity`
- **Code**: `VALIDATION_ERROR`

### 2c. Oversize Payload
- **Setup**: Submit payload image/base64 > 5MB (decoded)
- **Expected**: `422 Unprocessable Entity`
- **Code**: `VALIDATION_ERROR`

### 3. Camera Permission Denied
- **Setup**: Deny camera access in browser
- **Expected**: Alert popup "Akses kamera ditolak. Periksa permissions browser Anda."

### 4. Unauthenticated Request
- **Setup**: No token or invalid token
- **Expected**: `401 Unauthorized`

### 5. Cross-tenant Admin Download Attempt
- **Setup**: Login sebagai HCM admin tenant A lalu coba unduh selfie attendance record tenant B via `GET /v1/hcm/attendance/admin/records/{id}/selfie/download`
- **Expected**: `404 Not Found` atau `403 TENANT_FORBIDDEN` tergantung konteks tenant aktif, tanpa membuka akses file tenant lain

### 6. Empty Selfie Data
- **Setup**: Click "Simpan Selfie" without capturing
- **Expected**: Alert "Tidak ada foto untuk disimpan"

---

## Test Evidence Template

When tests complete, document:

```
[Test Date]: 2026-04-15
[Tester]: YOUR_NAME
[Environment]: http://localhost:8000

### A: UI Testing
- [ ] A1 Punch In - PASS/FAIL
- [ ] A2 Open Modal - PASS/FAIL
- [ ] A3 Capture Photo - PASS/FAIL
- [ ] A4 Retake - PASS/FAIL (optional)
- [ ] A5 Submit Selfie - PASS/FAIL
- [ ] A6 Verify Storage - PASS/FAIL

### B: API Testing
- [ ] B1 Attendance Status - PASS/FAIL
- [ ] B2 Upload Selfie - PASS/FAIL
- [ ] B3 Check Status - PASS/FAIL
- [ ] B4 DB Hash Verification - PASS/FAIL

### Errors Tested
- [ ] No Attendance Record Error
- [ ] Invalid Base64 Error
- [ ] Camera Permission Error
- [ ] Unauthenticated Error
- [ ] Empty Selfie Error

### Database Query Results
```sql
SELECT ... FROM attendance_records WHERE work_date = '2026-04-15'
Result: [paste here]
```

### Notes
[Any issues found, screenshots, etc]
```

---

## Next After Testing
1. ✅ **Encrypt NIK field** (separate migration)
2. ✅ **PDP/Privacy Policy** (data retention, consent)
3. ✅ **Resume Export Reconciliation tests** (PC-01, TC-01)

---

## Quick Issue Fixes

**Issue**: Camera won't start
- Check browser console for GeolocationError
- Ensure HTTPS or localhost (browser requires secure context)

**Issue**: 404 on selfie endpoint
- Verify routes added: `php artisan route:list | grep selfie`
- Check controller method exists: `meSelfie` + `meSelfieStatus`

**Issue**: Base64 not sending
- Verify Blade template JS captures canvas data correctly
- Check DevTools Network → Request payload for base64 string

**Issue**: Hash not 64 chars
- Verify SHA256 hash is correct length: `bin2hex(hash('sha256', $data))` should be 64
- Check DB column is VARCHAR(255) or TEXT

