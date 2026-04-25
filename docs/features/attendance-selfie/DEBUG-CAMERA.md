# Attendance Selfie - Camera Debugging Guide

## Overview

This guide helps debug camera stream initialization issues in the selfie capture modal.

## Quick Test Checklist

```
☐ Server running on port 8000
☐ Open browser DevTools (F12 or Cmd+Opt+I)
☐ Go to Console tab
☐ Navigated to attendance page
☐ Clicked "Ambil Selfie" button
☐ Modal opened
☐ Check console for log messages
```

## Runtime Note

Selfie camera lifecycle sekarang ditangani oleh module frontend `attendance-data.js` (fungsi `initSelfieCapture`), bukan inline script blade. Debug utama berfokus pada state UI, permission browser, dan network request API.

## Debugging Scenarios

### Scenario 1: Modal Opens but Camera Tidak Start

**Problem**: Modal tampil tapi video tidak start

**Causes**:
- Page not fully loaded
- JavaScript module belum termuat/tereksekusi
- Blade template missing data attributes

**Solution**:
```javascript
// In browser console, manually check:
document.getElementById('arcav_attendance_selfie_modal')  // Should not be null
document.querySelector('[data-selfie-camera-video]')      // Should not be null
document.querySelector('[data-attendance-me-selfie-btn]')  // Should not be null
```

**Action**:
- Refresh page (Cmd+R)
- Clear cache (Cmd+Shift+R)
- Check HTML in DevTools inspector

---

### Scenario 2: Tombol Selfie Tidak Reaktif

**Problem**: Element exists but click event not firing

**Causes**:
- Button has wrong data attribute
- Event listener not attached
- Bootstrap not loaded

**Solution**:
```javascript
// In browser console:
document.querySelector('[data-attendance-me-selfie-btn]').click()  // Manually trigger
```

**Action**:
- Check button HTML in DevTools: Right-click button → Inspect
- Look for: `data-attendance-me-selfie-btn` attribute
- If missing, verify blade template has the button defined

---

### Scenario 3: Browser Tolak Kamera

**Problem**: `startCamera()` called but `getUserMedia()` failed

**Most Common Error**: NotAllowedError (permission denied)

**Check in Console**:
```
Camera access denied: DOMException: Permission denied by user gesture
```

**Solutions by Error Type**:

#### Permission Denied (NotAllowedError)
```
Browser hasn't asked for camera permission OR user clicked "Block"
```

**Fix**:
1. **macOS**: System Preferences → Security & Privacy → Camera
   - Check if browser has access
2. **Browser Chrome/Firefox**: 
   - Click camera icon in address bar
   - Select "Clear" to reset permissions
   - Reload page and click "Ambil Selfie" again
3. **Allow permission** when browser asks

#### Camera Not Found (NotFoundError)
```
Camera access denied: NotFoundError: Requested device not found
```

**Fix**:
- Check if camera device works: Photo app, Zoom, etc.
- Try different browser
- Restart browser

#### Browser Not Supported (TypeError)
```
Browser Anda tidak mendukung akses kamera
```

**Fix**:
- Use Chrome, Firefox, or Safari (latest versions)
- Not supported: Internet Explorer, very old browsers

---

### Scenario 4: Stream Aktif Tapi Video Tidak Tampil

**Problem**: Camera initialized but video not visible

**Causes**:
- Video element has CSS hiding it
- Video permission exists but stream not working
- Canvas covering video

**Check**:
```javascript
// In browser console:
document.querySelector('[data-selfie-camera-video]').srcObject  // Should not be null
document.querySelector('[data-selfie-camera-video]').readyState  // Should be 1 or higher
```

**Solution**:
1. Open DevTools Inspector
2. Select video element
3. Check `<video>` styles in CSS
4. Look for `display: none` or `visibility: hidden`
5. Verify canvas element is also hidden (should have class `d-none`)

---

## Full Manual Test Flow

### Step 1: Start Server

```bash
cd /Users/vanviakingali/arcav_new_v2/backend
php artisan serve --host=0.0.0.0 --port=8000
```

### Step 2: Open DevTools

1. Open browser to `http://localhost:8000`
2. Open DevTools: `F12` (Windows) or `Cmd+Opt+I` (macOS)
3. Go to **Console** tab
4. Leave open during test

### Step 3: Navigate to Attendance

1. Login if required
2. Go to Attendance page
3. Find the attendance punch-in card

### Step 4: Test Camera Start

1. Look for green "Ambil Selfie" button
2. **Watch Console while clicking**
3. Should see:
   - "Ambil Selfie clicked"
   - "Modal shown event fired"
   - "Starting camera..."
4. Wait 1-2 seconds
5. Should see: "Camera started successfully"

