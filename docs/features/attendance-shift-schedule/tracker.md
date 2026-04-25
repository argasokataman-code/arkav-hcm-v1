# Attendance, Shift, and Schedule Tracker

## Snapshot Status

- Tanggal: 2026-04-26
- Status: in progress (foundation configurability tahap 4 + UI edit mode hardening)
- Ringkasan: hardening shift/schedule tetap aktif dengan dukungan shift lintas hari (overnight), sorting start-time stabil sebelum pagination, upsert tenant-scope per company+user, tenant admin tidak bisa mutasi template global, UI shift master tetap menghormati permission write, calendar view tetap aktif, smart planner mendukung batch rolling sampai akhir tahun, foundation baru untuk default rules + transition matrix per-tenant, publish roster harian bertanggal, endpoint roster index untuk review paginated, dan **UI edit mode dengan clear state indicator + inline guidance untuk prevent accidental configuration changes**.

## Evidence Terbaru

- Runtime docs source of truth aktif di `docs/api/hcm-shift-schedule-api.md` dan `docs/api/hcm-attendance-api.md`.
- Kontrak endpoint `POST /v1/hcm/smart-attendance-shifting/generate` tersinkron di `docs/api/hcm-attendance-api.md` dan `docs/api/openapi.yaml`.
- README feature mengikat `/timesheets` dan `/schedule-timing` ke role matrix aktif.
- README feature juga mengikat smart planner ke alur keputusan admin sebelum mutasi jadwal manual.
- Smart planner panel pada halaman `/schedule-timing` sudah ter-wire via `frontend/resources/js/attendance-data.js` untuk submit rules mingguan dan menampilkan hasil `validation/fairness/fatigue/violations/suggestions`.
- Tahap 1 calendar UX aktif di `/schedule-timing`: toggle `List/Calendar`, render event draft planner (M/A/N/OFF), dan overlay hari libur dari endpoint `GET /v1/hcm/holidays`.
- Tahap 2 calendar UX aktif di `/schedule-timing`: opsi horizon planner `single week` vs `end of year`, eksekusi API planner per minggu (rolling), dan agregasi hasil lintas minggu ke satu draft kalender.
- Tahap 3 publish UX aktif di `/schedule-timing`: tombol `Apply Dominant Shift per User` untuk mem-publish dominant shift hasil draft ke `schedule-timing` pada user dalam scope planner terakhir.
- Tahap 3.1 guardrail UX aktif di `/schedule-timing`: panel `Preview Diff Dominant Shift (Before/After)` dan `Conflict Resolver (Pre-publish)` menentukan apakah publish bisa langsung jalan atau perlu `Force apply`.
- Tahap 4 foundation configurability aktif: endpoint `GET/PUT /v1/hcm/smart-attendance-shifting/settings` menyimpan default rules + transition matrix (`forbiddenTransitions`) per-tenant.
- Tahap 4 UI edit mode hardening aktif: panel `📋 Planner Defaults & Transition Matrix` dengan dual-mode UX:
  - **View Mode (default)**: Semua input disabled, badge "Viewing" (light), tombol "Edit" aktif, tombol "Simpan/Cancel/Reset" hidden.
  - **Edit Mode**: Ketika admin click "Edit", input fields & checkboxes enabled, badge "Editing" (warning), tombol "Simpan/Cancel/Reset" visible, tombol Generate & Publish disabled untuk prevent accidental trigger.
  - **Inline Guidance**: Alert box menjelaskan default rules hanya fallback saat generate tanpa custom rules, serta bahwa default bisa diubah kapan saja tanpa lock-in.
  - **Card Descriptions**: Setiap card (Default Rules, Forbidden Transitions) punya subtitle yang jelas fungsinya untuk clarity non-engineer.
  - **Reset Button**: Dalam edit mode, admin bisa click "Reset" untuk reload dari DB tanpa save draft edits.
  - State management tervalidasi: `smartPlannerEditMode` + `smartPlannerEditModeOriginalValues` state di frontend, store/restore logic di `bindSmartPlanner()`.
  - Wiring UX edit mode tervalidasi manual (tapi tidak di Vitest automation, karena input enable/disable state sulit ditest via headless DOM).
