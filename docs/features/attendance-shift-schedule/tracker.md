# Attendance, Shift, and Schedule Tracker

## Snapshot Status

- Tanggal: 2026-04-25
- Status: ready for deployment
- Ringkasan: hardening 4-point untuk shift/schedule sudah aktif: sorting start-time stabil sebelum pagination, upsert schedule timing tenant-scope per company+user, tenant admin tidak bisa mutasi template shift global, dan UI shift master menyembunyikan aksi write untuk role tanpa `schedule.manage`.

## Evidence Terbaru

- Runtime docs source of truth aktif di `docs/api/hcm-shift-schedule-api.md` dan `docs/api/hcm-attendance-api.md`.
- README feature mengikat `/timesheets` dan `/schedule-timing` ke role matrix aktif.
- Kontrak write tenant scope + sort stability tervalidasi di `backend/tests/Feature/AttendanceApiTest.php` (`test_schedule_timing_upsert_is_scoped_per_company_for_same_user`, `test_schedule_timing_start_sort_applies_before_pagination`).
- Guard mutasi template global tervalidasi di `backend/tests/Feature/ShiftMasterApiTest.php` (`test_tenant_admin_cannot_mutate_global_shift_template`).
- UI permission gating shift master tervalidasi di `backend/tests/ui/shift-master-data.wiring.test.js`.
- Skema persistence schedule timing sudah ditenantkan pada unique key komposit lewat migration `backend/database/migrations/2026_04_25_090000_update_hcm_schedule_timings_unique_scope.php`.
- Wiring FE untuk `shiftId` numeric dan reversed timesheet range tervalidasi di `backend/tests/ui/attendance.wiring.test.js`.
- Role/permission URL aktif tercatat di `docs/planning/active-hcm-templates-and-permissions.md`.

## Gap Aktif

1. Belum ada log manual UI E2E untuk CRUD shift master dan override schedule di browser.
2. History perubahan jadwal per user belum didokumentasikan sebagai audit trail UI yang kaya.
3. Cross-check bundle `shift-master-data.js` vs `attendance-data.js` masih perlu dijaga bila ada refactor asset split.

## Keputusan Saat Ini

- Anggap feature shift/schedule siap sebagai source of truth jadwal kerja tenant aktif.
- Perubahan berikutnya wajib menjaga kontrak `users.id` dan `hcm_shifts.id`, plus tenant guard pada seluruh mutation schedule.