### Step 5: Verify Video Display

1. Modal should show video feed (you should see yourself)
2. If no video:
   - Check if video element is hidden (see Scenario 4)
   - Check browser permissions

### Step 6: Capture Photo

1. Click "Ambil Foto" (Capture) button
2. Console should show:
   - "Capturing photo..."
   - "Photo captured, data length: XXXXX"
3. Modal should show:
   - Preview image on canvas
   - "Ulangi" button visible (replace video)
   - "Simpan Selfie" button visible

### Step 7: Submit Photo

1. Click "Simpan Selfie" (Submit) button
2. Button text changes to "Mengirim..." (Sending...)
3. Console should show:
   - "Submitting selfie..."
   - "Response status: 200"
   - "Response data: {selfie_path: '...', ...}"
4. Modal closes automatically
5. Success toast appears

---

## Advanced Debugging

### Enable Verbose Logging

Edit [attendance-employee.blade.php](../../backend/resources/views/attendance-employee.blade.php) around line 580:

```javascript
// Add this inside startCamera() to see more details
console.log('Video element ready:', videoEl.readyState);
console.log('Media stream tracks:', mediaStream.getTracks());
console.log('Video track enabled:', mediaStream.getVideoTracks()[0]?.enabled);
```

### Test getUserMedia Directly

Paste this in browser console:

```javascript
navigator.mediaDevices.getUserMedia({
  video: { facingMode: 'user' },
  audio: false
}).then(stream => {
  console.log('✓ Camera access granted');
  console.log('Tracks:', stream.getTracks());
  stream.getTracks().forEach(t => t.stop());
}).catch(err => {
  console.error('✗ Camera access failed:', err);
});
```

### Check Modal Bootstrap State

```javascript
// In console:
const modal = bootstrap.Modal.getInstance(document.getElementById('arcav_attendance_selfie_modal'));
console.log('Modal visible:', modal._isShown);
console.log('Modal element:', modal._element);
```

### Inspect Network Request

1. Go to **Network** tab in DevTools
2. Filter by `selfie`
3. Click "Ambil Selfie" and capture
4. Click "Simpan Selfie"
5. Should see `POST /api/v1/attendance/me/selfie`
6. Check:
   - **Request body**: Base64 image data (starts with `data:image/jpeg;base64,...`)
   - **Response**: 200 with `{selfie_path: '...', uploaded_at: '...'}`

---

## Common Browser Issues

| Browser | Issue | Fix |
|---------|-------|-----|
| macOS Safari | Camera permission stuck | Settings → Safari → Clear Website Data |
| Chrome | "Permission denied" repeated | Settings → Privacy → Clear all cookies/cache |
| Firefox | Video not showing | about:config → media.navigator enabled = true |
| Mobile Safari | Not supported | Use Safari 14.1+, iOS 15+ |
| Incognito/Private | Permission denied by default | Use normal browsing mode |

---

## Database Verification

After successful upload, verify data was stored:

```bash
cd /Users/vanviakingali/arcav_new_v2/backend

# Check if selfie was stored
php artisan tinker
>>> $record = AttendanceRecord::whereDate('work_date', today())->first();
>>> $record->selfie_path;  // Should show: selfie/COMPANY_ID/USER_ID_DATE_TIMESTAMP.jpg
>>> $record->selfie_encrypted_hash;  // Should be 64 chars (SHA256 hex)
>>> strlen($record->selfie_encrypted_hash);  // Should return 64
>>> exit
```

---

## API Test (Alternative Method)

If UI testing fails, verify API works:

```bash
php /Users/vanviakingali/arcav_new_v2/test-selfie-api.php
```

This script tests the endpoint independently of browser camera.

---

## Final Checklist Before Production

- [ ] Modal opens without errors
- [ ] Console shows all 7 expected log messages
- [ ] Browser asks for camera permission
- [ ] Video feed displays
- [ ] Capture saves to canvas
- [ ] Submit sends to API
- [ ] Hash stored in database (64 chars)
- [ ] Selfie file exists in private storage
- [ ] Works on Chrome, Firefox, and Safari
- [ ] Works on macOS, Windows, and Linux

---

## Next Steps

If camera still not working after all debugging:

1. **Collect evidence**:
   - Screenshot of console errors
   - Browser name + version
   - OS (macOS/Windows/Linux)
   - Camera works in other apps (Zoom, Photo booth)

2. **Test API directly**:
   - Run `test-selfie-api.php` script
   - If API works but UI doesn't → JavaScript issue
   - If API fails → Backend/database issue

3. **Check permissions**:
   - System level (OS camera permission)
   - Browser level (site camera permission)
   - Firewall/antivirus blocking

