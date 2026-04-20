# Termination Progress Tracker

## Snapshot 2026-04-19

- Status: in progress
- Focus: hubungkan lifecycle `finalized` ke source runtime payroll period, settlement breakdown, dan clearance item terstruktur.

## Implemented

- Status `finalized` diterima end-to-end di controller, docs, UI modal, dan list/detail Termination.
- Termination `finalized` sekarang dapat menyimpan snapshot settlement manual:
  - payroll period target
  - final salary amount
  - final allowance amount
  - final deduction amount
  - server-computed net payable
  - asset return notes
  - clearance notes
- Termination `finalized` sekarang juga dapat me-refresh preview settlement dari source runtime:
  - auto-resolve payroll period aktual terdekat dan simpan `payrollPeriodId`
  - hitung gaji pokok dan tunjangan tetap secara prorata sampai `terminationDate`
  - gunakan payroll run bulanan terdekat sebagai reference line untuk komponen tambahan/potongan bila tersedia
  - tambahkan kompensasi PKWT bila profile contract memang due pada bulan termination
  - simpan `clearanceItems` terstruktur dari asset assignment aktif yang belum return
- List Termination menampilkan ringkasan settlement untuk baris `finalized`.
- Detail Termination menampilkan breakdown settlement dan clearance item saat snapshot tersedia.
- Modal Termination punya tombol `Refresh from payroll & assets` untuk auto-fill final settlement.
- Clearance item asset sekarang bisa langsung di-return dari context Termination untuk record existing.

## Evidence

- Backend regression: `backend/tests/Feature/TerminationApiTest.php`
- Frontend API contract: `backend/tests/ui/termination-api-contract.test.js`
- Frontend page wiring: `backend/tests/ui/termination.wiring.test.js`
- Employee detail relation: `backend/tests/ui/employee-details-training.wiring.test.js`

## Latest Validation

- `php artisan migrate --force` → migrations `2026_04_27_000000_add_finalization_fields_to_hcm_terminations_table` dan `2026_04_27_010000_add_structured_settlement_fields_to_hcm_terminations_table` applied.
- `php artisan test tests/Feature/TerminationApiTest.php` → 7 passed, 87 assertions.
- `npm run test:ui -- tests/ui/termination-api-contract.test.js tests/ui/termination.wiring.test.js tests/ui/employee-details-training.wiring.test.js` → 3 files passed, 14 tests passed.
- `npm run build` → success.
- `scripts/check-api-docs-sync.sh` → no backend API surface changes detected.

## Open Gaps

- Settlement policy termination sekarang sudah lebih kaya, tetapi formula masih fokus ke prorata gaji pokok/tunjangan tetap + kompensasi PKWT; belum ada policy tambahan lain seperti severance, leave payout, atau komponen custom HR policy.
- Clearance asset sekarang bisa dipicu return langsung dari Termination, tetapi belum ada approval step/manual checklist lintas kewajiban non-asset per item.
- Preview settlement masih belum menggabungkan source lintas-purpose seperti THR atau run khusus lain bila bisnis ingin settlement final multi-source pada satu layar.

## Next Recommended Slice

- Tambahkan checklist/approval step lintas kewajiban non-asset dari context Termination.
- Tambahkan formula settlement policy lain seperti severance, leave payout, atau custom compensation policy bila HR membutuhkannya.
- Evaluasi agregasi source lintas-purpose seperti THR atau payroll run khusus lain bila settlement final perlu tampil multi-source pada satu preview.