- Tahap 4 publish harian aktif: endpoint `POST /v1/hcm/smart-attendance-shifting/publish-roster` menulis roster bertanggal ke `hcm_schedule_rosters` dengan kunci unik company+user+work_date.
- Tahap 4 roster review aktif: endpoint `GET /v1/hcm/schedule-rosters` menyediakan list roster harian paginated untuk rentang tanggal.
- Wiring FE untuk conflict gate tervalidasi di `backend/tests/ui/attendance.wiring.test.js` (`requires force apply when critical conflicts are detected before publish`).
- Wiring FE publish harian tervalidasi di `backend/tests/ui/attendance.wiring.test.js` (`publishes daily roster per date from planner draft`).
- Hardcode label shift kalender dari slot waktu tetap (07/15/23) sudah dieliminasi; label sekarang bersumber dari metadata shift runtime (`/v1/hcm/shifts`).
- Asset FullCalendar dimuat khusus halaman `/schedule-timing` melalui `backend/resources/views/layout/partials/footer-scripts.blade.php`.
- Scheduler backend sudah dituning untuk variasi lebih nyata pada mode shifting/hybrid: balancing target shift count per user + anti-repeat shift streak saat memilih kandidat assignment.
- Kontrak write tenant scope + sort stability tervalidasi di `backend/tests/Feature/AttendanceApiTest.php` (`test_schedule_timing_upsert_is_scoped_per_company_for_same_user`, `test_schedule_timing_start_sort_applies_before_pagination`).
- Dukungan overnight pada shift master dan schedule timing tervalidasi di `backend/tests/Feature/ShiftMasterApiTest.php` (`test_shift_crud_supports_overnight_window`) dan `backend/tests/Feature/AttendanceApiTest.php` (`test_schedule_timing_admin_can_apply_overnight_shift_id`).
- Guard mutasi template global tervalidasi di `backend/tests/Feature/ShiftMasterApiTest.php` (`test_tenant_admin_cannot_mutate_global_shift_template`).
- UI permission gating shift master tervalidasi di `backend/tests/ui/shift-master-data.wiring.test.js`.
- Skema persistence schedule timing sudah ditenantkan pada unique key komposit lewat migration `backend/database/migrations/2026_04_25_090000_update_hcm_schedule_timings_unique_scope.php`.
- Wiring FE untuk `shiftId` numeric dan reversed timesheet range tervalidasi di `backend/tests/ui/attendance.wiring.test.js`.
- Role/permission URL aktif tercatat di `docs/planning/active-hcm-templates-and-permissions.md`.
- Regression test endpoint smart planner ada di `backend/tests/Feature/HcmSmartAttendanceApiTest.php` (happy path payload structure + admin guard).
- Regression test wiring frontend smart planner ada di `backend/tests/ui/attendance.wiring.test.js` (`generates smart planner and renders recommendation summary`).
- Regression test batch planner sampai akhir tahun ada di `backend/tests/ui/attendance.wiring.test.js` (`generates planner in weekly batches until end of year`).
- Regression test publish dominant shift ada di `backend/tests/ui/attendance.wiring.test.js` (`publishes dominant shifts from planner draft into schedule timing`).
- Regression test backend variasi shifting ada di `backend/tests/Feature/HcmSmartAttendanceApiTest.php` (`test_generate_shifting_mode_distributes_multiple_shift_types`).

## Gap Aktif

1. Belum ada log manual UI E2E untuk CRUD shift master, save planner settings, publish dominant, dan publish roster harian di browser.
2. History perubahan jadwal per user belum didokumentasikan sebagai audit trail UI yang kaya.
3. Cross-check bundle `shift-master-data.js` vs `attendance-data.js` masih perlu dijaga bila ada refactor asset split.
4. Manual UI E2E khusus panel smart planner + calendar (single-week + year-end batch -> review kalender -> apply perubahan jadwal) belum dicatat sebagai runbook terpisah.

## Keputusan Saat Ini

- Anggap feature shift/schedule siap sebagai source of truth jadwal kerja tenant aktif.
- Perubahan berikutnya wajib menjaga kontrak `users.id` dan `hcm_shifts.id`, plus tenant guard pada seluruh mutation schedule.
