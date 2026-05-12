<?php $page = 'schedule-timing'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
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
                    <h2 class="mb-1">Shift &amp; Schedule</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Attendance
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Shift &amp; Schedule</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="mb-2 dropdown">
                        <button type="button" class="btn btn-white d-inline-flex align-items-center dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Unduh daftar Shift &amp; Schedule">
                            <i class="ti ti-file-export me-1"></i>Export Shift &amp; Schedule
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" data-schedule-timing-export="xlsx">Export XLSX</a></li>
                            <li><a class="dropdown-item" href="#" data-schedule-timing-export="csv">Export CSV</a></li>
                        </ul>
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
                        <p class="text-muted mb-0 small">Gunakan Panduan untuk arti mode, scope, dan default planner.</p>
                    </div>
                    <a href="javascript:void(0);" class="btn btn-light btn-sm d-inline-flex align-items-center"
                       data-bs-toggle="modal" data-bs-target="#arcav_smart_planner_guide">
                        <i class="ti ti-info-circle me-1"></i>Panduan planner
                    </a>
                </div>
                <div class="card-body">
                    <form class="row g-3" data-smart-planner-form>
                        <div class="col-md-3">
                            <label class="form-label">Pola Kerja Planner</label>
                            <select class="form-select" data-smart-planner-shift-category>
                                <option value="office_hour">Office Hour Standar</option>
                                <option value="shifting_24h" selected>Shifting 24 Jam</option>
                                <option value="hybrid">Hybrid (Office + Shift)</option>
                            </select>
                            <div class="form-text" data-smart-planner-mode-hint>Pilihan manual rule planner. Bukan auto dari master shift.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sasaran Draft</label>
                            <select class="form-select" data-smart-planner-scope>
                                <option value="all">Semua employee aktif</option>
                                <option value="department" selected>Per departemen</option>
                                <option value="custom">Karyawan pilihan (advanced)</option>
                            </select>
                            <div class="form-text" data-smart-planner-scope-hint>Sumber data: daftar employee tenant aktif.</div>
                        </div>
                        <div class="col-md-3" data-smart-planner-field="department">
                            <label class="form-label">Departemen</label>
                            <select class="form-select" data-smart-planner-department>
                                <option value="">Pilih departemen</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-none" data-smart-planner-field="custom-ids">
                            <label class="form-label">Pilih Karyawan (Advanced)</label>
                            <select class="form-select" data-smart-planner-custom-ids multiple size="5" aria-label="Pilih karyawan untuk scope custom"></select>
                            <div class="form-text">Pilih satu atau lebih karyawan dari tenant aktif.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mulai Minggu</label>
                            <input type="date" class="form-control" data-smart-planner-week-start>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Rentang Perencanaan</label>
                            <select class="form-select" data-smart-planner-horizon>
                                <option value="single_week" selected>Minggu terpilih saja</option>
                                <option value="end_of_year">Generate sampai akhir tahun</option>
                            </select>
                            <div class="form-text" data-smart-planner-horizon-hint>Pilih 1 minggu atau batch mingguan sampai 31 Desember.</div>
                        </div>
                        <div class="col-md-3 d-none" data-smart-planner-field="horizon-end-date">
                            <label class="form-label">Horizon End Date</label>
                            <input type="date" class="form-control" data-smart-planner-end-date readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Max Work Days <span class="badge bg-light text-secondary border fw-normal" style="font-size:0.68rem;">Override generate</span></label>
                            <input type="number" class="form-control" min="1" max="7" value="5" data-smart-planner-max-work-days>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Min Days Off <span class="badge bg-light text-secondary border fw-normal" style="font-size:0.68rem;">Override generate</span></label>
                            <input type="number" class="form-control" min="0" max="7" value="2" data-smart-planner-min-days-off>
                        </div>
                        <div class="col-md-2" data-smart-planner-field="rest-rule">
                            <label class="form-label">Min Rest (hours) <span class="badge bg-light text-secondary border fw-normal" style="font-size:0.68rem;">Override generate</span></label>
                            <input type="number" class="form-control" min="1" max="24" value="12" data-smart-planner-min-rest>
                        </div>
                        <div class="col-md-2" data-smart-planner-field="night-rule">
                            <label class="form-label">Max Night Streak <span class="badge bg-light text-secondary border fw-normal" style="font-size:0.68rem;">Override generate</span></label>
                            <input type="number" class="form-control" min="1" max="7" value="3" data-smart-planner-max-night>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100" data-smart-planner-submit>
                                <span data-smart-planner-submit-label>Generate Draft</span>
                                <span class="spinner-border spinner-border-sm d-none ms-1" data-smart-planner-spinner aria-hidden="true"></span>
                            </button>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-warning py-2 small d-none" data-smart-planner-feedback-inline role="alert"></div>
                            <small class="text-muted" data-smart-planner-scope-meta>Rekomendasi flow: pilih pola kerja, pilih sasaran draft, generate, review conflict, lalu publish.</small>
                        </div>
                        <div class="col-12">
                            <div class="card border-0 bg-light" data-smart-planner-settings-panel>
                                <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between flex-wrap gap-2 pb-0">
                                    <div>
                                        <h6 class="mb-1">Planner Defaults &amp; Transition Rules</h6>
                                        <div class="text-muted small">Fallback saat generate tanpa custom rule.</div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge bg-white text-dark border" data-smart-planner-mode-indicator>View mode</span>
                                        <button type="button" class="btn btn-light btn-sm d-inline-flex align-items-center" data-smart-planner-edit-mode-btn>
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
                                <div class="card-body pt-3">
                                    <div class="row g-3">
                                        <div class="col-md-6 col-xl-3">
                                            <label class="form-label small">Max Work Days</label>
                                            <input type="number" class="form-control" min="1" max="7" value="5" data-smart-planner-default-max-work-days disabled>
                                        </div>
                                        <div class="col-md-6 col-xl-3">
                                            <label class="form-label small">Min Days Off</label>
                                            <input type="number" class="form-control" min="0" max="7" value="2" data-smart-planner-default-min-days-off disabled>
                                        </div>
                                        <div class="col-md-6 col-xl-3">
                                            <label class="form-label small">Min Rest (hours)</label>
                                            <input type="number" class="form-control" min="1" max="24" value="12" data-smart-planner-default-min-rest disabled>
                                        </div>
                                        <div class="col-md-6 col-xl-3">
                                            <label class="form-label small">Max Night Streak</label>
                                            <input type="number" class="form-control" min="1" max="7" value="3" data-smart-planner-default-max-night disabled>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                                <label class="form-label mb-0">Transition yang diblok</label>
                                                <small class="text-muted">Berbasis tipe shift planner, bukan nama shift individual.</small>
                                            </div>
                                            <div class="row g-2" data-smart-planner-transition-matrix-wrap>
                                                <div class="col-12" data-smart-planner-transition-matrix>
                                                    <div class="text-muted small">Loading transition matrix...</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <small class="text-muted d-block" data-smart-planner-settings-feedback>Belum ada perubahan default tersimpan pada sesi ini.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="alert alert-light mt-3 mb-3 d-none" data-smart-planner-feedback role="alert"></div>

                    <div class="row g-3" data-smart-planner-result>
                        <div class="col-12">
                            <div class="alert alert-warning mb-0" role="alert">
                                <strong>Draft planner:</strong> hasil di bawah adalah rekomendasi AI untuk minggu terpilih.
                                Belum mengubah jadwal permanen sampai admin review dan simpan manual di Schedule Timing List.
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Status Validasi</div>
                                <div class="fw-semibold" data-smart-planner-validation>—</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Skor Keadilan</div>
                                <div class="fw-semibold" data-smart-planner-fairness>—</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Risiko Kelelahan</div>
                                <div class="fw-semibold" data-smart-planner-fatigue>—</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Coverage Belum Terpenuhi</div>
                                <div class="fw-semibold" data-smart-planner-unmet>—</div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded p-3">
                                <h6 class="mb-2">Penjelasan</h6>
                                <p class="mb-0 text-muted" data-smart-planner-explanation>—</p>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="mb-1">⚠️ Pelanggaran Aturan Jadwal</h6>
                                <p class="text-muted small mb-2">Jadwal draft masih memiliki isu yang belum terpenuhi. Tinjau dan selesaikan sebelum publish.</p>
                                <ul class="mb-0 ps-3" data-smart-planner-violations>
                                    <li class="text-muted">Belum ada data.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="mb-1">💡 Saran Perbaikan</h6>
                                <p class="text-muted small mb-2">Langkah yang disarankan sistem untuk memperbaiki jadwal dan mengurangi risiko.</p>
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
                                    <h6 class="mb-0">Conflict Resolver (Pra-Publish)</h6>
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

                        {{-- Shift Swap Simulator --}}
                        <div class="col-12">
                            <div class="border rounded p-3">
                                <h6 class="mb-1">🔄 Simulasi Tukar Jadwal (Shift Swap)</h6>
                                <p class="text-muted small mb-3">Simulasikan tukar jadwal antara dua karyawan dan lihat dampak risiko kelelahan, jeda istirahat, dan transisi shift sebelum memutuskan untuk swap.</p>
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Karyawan A</label>
                                        <select class="form-select form-select-sm" data-swap-user-a>
                                            <option value="">Pilih karyawan A...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Tanggal jadwal A</label>
                                        <input type="date" class="form-control form-control-sm" data-swap-date-a>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Karyawan B (tukar dengan)</label>
                                        <select class="form-select form-select-sm" data-swap-user-b>
                                            <option value="">Pilih karyawan B...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Tanggal jadwal B</label>
                                        <input type="date" class="form-control form-control-sm" data-swap-date-b>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary w-100" data-swap-simulate-btn disabled>
                                            Simulasi Swap
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-3 d-none" data-swap-result></div>
                            </div>
                        </div>

                        {{-- Replacement Finder --}}
                        <div class="col-12">
                            <div class="border rounded p-3">
                                <h6 class="mb-1">🔍 Cari Pengganti Karyawan Absen / Cuti</h6>
                                <p class="text-muted small mb-3">Pilih karyawan yang tidak hadir / cuti, tentukan tanggal dan shift yang perlu diisi, sistem akan mencari siapa yang paling cocok menggantikan berdasarkan beban kerja dan aturan jadwal.</p>
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Karyawan yang absen / cuti</label>
                                        <select class="form-select form-select-sm" data-replacement-absent-user>
                                            <option value="">Pilih karyawan...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Tanggal absen</label>
                                        <input type="date" class="form-control form-control-sm" data-replacement-date>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Shift yang perlu diisi</label>
                                        <select class="form-select form-select-sm" data-replacement-shift>
                                            <option value="">Pilih shift...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="button" class="btn btn-sm btn-outline-warning w-100" data-replacement-find-btn disabled>
                                            Cari Pengganti
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-3 d-none" data-replacement-result></div>
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

                    <div class="modal fade" id="arcav_smart_planner_guide" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Panduan Smart Attendance Planner</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <h6 class="mb-2">Flow yang direkomendasikan</h6>
                                                <ol class="mb-0 ps-3 small">
                                                    <li>Pilih pola kerja yang ingin direncanakan.</li>
                                                    <li>Pilih sasaran draft: semua employee, per departemen, atau manual advanced.</li>
                                                    <li>Atur rule mingguan bila perlu, lalu generate draft.</li>
                                                    <li>Review assignment, diff, dan conflict sebelum publish.</li>
                                                    <li>Pilih publish dominant shift atau roster harian sesuai kebutuhan.</li>
                                                </ol>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <h6 class="mb-2">Mode Planner</h6>
                                                <p class="text-muted small mb-2">Mode planner adalah pilihan manual admin untuk rule kalkulasi AI.</p>
                                                <ul class="mb-0 ps-3 small">
                                                    <li>Bukan auto-detect dari master shift.</li>
                                                    <li>Office Hour untuk pola kerja normal.</li>
                                                    <li>Shifting 24 Jam untuk rotasi dengan fatigue dan transition check.</li>
                                                    <li>Hybrid untuk kombinasi dua pola operasional.</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <h6 class="mb-2">Sasaran Draft</h6>
                                                <p class="text-muted small mb-2">Data selalu diambil dari directory employee tenant aktif. Planner tidak lagi memakai team keyword bebas.</p>
                                                <ul class="mb-0 ps-3 small">
                                                    <li>All: semua employee tenant aktif.</li>
                                                    <li>Per departemen: pakai departemen yang ada pada employee aktif.</li>
                                                    <li>Manual advanced: user ID tertentu yang Anda isi manual.</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <h6 class="mb-2">Planner Defaults</h6>
                                                <p class="text-muted small mb-2">Default ini dipakai hanya saat generate tanpa custom rule di form utama.</p>
                                                <ul class="mb-0 ps-3 small">
                                                    <li>Bisa diubah kapan saja.</li>
                                                    <li>Tidak mengunci hasil planner berikutnya.</li>
                                                    <li>Rule custom di form utama tetap boleh override.</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <h6 class="mb-2">Transition Rules</h6>
                                                <p class="text-muted small mb-2">Rule ini memblok urutan tipe shift tertentu.</p>
                                                <ul class="mb-0 ps-3 small">
                                                    <li>Berbasis tipe shift planner: morning, afternoon, night.</li>
                                                    <li>Bukan berdasarkan nama shift individual di master shift.</li>
                                                    <li>Contoh umum: blok night ke morning.</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Mengerti</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>Daftar Shift &amp; Schedule</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <div class="btn-group me-2" role="group" aria-label="Schedule view mode">
                            <button type="button" class="btn btn-outline-primary active" data-schedule-view-toggle="list">List</button>
                            <button type="button" class="btn btn-outline-primary" data-schedule-view-toggle="calendar">Calendar</button>
                        </div>
                        <div class="me-2">
                            <input type="text" class="form-control" placeholder="Cari nama / jabatan" data-schedule-timing-search>
                        </div>
                        <div class="me-2">
                            <select class="form-select" data-schedule-timing-dept-filter>
                                <option value="">Semua Departemen</option>
                            </select>
                        </div>
                        <div>
                            <select class="form-select" data-schedule-timing-sort>
                                <option value="name_asc">Urut: Nama A-Z</option>
                                <option value="name_desc">Urut: Nama Z-A</option>
                                <option value="start_asc">Urut: Jam mulai paling awal</option>
                                <option value="start_desc">Urut: Jam mulai paling akhir</option>
                            </select>
                        </div>
                        <div class="form-check form-check-md ms-2">
                            <input class="form-check-input" type="checkbox" value="1" id="schedule-ai-draft-only" data-schedule-timing-ai-only>
                            <label class="form-check-label" for="schedule-ai-draft-only">Tampilkan hanya hasil draft planner terakhir</label>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div data-schedule-view-panel="list">
                        <!-- Bulk action toolbar (visible when rows selected) -->
                        <div class="d-none border-bottom px-3 py-2 bg-light d-flex align-items-center gap-2 flex-wrap" data-schedule-timing-bulk-toolbar>
                            <span class="text-muted small me-2"><span data-schedule-timing-bulk-count>0</span> baris dipilih</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-schedule-timing-bulk-apply-shift>Terapkan Shift Master</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-schedule-timing-bulk-reset>Reset ke Otomatis</button>
                            <button type="button" class="btn btn-sm btn-link text-muted ms-auto" data-schedule-timing-bulk-clear>Batal pilih</button>
                        </div>
                        <div class="custom-datatable-filter table-responsive">
                            <table class="table">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="no-sort">
                                            <div class="form-check form-check-md">
                                                <input class="form-check-input" type="checkbox" id="select-all" data-schedule-timing-select-all>
                                            </div>
                                        </th>
                                        <th>Nama</th>
                                        <th>Departemen</th>
                                        <th>Jabatan</th>
                                        <th>Jam Kerja Tersedia</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-schedule-timing-body>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Memuat data shift &amp; schedule...</td>
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
                        <div class="alert alert-light mb-3" data-schedule-calendar-loading>Memuat kalender...</div>
                        <div data-schedule-calendar-wrap>
                            <div data-schedule-calendar></div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2" data-schedule-timing-pagination style="display: none;">
                    <span class="text-muted small" data-schedule-timing-page-info>Menampilkan data...</span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light border" data-schedule-timing-prev>&#8592; Sebelumnya</button>
                        <button type="button" class="btn btn-sm btn-light border" data-schedule-timing-next>Berikutnya &#8594;</button>
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
                    <h5 class="modal-title">Atur Jadwal Kerja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form data-schedule-timing-edit-form>
                    <div class="modal-body">
                        <p class="fw-medium mb-1" data-st-edit-employee>—</p>
                        <p class="text-muted small mb-3">Pilih shift master atau isi jam manual (custom).</p>
                        <input type="hidden" data-st-edit-user-id value="">
                        <div class="mb-3">
                            <label class="form-label">Shift Master</label>
                            <select class="form-select" data-st-edit-shift>
                                <option value="">Custom (manual)</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Jam Mulai</label>
                                <input type="time" class="form-control" data-st-edit-start required value="09:00">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Jam Selesai</label>
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