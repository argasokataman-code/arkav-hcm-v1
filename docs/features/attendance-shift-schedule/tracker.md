# Attendance, Shift, and Schedule Tracker

## Snapshot Status

- Tanggal: 2026-04-20
- Status: ready for deployment
- Ringkasan: master shift, schedule timing per user, dan timesheets admin sudah aktif dengan kontrak numeric identifier yang sinkron FE/BE, tenant guard untuk write schedule, dan validasi negative path date range timesheet.

## Evidence Terbaru

- Runtime docs source of truth aktif di `docs/api/hcm-shift-schedule-api.md` dan `docs/api/hcm-attendance-api.md`.
- README feature mengikat `/timesheets` dan `/schedule-timing` ke role matrix aktif.
- Kontrak write tenant scope tervalidasi di `backend/tests/Feature/AttendanceApiTest.php`.
- Wiring FE untuk `shiftId` numeric dan reversed timesheet range tervalidasi di `backend/tests/ui/attendance.wiring.test.js`.
- Role/permission URL aktif tercatat di `docs/planning/active-hcm-templates-and-permissions.md`.

## Gap Aktif

1. Belum ada log manual UI E2E untuk CRUD shift master dan override schedule di browser.
2. History perubahan jadwal per user belum didokumentasikan sebagai audit trail UI yang kaya.
3. Cross-check bundle `shift-master-data.js` vs `attendance-data.js` masih perlu dijaga bila ada refactor asset split.

## Keputusan Saat Ini

- Anggap feature shift/schedule siap sebagai source of truth jadwal kerja tenant aktif.
- Perubahan berikutnya wajib menjaga kontrak `users.id` dan `hcm_shifts.id`, plus tenant guard pada seluruh mutation schedule.
