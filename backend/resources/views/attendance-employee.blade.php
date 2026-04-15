<?php $page = 'attendance-employee'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        [data-attendance-me-history-body]:not([data-hydrated="1"]) {
            display: none;
        }
        .arcav-attendance-page .arcav-punch-card .card-body {
            padding: 1.25rem;
        }
        .arcav-attendance-page .arcav-punch-card {
            border: 1px solid #e6ecf4;
            background: linear-gradient(180deg, #f9fbff 0%, #ffffff 100%);
        }
        .arcav-attendance-page .arcav-profile-head {
            border: 1px solid #e8eef7;
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 0.875rem;
            margin-bottom: 1rem;
        }
        .arcav-attendance-page .arcav-profile-meta-name {
            font-size: 0.9375rem;
            font-weight: 700;
            color: #1f2a37;
            margin: 0;
        }
        .arcav-attendance-page .arcav-profile-meta-team {
            font-size: 0.8125rem;
            color: #64748b;
            margin: 0.125rem 0 0;
        }
        .arcav-attendance-page .arcav-profile-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #dbe7ff;
            background: #eef4ff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .arcav-attendance-page .arcav-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }
        .arcav-attendance-page .arcav-profile-avatar.has-image img {
            display: block;
        }
        .arcav-attendance-page .arcav-profile-avatar.has-image [data-attendance-me-avatar-initial] {
            display: none;
        }
        .arcav-attendance-page .arcav-punch-section-title {
            font-size: 0.6875rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--gray-600, #6c757d);
            font-weight: 600;
            margin-bottom: 0.35rem;
        }
        .arcav-attendance-page .arcav-stat-card {
            border: 1px solid var(--border-color, #e8e8e8);
            height: 100%;
        }
        .arcav-attendance-page .arcav-stat-card .card-body {
            padding: 1.125rem 1.25rem;
        }
        .arcav-attendance-page .arcav-stat-card .stat-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .arcav-attendance-page .arcav-stat-card .stat-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gray-700, #495057);
            line-height: 1.35;
            margin: 0;
            max-width: 70%;
        }
        .arcav-attendance-page .arcav-stat-card .stat-value-row {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 0.25rem 0.5rem;
            margin-bottom: 0.5rem;
        }
        .arcav-attendance-page .arcav-stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
            color: var(--gray-900, #212529);
        }
        .arcav-attendance-page .arcav-stat-card .stat-target {
            font-size: 1rem;
            font-weight: 500;
            color: var(--gray-500, #8a9099);
        }
        .arcav-attendance-page .arcav-stat-card .stat-foot {
            font-size: 0.75rem;
            color: var(--gray-600, #6c757d);
            margin: 0;
            padding-top: 0.5rem;
            border-top: 1px dashed var(--border-color, #e8e8e8);
        }
        .arcav-attendance-page .arcav-summary-card .summary-item {
            background: var(--light, #f8f9fa);
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            height: 100%;
            border: 1px solid var(--border-color, #e8e8e8);
        }
        .arcav-attendance-page .arcav-summary-card .summary-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--gray-600, #6c757d);
            margin-bottom: 0.35rem;
        }
        .arcav-attendance-page .arcav-summary-card .summary-value {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.3;
        }
        #arcav-attendance-punch-map {
            min-height: 180px;
            height: 180px;
        }
        .arcav-attendance-page .arcav-gps-debug {
            border: 1px dashed var(--border-color, #d9d9d9);
            border-radius: 0.5rem;
            background: #fcfcfc;
            padding: 0.75rem;
        }
        /* Selfie camera modal styles */
        .arcav-selfie-camera-modal .modal-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e8eef7;
        }
        .arcav-selfie-camera-video {
            width: 100%;
            border-radius: 0.5rem;
            background: #000;
            display: block;
            margin-bottom: 1rem;
        }
        .arcav-selfie-preview {
            width: 100%;
            max-height: 300px;
            border-radius: 0.5rem;
            object-fit: cover;
            margin-bottom: 1rem;
            display: none;
            border: 1px solid #e8eef7;
        }
        .arcav-selfie-preview.show {
            display: block;
        }
        .arcav-selfie-control-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .arcav-selfie-encrypt-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        .arcav-selfie-encrypt-badge i {
            font-size: 1rem;
        }
        [data-selfie-camera-video]:not([data-recording="1"]) {
            display: block;
        }
        [data-selfie-preview]:not([data-show="1"]) {
            display: none;
        }
    </style>

    <!-- Page Wrapper -->
    <div class="page-wrapper arcav-attendance-page">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Absensi karyawan</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Employee
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Absensi</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="me-2 mb-2">
                        <div class="d-flex align-items-center border bg-white rounded p-1 me-2 icon-list">
                            <a href="{{url('attendance-employee')}}" class="btn btn-icon btn-sm active bg-primary text-white me-1"><i class="ti ti-brand-days-counter"></i></a>
                            <a href="{{url('attendance-admin')}}" class="btn btn-icon btn-sm"><i class="ti ti-calendar-event"></i></a>
                        </div>
                    </div>
                    <div class="me-2 mb-2">
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                <i class="ti ti-file-export me-1"></i>Export
                            </a>
                            <ul class="dropdown-menu  dropdown-menu-end p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1" data-attendance-me-export="csv"><i class="ti ti-file-type-xls me-1"></i>Export CSV</a>
                                </li>
                                <li>
                                    <span class="dropdown-item rounded-1 text-muted">CSV format only</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-2">
                        <a href="{{ url('attendance-report') }}" class="btn btn-primary d-flex align-items-center">
                            <i class="ti ti-file-analytics me-2"></i>Report
                        </a>
                    </div>
                    <div class="ms-2 head-icons">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <div class="row g-3">
                <div class="col-xl-3 col-lg-4 d-flex">
                    <div class="card flex-fill arcav-punch-card border shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="arcav-profile-head">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="arcav-profile-avatar" data-attendance-me-avatar>
                                        <img alt="Employee photo" data-attendance-me-avatar-image>
                                        <span class="avatar-title rounded-circle text-primary fs-4 fw-semibold" data-attendance-me-avatar-initial>?</span>
                                    </div>
                                    <div class="text-start min-w-0">
                                        <p class="arcav-profile-meta-name" data-attendance-me-user-name>Loading…</p>
                                        <p class="arcav-profile-meta-team" data-attendance-me-team>—</p>
                                        <p class="text-muted small mb-0 mt-1" data-attendance-me-now>—</p>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center border-bottom pb-3 mb-3">
                                <p class="arcav-punch-section-title mb-1">Status hari ini</p>
                                <p class="fw-semibold text-gray-900 mb-0" data-attendance-me-greeting>Loading…</p>
                            </div>
                            <div class="attendance-circle-progress mx-auto mb-3 flex-shrink-0" data-value="0">
                                <span class="progress-left">
                                    <span class="progress-bar border-success"></span>
                                </span>
                                <span class="progress-right">
                                    <span class="progress-bar border-success"></span>
                                </span>
                                <div class="avatar avatar-xxl avatar-rounded bg-primary-subtle d-inline-flex align-items-center justify-content-center">
                                    <i class="ti ti-fingerprint text-primary fs-2"></i>
                                </div>
                            </div>
                            <div class="text-center mb-3">
                                <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2 fw-medium" data-attendance-me-production-badge>Produktivitas: —</span>
                            </div>
                            <div class="rounded-3 bg-light p-3 mb-3 text-center">
                                <p class="arcav-punch-section-title text-start mb-2">Absensi</p>
                                <div class="fw-medium text-gray-700 d-flex align-items-center justify-content-center gap-2 mb-0" data-attendance-me-punch-line>
                                    <i class="ti ti-fingerprint text-primary fs-18"></i>
                                    <span>—</span>
                                </div>
                                <div class="d-none mt-2" data-attendance-me-break-indicator>
                                    <span class="badge bg-warning text-dark">Istirahat</span>
                                    <div class="small text-muted mt-1">Durasi: <span data-attendance-me-break-duration>00:00</span></div>
                                </div>
                            </div>
                            <div class="alert alert-warning py-2 px-3 text-start small mb-3 d-none" data-attendance-me-alert></div>
                            <div class="mb-3 flex-grow-1 d-flex flex-column">
                                <p class="arcav-punch-section-title">Lokasi (wajib saat Punch)</p>
                                <div id="arcav-attendance-punch-map" class="rounded-3 border bg-white shadow-sm flex-grow-1 w-100"></div>
                                <p class="text-muted small mt-2 mb-0">
                                    Peta Leaflet + OpenStreetMap. Jika GPS perangkat ditolak browser, klik titik di peta sebagai lokasi manual.
                                </p>
                                <p class="small mb-0 mt-1 text-primary" data-attendance-me-map-hint></p>
                            </div>
                            <div class="d-grid gap-2 mt-auto">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-attendance-gps-debug-btn>
                                    Cek status GPS
                                </button>
                                <div class="arcav-gps-debug d-none text-start" data-attendance-gps-debug-box>
                                    <p class="small mb-1"><strong>Secure Context:</strong> <span data-gps-debug-secure>—</span></p>
                                    <p class="small mb-1"><strong>Permission:</strong> <span data-gps-debug-permission>—</span></p>
                                    <p class="small mb-1"><strong>Coords:</strong> <span data-gps-debug-coords>—</span></p>
                                    <p class="small mb-0"><strong>Status:</strong> <span data-gps-debug-status>Belum dicek.</span></p>
                                </div>
                                <button type="button" class="btn btn-outline-warning d-none" data-attendance-me-request-correction>
                                    Ajukan koreksi
                                </button>
                                <button type="button" class="btn btn-outline-success" data-attendance-me-selfie-btn>
                                    <i class="ti ti-camera me-1"></i> Ambil Selfie
                                </button>
                                <button type="button" class="btn btn-outline-primary" data-attendance-me-break-btn disabled>Mulai istirahat</button>
                                <button type="button" class="btn btn-dark" data-attendance-me-punch-btn>Punch In</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-9 col-lg-8">
                    <div class="row g-3">
                        <div class="col-xxl-3 col-md-6">
                            <div class="card arcav-stat-card shadow-sm">
                                <div class="card-body">
                                    <div class="stat-head">
                                        <p class="stat-label">Jam kerja hari ini</p>
                                        <span class="avatar avatar-sm bg-primary flex-shrink-0"><i class="ti ti-clock-stop"></i></span>
                                    </div>
                                    <div class="stat-value-row">
                                        <span class="stat-value" data-attendance-stat-today-hours>—</span>
                                        <span class="stat-target">/ <span data-attendance-stat-today-target>8</span> jam</span>
                                    </div>
                                    <p class="stat-foot mb-0"><span data-attendance-me-stat-foot-today>—</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card arcav-stat-card shadow-sm">
                                <div class="card-body">
                                    <div class="stat-head">
                                        <p class="stat-label">Jam kerja minggu ini</p>
                                        <span class="avatar avatar-sm bg-dark flex-shrink-0"><i class="ti ti-clock-up"></i></span>
                                    </div>
                                    <div class="stat-value-row">
                                        <span class="stat-value" data-attendance-stat-week-hours>—</span>
                                        <span class="stat-target">/ <span data-attendance-stat-week-target>40</span> jam</span>
                                    </div>
                                    <p class="stat-foot mb-0"><span data-attendance-me-stat-foot-week>—</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card arcav-stat-card shadow-sm">
                                <div class="card-body">
                                    <div class="stat-head">
                                        <p class="stat-label">Jam kerja bulan ini</p>
                                        <span class="avatar avatar-sm bg-info flex-shrink-0"><i class="ti ti-calendar-up"></i></span>
                                    </div>
                                    <div class="stat-value-row">
                                        <span class="stat-value" data-attendance-stat-month-hours>—</span>
                                        <span class="stat-target">/ <span data-attendance-stat-month-target>—</span> jam</span>
                                    </div>
                                    <p class="stat-foot mb-0"><span data-attendance-me-stat-foot-month>—</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card arcav-stat-card shadow-sm">
                                <div class="card-body">
                                    <div class="stat-head">
                                        <p class="stat-label">Lembur bulan ini</p>
                                        <span class="avatar avatar-sm bg-pink flex-shrink-0"><i class="ti ti-calendar-star"></i></span>
                                    </div>
                                    <div class="stat-value-row">
                                        <span class="stat-value" data-attendance-stat-ot-hours>—</span>
                                        <span class="stat-target">/ <span data-attendance-stat-ot-target>—</span> jam</span>
                                    </div>
                                    <p class="stat-foot mb-0"><span data-attendance-me-stat-foot-ot>—</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card arcav-summary-card border shadow-sm">
                                <div class="card-header bg-transparent border-bottom py-3">
                                    <h5 class="mb-0 fs-16 fw-semibold">Ringkasan hari ini</h5>
                                    <p class="text-muted small mb-0 mt-1">Diperbarui otomatis dari punch in / out Anda hari ini.</p>
                                </div>
                                <div class="card-body pt-3">
                                    <div class="row g-3">
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="summary-item">
                                                <p class="summary-label mb-0">Total jam kerja</p>
                                                <p class="summary-value text-dark" data-attendance-me-summary-total>—</p>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="summary-item">
                                                <p class="summary-label mb-0">Produktif</p>
                                                <p class="summary-value text-success" data-attendance-me-summary-productive>—</p>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="summary-item">
                                                <p class="summary-label mb-0">Istirahat</p>
                                                <p class="summary-value text-warning" data-attendance-me-summary-break>—</p>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="summary-item">
                                                <p class="summary-label mb-0">Lembur (hari ini)</p>
                                                <p class="summary-value text-info" data-attendance-me-summary-ot>—</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3 border shadow-sm">
                <div class="card-header bg-transparent d-flex align-items-center justify-content-between flex-wrap row-gap-2 py-3">
                    <div>
                        <h5 class="mb-0 fs-16 fw-semibold">Riwayat absensi</h5>
                        <p class="text-muted small mb-0">30 hari terakhir</p>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table" id="attendance-me-history-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Check In</th>
                                    <th>Check In Location</th>
                                    <th>Status</th>
                                    <th>Check Out</th>
                                    <th>Check Out Location</th>
                                    <th>Break</th>
                                    <th>Late</th>
                                    <th>Overtime</th>
                                    <th>Production Hours</th>
                                </tr>
                            </thead>
                            <tbody data-attendance-me-history-body>
                                <tr>
                                    <td class="text-center text-muted py-4">Loading history...</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>


    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

    <!-- Selfie Camera Modal -->
    <div class="modal fade arcav-selfie-camera-modal" id="arcav_attendance_selfie_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ti ti-camera me-2"></i>Ambil Selfie Absensi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small" role="alert">
                        <i class="ti ti-info-circle me-1"></i>
                        Pastikan wajah Anda terlihat jelas dalam frame kamera.
                    </div>
                    
                    <!-- Camera stream -->
                    <video data-selfie-camera-video class="arcav-selfie-camera-video" playsinline></video>
                    
                    <!-- Preview after capture -->
                    <canvas data-selfie-preview class="arcav-selfie-preview" width="400" height="300"></canvas>
                    
                    <!-- Controls -->
                    <div class="arcav-selfie-control-group">
                        <button type="button" class="btn btn-primary flex-grow-1" data-selfie-capture-btn>
                            <i class="ti ti-circle me-1"></i>Ambil Foto
                        </button>
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 d-none" data-selfie-retake-btn>
                            <i class="ti ti-refresh me-1"></i>Ulangi
                        </button>
                    </div>
                    
                    <!-- Encryption indicator -->
                    <div class="arcav-selfie-encrypt-badge">
                        <i class="ti ti-lock"></i>
                        <span>Foto akan dienkripsi sebelum dikirim</span>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success d-none" data-selfie-submit-btn>
                        <i class="ti ti-check me-1"></i>Simpan Selfie
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="arcav_attendance_correction_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Correction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Reason</label>
                    <textarea class="form-control" rows="4" data-attendance-correction-reason placeholder="Jelaskan alasan koreksi absensi"></textarea>
                    <div class="form-text">Minimal 5 karakter.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-attendance-correction-submit>Send Request</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Attendance Selfie Camera Handler
    document.addEventListener('DOMContentLoaded', function() {
        const selfieModal = document.getElementById('arcav_attendance_selfie_modal');
        const videoEl = document.querySelector('[data-selfie-camera-video]');
        const canvasEl = document.querySelector('[data-selfie-preview]');
        const captureBtn = document.querySelector('[data-selfie-capture-btn]');
        const retakeBtn = document.querySelector('[data-selfie-retake-btn]');
        const submitBtn = document.querySelector('[data-selfie-submit-btn]');
        const openSelfieBtn = document.querySelector('[data-attendance-me-selfie-btn]');
        let mediaStream = null;
        let capturedImageData = null;

        // Verify elements exist
        console.log('Selfie Elements:', {
            modal: !!selfieModal,
            video: !!videoEl,
            canvas: !!canvasEl,
            captureBtn: !!captureBtn,
            retakeBtn: !!retakeBtn,
            submitBtn: !!submitBtn,
            openBtn: !!openSelfieBtn,
        });

        // Open modal and start camera
        if (openSelfieBtn && selfieModal) {
            openSelfieBtn.addEventListener('click', function() {
                console.log('Ambil Selfie clicked');
                const modal = new bootstrap.Modal(selfieModal);
                modal.show();
                // Wait for modal animation (300ms default)
                setTimeout(startCamera, 350);
            });
        }

        // Listen for modal shown event (alternative trigger)
        if (selfieModal) {
            selfieModal.addEventListener('shown.bs.modal', function() {
                console.log('Modal shown event fired');
                if (!mediaStream) {
                    startCamera();
                }
            });
        }

        // Start camera stream
        async function startCamera() {
            try {
                console.log('Starting camera...');
                
                // Check if browser supports getUserMedia
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Browser Anda tidak mendukung akses kamera. Gunakan Chrome, Firefox, atau Safari terbaru.');
                    return;
                }

                mediaStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 400 },
                        height: { ideal: 300 }
                    },
                    audio: false
                });
                
                if (videoEl) {
                    videoEl.srcObject = mediaStream;
                    videoEl.onloadedmetadata = function() {
                        videoEl.play().catch(e => console.error('Play failed:', e));
                        console.log('Camera started successfully');
                    };
                } else {
                    console.error('Video element not found');
                }
            } catch (error) {
                console.error('Camera access denied:', error);
                alert('Akses kamera ditolak:\n' + error.message + '\n\nPastikan Anda mengizinkan akses kamera di browser.');
            }
        }

        // Capture photo from video stream
        if (captureBtn) {
            captureBtn.addEventListener('click', function() {
                try {
                    console.log('Capturing photo...');
                    if (!videoEl) {
                        alert('Video element tidak ditemukan');
                        return;
                    }
                    
                    const ctx = canvasEl.getContext('2d');
                    if (!ctx) {
                        alert('Canvas context tidak tersedia');
                        return;
                    }
                    
                    // Mirror the video on canvas (optional)
                    ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
                    capturedImageData = canvasEl.toDataURL('image/jpeg', 0.9);
                    console.log('Photo captured, data length:', capturedImageData.length);
                    
                    // Show preview, hide camera
                    videoEl.classList.add('d-none');
                    canvasEl.classList.add('show');
                    captureBtn.classList.add('d-none');
                    retakeBtn.classList.remove('d-none');
                    submitBtn.classList.remove('d-none');
                } catch (error) {
                    console.error('Capture error:', error);
                    alert('Gagal mengambil foto: ' + error.message);
                }
            });
        }

        // Retake photo
        if (retakeBtn) {
            retakeBtn.addEventListener('click', function() {
                console.log('Retaking photo...');
                videoEl.classList.remove('d-none');
                canvasEl.classList.remove('show');
                captureBtn.classList.remove('d-none');
                retakeBtn.classList.add('d-none');
                submitBtn.classList.add('d-none');
                capturedImageData = null;
            });
        }

        // Submit selfie to API
        if (submitBtn) {
            submitBtn.addEventListener('click', async function() {
                try {
                    console.log('Submitting selfie...');
                    
                    if (!capturedImageData) {
                        alert('Tidak ada foto untuk dikirim');
                        return;
                    }
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengirim...';
                    
                    const response = await fetch('/api/v1/attendance/me/selfie', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + (document.querySelector('[data-auth-token]')?.dataset.authToken || ''),
                            'X-Company-Id': document.querySelector('[data-company-id]')?.dataset.companyId || '',
                        },
                        body: JSON.stringify({
                            selfie_base64: capturedImageData
                        })
                    });
                    
                    console.log('Response status:', response.status);
                    const result = await response.json();
                    console.log('Response data:', result);
                    
                    if (response.ok) {
                        // Close modal
                        bootstrap.Modal.getInstance(selfieModal)?.hide();
                        
                        // Show success
                        showToast('success', 'Selfie Tersimpan', 'Foto Anda telah dienkripsi dan disimpan.');
                        capturedImageData = null;
                        
                        // Reset UI
                        if (mediaStream) {
                            mediaStream.getTracks().forEach(track => track.stop());
                            mediaStream = null;
                        }
                    } else {
                        alert('Gagal menyimpan selfie:\n' + (result.message || result.error || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Submit error:', error);
                    alert('Error mengupload selfie: ' + error.message);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Simpan Selfie';
                }
            });
        }

        // Stop camera when modal closes
        if (selfieModal) {
            selfieModal.addEventListener('hidden.bs.modal', function() {
                console.log('Modal hidden, stopping camera');
                if (mediaStream) {
                    mediaStream.getTracks().forEach(track => track.stop());
                    mediaStream = null;
                }
                if (videoEl) {
                    videoEl.srcObject = null;
                }
                capturedImageData = null;
            });
        }

        // Toast notification helper
        function showToast(type, title, message) {
            const toastHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <strong>${title}</strong> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            const container = document.querySelector('[data-toast-container]') || document.body;
            const toast = document.createElement('div');
            toast.innerHTML = toastHtml;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }
    });
    </script>

@endsection