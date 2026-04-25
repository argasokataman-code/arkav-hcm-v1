<?php $page = 'schedule-timing'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        [data-schedule-timing-body]:not([data-hydrated="1"]) {
            display: none;
        }

        [data-schedule-calendar-wrap]:not([data-hydrated="1"]) {
            display: none;
        }

        .schedule-calendar-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .schedule-calendar-legend .badge {
            border: 1px solid rgba(15, 23, 42, 0.12);
            font-size: 0.72rem;
            font-weight: 600;
        }

        .schedule-calendar-panel .fc .fc-toolbar-title {
            font-size: 1rem;
            font-weight: 700;
        }

        .schedule-calendar-panel .fc .fc-button {
            text-transform: capitalize;
        }

        .schedule-calendar-panel .fc .fc-daygrid-day.fc-day-today {
            background: rgba(37, 99, 235, 0.08);
        }
    </style>

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Schedule Timing</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Administration
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Schedule Timing</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="mb-2">
                        <a href="javascript:void(0);" class="btn btn-white d-inline-flex align-items-center" data-schedule-timing-export="csv">
                            <i class="ti ti-file-export me-1"></i>Export CSV
                        </a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                    <div>
                        <h5 class="mb-1">Smart Attendance Planner</h5>
                        <p class="text-muted mb-0 small">Pisahkan mode Office Hour dan Shifting 24 Jam agar rekomendasi jadwal sesuai model operasional perusahaan.</p>
                    </div>
                </div>
                <div class="card-body">
                    <form class="row g-3" data-smart-planner-form>
                        <div class="col-md-3">
                            <label class="form-label">Mode Planner</label>
                            <select class="form-select" data-smart-planner-shift-category>
                                <option value="office_hour">Office Hour Standar</option>
                                <option value="shifting_24h" selected>Shifting 24 Jam</option>
                                <option value="hybrid">Hybrid (Office + Shift)</option>
                            </select>
                            <div class="form-text" data-smart-planner-mode-hint>Mode Shifting 24 Jam mengaktifkan kontrol kelelahan, distribusi shift malam, dan validasi transisi antar shift.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Scope Karyawan</label>
                            <select class="form-select" data-smart-planner-scope>
                                <option value="all">Semua karyawan tenant aktif</option>
                                <option value="team" selected>Filter by team</option>
                                <option value="custom">Custom user IDs</option>
                            </select>
                            <div class="form-text" data-smart-planner-scope-hint>Isi team keyword sesuai unit operasional yang ingin dijadwalkan.</div>
                        </div>
                        <div class="col-md-3" data-smart-planner-field="team-query">
                            <label class="form-label">Team keyword</label>
                            <input type="text" class="form-control" placeholder="contoh: Customer Service" data-smart-planner-team-query>
                        </div>
                        <div class="col-md-3 d-none" data-smart-planner-field="custom-ids">
                            <label class="form-label">Custom user IDs</label>
                            <input type="text" class="form-control" placeholder="contoh: 101, 102, 150" data-smart-planner-custom-ids>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Week Start</label>
                            <input type="date" class="form-control" data-smart-planner-week-start>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Planning Horizon</label>
                            <select class="form-select" data-smart-planner-horizon>
                                <option value="single_week" selected>Minggu terpilih saja</option>
                                <option value="end_of_year">Generate sampai akhir tahun</option>
                            </select>
                            <div class="form-text" data-smart-planner-horizon-hint>Mode default: generate hanya untuk minggu yang dipilih.</div>
                        </div>
                        <div class="col-md-3 d-none" data-smart-planner-field="horizon-end-date">
                            <label class="form-label">Horizon End Date</label>
                            <input type="date" class="form-control" data-smart-planner-end-date readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Max Work Days</label>
                            <input type="number" class="form-control" min="1" max="7" value="5" data-smart-planner-max-work-days>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Min Days Off</label>
                            <input type="number" class="form-control" min="0" max="7" value="2" data-smart-planner-min-days-off>
                        </div>
                        <div class="col-md-2" data-smart-planner-field="rest-rule">
                            <label class="form-label">Min Rest (hours)</label>
                            <input type="number" class="form-control" min="1" max="24" value="12" data-smart-planner-min-rest>
                        </div>
                        <div class="col-md-2" data-smart-planner-field="night-rule">
                            <label class="form-label">Max Night Streak</label>
                            <input type="number" class="form-control" min="1" max="7" value="3" data-smart-planner-max-night>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100" data-smart-planner-submit>Generate</button>
                        </div>
                        <div class="col-12">
                            <small class="text-muted" data-smart-planner-scope-meta>Belum ada scope yang dipilih.</small>
                        </div>
                        <div class="col-12">
                            <div class="border rounded p-3" data-smart-planner-settings-panel>
                                <!-- Header dengan Mode Indicator -->
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <div>
                                        <h6 class="mb-1">📋 Planner Defaults & Transition Matrix</h6>
                                        <small class="badge bg-light text-dark" data-smart-planner-mode-indicator>Viewing</small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-smart-planner-edit-mode-btn>
                                            <i class="ti ti-pencil me-1"></i>Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger d-none" data-smart-planner-cancel-edit-btn>
                                            <i class="ti ti-x me-1"></i>Cancel
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary d-none" data-smart-planner-save-settings>
                                            <i class="ti ti-check me-1"></i>Simpan
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-smart-planner-reset-defaults-btn title="Reset ke default tenant yang tersimpan">
                                            <i class="ti ti-reload me-1"></i>Reset
                                        </button>
                                    </div>
                                </div>

                                <!-- Panduan Umum -->
                                <div class="alert alert-info small mb-3" role="alert">
                                    <strong>💡 Panduan:</strong> Konfigurasi ini menjadi fallback rule saat generate jadwal tanpa rule custom. Setelah disimpan, bisa diubah kembali kapan saja. Saat generate, Anda bisa override nilai ini per-session tanpa mengubah default yang tersimpan.
                                </div>

                                <!-- Card 1: Default Rules -->
                                <div class="card mb-3 border">
                                    <div class="card-header bg-light py-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <h6 class="mb-0 small">⚙️ Default Rules</h6>
                                            <span class="badge bg-light text-dark small">Fallback ketika generate tanpa custom rules</span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label small">Max Work Days Per Week</label>
                                                <input type="number" class="form-control" min="1" max="7" value="5" data-smart-planner-default-max-work-days disabled>
                                                <small class="form-text text-muted">Maksimal hari kerja per minggu</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">Min Days Off Per Week</label>
                                                <input type="number" class="form-control" min="0" max="7" value="2" data-smart-planner-default-min-days-off disabled>
                                                <small class="form-text text-muted">Minimal hari libur per minggu</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">Min Rest Hours Between Shifts</label>
                                                <input type="number" class="form-control" min="1" max="24" value="12" data-smart-planner-default-min-rest disabled>
                                                <small class="form-text text-muted">Jam istirahat minimal antar shift</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">Max Consecutive Night Shifts</label>
                                                <input type="number" class="form-control" min="1" max="7" value="3" data-smart-planner-default-max-night disabled>
                                                <small class="form-text text-muted">Max shift malam berturut-turut</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 2: Transition Matrix -->
                                <div class="card border">
                                    <div class="card-header bg-light py-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <h6 class="mb-0 small">🚫 Forbidden Transitions (Dilarang)</h6>
                                            <span class="badge bg-light text-dark small">Kombinasi shift yg tidak boleh bersebelahan</span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted small mb-2">Centang kombinasi shift yang ingin dilarang. Contoh: Night → Morning (capek jika langsung pagi).</p>
                                        <div class="row g-2" data-smart-planner-transition-matrix-wrap>
                                            <div class="col-12" data-smart-planner-transition-matrix>
                                                <div class="text-muted small">Loading transition matrix...</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Feedback -->
                                <div class="mt-3">
                                    <small class="text-muted d-block" data-smart-planner-settings-feedback>Belum ada perubahan default tersimpan pada sesi ini.</small>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="alert alert-light mt-3 mb-3 d-none" data-smart-planner-feedback role="alert"></div>

                    <div class="row g-3 d-none" data-smart-planner-result>
                        <div class="col-12">
                            <div class="alert alert-warning mb-0" role="alert">
                                <strong>Draft planner:</strong> hasil di bawah adalah rekomendasi AI untuk minggu terpilih.
                                Belum mengubah jadwal permanen sampai admin review dan simpan manual di Schedule Timing List.
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Validation</div>
                                <div class="fw-semibold" data-smart-planner-validation>—</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Fairness Score</div>
                                <div class="fw-semibold" data-smart-planner-fairness>—</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Fatigue Risk</div>
                                <div class="fw-semibold" data-smart-planner-fatigue>—</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Unmet Coverage</div>
                                <div class="fw-semibold" data-smart-planner-unmet>—</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded p-3">
                                <h6 class="mb-2">Explanation</h6>
                                <p class="mb-0 text-muted" data-smart-planner-explanation>—</p>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="mb-2">Violations</h6>
                                <ul class="mb-0 ps-3" data-smart-planner-violations>
                                    <li class="text-muted">Belum ada data.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="mb-2">Improvement Suggestions</h6>
                                <ul class="mb-0 ps-3" data-smart-planner-suggestions>
                                    <li class="text-muted">Belum ada data.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded p-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <h6 class="mb-0">Preview Auto-Assign Karyawan 24 Jam (Draft)</h6>
                                    <small class="text-muted" data-smart-planner-assignment-meta>Belum ada draft assignment.</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Karyawan</th>
                                                <th class="text-nowrap">Work Days</th>
                                                <th class="text-nowrap">Off Days</th>
                                                <th class="text-nowrap">Night Count</th>
                                                <th>Pola Mingguan</th>
                                            </tr>
                                        </thead>
                                        <tbody data-smart-planner-assignment-body>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">Generate planner untuk melihat draft assignment.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded p-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <h6 class="mb-0">Preview Diff Dominant Shift (Before / After)</h6>
                                    <small class="text-muted" data-smart-planner-diff-meta>Belum ada preview diff.</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Karyawan</th>
                                                <th>Schedule Saat Ini</th>
                                                <th>Dominant Shift Draft</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody data-smart-planner-diff-body>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-3">Generate planner untuk melihat preview diff.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded p-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <h6 class="mb-0">Conflict Resolver (Pre-publish)</h6>
                                    <small class="text-muted" data-smart-planner-conflict-meta>Belum ada analisis conflict.</small>
                                </div>
                                <ul class="mb-3 ps-3" data-smart-planner-conflict-list>
                                    <li class="text-muted">Generate planner untuk memunculkan conflict check.</li>
                                </ul>
                                <div class="form-check form-check-md">
                                    <input class="form-check-input" type="checkbox" value="1" id="smart-planner-force-apply" data-smart-planner-force-apply>
                                    <label class="form-check-label" for="smart-planner-force-apply">
                                        Force apply walau ada conflict (gunakan hanya jika sudah review manual)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <h6 class="mb-1">Publish Draft Ke Schedule Timing</h6>
                                    <small class="text-muted" data-smart-planner-apply-meta>Belum ada draft yang siap dipublish.</small>
                                    <small class="text-muted d-block mt-1">Mode publish tersedia: dominant shift per user (legacy) atau roster harian per tanggal dari draft planner.</small>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-success" data-smart-planner-apply-dominant disabled>
                                        Apply Dominant Shift per User
                                    </button>
                                    <button type="button" class="btn btn-success" data-smart-planner-apply-daily disabled>
                                        Publish Roster Harian (Per Date)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>Schedule Timing List</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <div class="btn-group me-2" role="group" aria-label="Schedule view mode">
                            <button type="button" class="btn btn-outline-primary active" data-schedule-view-toggle="list">List</button>
                            <button type="button" class="btn btn-outline-primary" data-schedule-view-toggle="calendar">Calendar</button>
                        </div>
                        <div class="me-2">
                            <input type="text" class="form-control" placeholder="Search name / job title" data-schedule-timing-search>
                        </div>
                        <div>
                            <select class="form-select" data-schedule-timing-sort>
                                <option value="name_asc">Sort: Name A-Z</option>
                                <option value="name_desc">Sort: Name Z-A</option>
                                <option value="start_asc">Sort: Start earliest</option>
                                <option value="start_desc">Sort: Start latest</option>
                            </select>
                        </div>
                        <div class="form-check form-check-md ms-2">
                            <input class="form-check-input" type="checkbox" value="1" id="schedule-ai-draft-only" data-schedule-timing-ai-only>
                            <label class="form-check-label" for="schedule-ai-draft-only">Show AI Draft only</label>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div data-schedule-view-panel="list">
                        <div class="custom-datatable-filter table-responsive">
                            <table class="table">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="no-sort">
                                            <div class="form-check form-check-md">
                                                <input class="form-check-input" type="checkbox" id="select-all">
                                            </div>
                                        </th>
                                        <th>Name</th>
                                        <th>Job Title</th>
                                        <th>User Available Timings</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-schedule-timing-body>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Loading schedule timings...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="p-3 d-none schedule-calendar-panel" data-schedule-view-panel="calendar">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <small class="text-muted" data-schedule-calendar-meta>
                                Kalender menampilkan draft hasil planner + hari libur aktif untuk tenant.
                            </small>
                            <div class="schedule-calendar-legend">
                                <span class="badge text-bg-primary">Morning</span>
                                <span class="badge text-bg-warning text-dark">Afternoon</span>
                                <span class="badge text-bg-dark">Night</span>
                                <span class="badge text-bg-secondary">Off</span>
                                <span class="badge text-bg-danger">Holiday</span>
                            </div>
                        </div>
                        <div class="alert alert-light mb-3" data-schedule-calendar-loading>Loading calendar...</div>
                        <div data-schedule-calendar-wrap>
                            <div data-schedule-calendar></div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2" data-schedule-timing-pagination style="display: none;">
                    <span class="text-muted small" data-schedule-timing-page-info></span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light border" data-schedule-timing-prev>Sebelumnya</button>
                        <button type="button" class="btn btn-sm btn-light border" data-schedule-timing-next>Berikutnya</button>
                    </div>
                </div>
            </div>

        </div>


    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

    <div class="modal fade" id="arcav_schedule_timing_edit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set schedule timing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form data-schedule-timing-edit-form>
                    <div class="modal-body">
                        <p class="fw-medium mb-1" data-st-edit-employee>—</p>
                        <p class="text-muted small mb-3">Pilih shift master atau isi jam manual (custom).</p>
                        <input type="hidden" data-st-edit-user-id value="">
                        <div class="mb-3">
                            <label class="form-label">Shift master</label>
                            <select class="form-select" data-st-edit-shift>
                                <option value="">Custom (manual)</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Start</label>
                                <input type="time" class="form-control" data-st-edit-start required value="09:00">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">End</label>
                                <input type="time" class="form-control" data-st-edit-end required value="18:00">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-outline-danger d-none" data-st-edit-reset>Hapus override · kembali ke otomatis</button>
                        <div class="d-flex gap-2 ms-auto">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" data-st-edit-submit>Simpan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection