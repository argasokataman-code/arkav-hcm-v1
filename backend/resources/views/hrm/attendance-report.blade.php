<?php $page = 'attendance-report'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        [data-attendance-report-body]:not([data-hydrated="1"]) {
            display: none;
        }
    </style>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Attendance Report - Rekap Absensi Karyawan</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            HR
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Attendance Report</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="mb-2 me-2 d-flex align-items-center gap-2">
                        <label class="mb-0 small text-muted">Sumber Data</label>
                        <select class="form-select form-select-sm" style="min-width: 170px;" data-attendance-report-source>
                            <option value="live" selected>Data Hari Ini (Realtime)</option>
                            <option value="archive">Data Arsip Bulanan</option>
                        </select>
                    </div>
                    <div class="mb-2 me-2 d-none" data-attendance-report-snapshot-wrap>
                        <input type="number" class="form-control form-control-sm" placeholder="Nomor Arsip (lihat di Laporan)" min="1" data-attendance-report-snapshot-id>
                    </div>
                    <div class="mb-2 me-2">
                        <span class="badge bg-light text-dark" data-attendance-report-source-badge>Sumber: Data Terbaru</span>
                    </div>
                    <div class="mb-2 me-2 w-100">
                        <span class="text-muted small">Data Terbaru menampilkan rekap absensi hari ini. Data Arsip memakai nomor arsip dari halaman Laporan.</span>
                    </div>
                <div class="mb-2 me-2">
                    <a href="{{ url('attendance-admin') }}" class="btn btn-light d-inline-flex align-items-center">
                        <i class="ti ti-arrow-left me-1"></i>Back
                    </a>
                </div>
                <div class="mb-2 me-2">
                    <div class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                            <i class="ti ti-file-export me-1"></i>Export
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1" data-attendance-report-export="xlsx">
                                    <i class="ti ti-file-type-xls me-1"></i>Export Excel (XLSX)
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1" data-attendance-report-export="csv">
                                    <i class="ti ti-file-type-csv me-1"></i>Export CSV
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->
        <div class="row">
            <div class="col-xl-6 d-flex">
                <div class="row flex-fill">
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center overflow-hidden mb-2">
                                    <div class="attendence-icon">
                                        <span><i class="ti ti-calendar text-primary"></i></span>
                                    </div>
                                    <div class="ms-2 overflow-hidden">
                                        <p class="fs-12 fw-normal mb-1 text-truncate">Present</p>
                                        <h4 data-attendance-report-stat-working>—</h4>
                                    </div>
                                </div>
                                <div class="attendance-report-bar mb-2">
                                    <div class="progress" role="progressbar" style="height: 5px;">
                                        <div class="progress-bar bg-secondary" style="width: 0%"></div>
                                    </div>
                                </div>
                                <p class="fs-12 fw-normal text-muted mb-0 text-truncate" data-attendance-report-stat-foot-working>—</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center overflow-hidden mb-2">
                                    <div class="attendence-icon">
                                        <span><i class="ti ti-calendar text-info"></i></span>
                                    </div>
                                    <div class="ms-2 overflow-hidden">
                                        <p class="fs-12 fw-normal mb-1 text-truncate">Absent</p>
                                        <h4 data-attendance-report-stat-leave>—</h4>
                                    </div>
                                </div>
                                <div class="attendance-report-bar mb-2">
                                    <div class="progress" role="progressbar" style="height: 5px;">
                                        <div class="progress-bar bg-secondary" style="width: 0%"></div>
                                    </div>
                                </div>
                                <p class="fs-12 fw-normal text-muted mb-0 text-truncate" data-attendance-report-stat-foot-leave>—</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center overflow-hidden mb-2">
                                    <div class="attendence-icon">
                                        <span><i class="ti ti-calendar text-pink"></i></span>
                                    </div>
                                    <div class="ms-2 overflow-hidden">
                                        <p class="fs-12 fw-normal mb-1 text-truncate">Late login</p>
                                        <h4 data-attendance-report-stat-holiday>—</h4>
                                    </div>
                                </div>
                                <div class="attendance-report-bar mb-2">
                                    <div class="progress" role="progressbar" style="height: 5px;">
                                        <div class="progress-bar bg-secondary" style="width: 0%"></div>
                                    </div>
                                </div>
                                <p class="fs-12 fw-normal text-muted mb-0 text-truncate" data-attendance-report-stat-foot-holiday>—</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center overflow-hidden mb-2">
                                    <div class="attendence-icon">
                                        <span><i class="ti ti-calendar text-warning"></i></span>
                                    </div>
                                    <div class="ms-2 overflow-hidden">
                                        <p class="fs-12 fw-normal mb-1 text-truncate">On roster</p>
                                        <h4 data-attendance-report-stat-halfday>—</h4>
                                    </div>
                                </div>
                                <div class="attendance-report-bar mb-2">
                                    <div class="progress" role="progressbar" style="height: 5px;">
                                        <div class="progress-bar bg-secondary" style="width: 0%"></div>
                                    </div>
                                </div>
                                <p class="fs-12 fw-normal text-muted mb-0 text-truncate" data-attendance-report-stat-foot-halfday>—</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header border-0 pb-0">
                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                            <div class="d-flex align-items-center ">
                                <span class="me-2"><i class="ti ti-chart-line text-danger"></i></span>
                                <h5>Attendance</h5>
                            </div>
                        </div>
                    </div>
                    <div class="card-body py-3 px-3">
                        <div id="attendance-report-chart" style="min-height: 200px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <h5>Employee Attendance</h5>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                    <div class="me-3">
                        <input type="date" class="form-control" data-attendance-report-date title="Work date">
                    </div>
                    <div class="me-3">
                        <button type="button" class="btn btn-outline-primary" data-attendance-report-load>
                            <i class="ti ti-refresh me-1"></i>Load
                        </button>
                    </div>
                    <div class="me-3">
                        <select class="form-select" data-attendance-report-filter-department title="Department filter">
                            <option value="">All departments</option>
                        </select>
                    </div>
                    <div class="me-3">
                        <select class="form-select" data-attendance-report-filter-status title="Status filter">
                            <option value="">All status</option>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="needs_review">Needs Review</option>
                        </select>
                    </div>
                    <div>
                        <select class="form-select" data-attendance-report-sort title="Sort rows">
                            <option value="name_asc">Sort: Name A-Z</option>
                            <option value="name_desc">Sort: Name Z-A</option>
                            <option value="checkin_asc">Sort: Check-in earliest</option>
                            <option value="checkin_desc">Sort: Check-in latest</option>
                            <option value="production_desc">Sort: Production high-low</option>
                            <option value="production_asc">Sort: Production low-high</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="custom-datatable-filter table-responsive">
                    <table class="table" id="attendance-report-table">
                        <thead class="thead-light">
                            <tr>
                                <th>Name</th>
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
                        <tbody data-attendance-report-body>
                            <tr>
                                <td class="text-center text-muted py-4">Loading…</td>
                                <td></td>
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

        <!-- Rekap Absensi Karyawan (Weekly/Monthly/Yearly) -->
        <div class="card" data-attendance-recap-card>
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <h5 class="card-title d-flex align-items-center gap-2 mb-0">
                    <i class="ti ti-report-analytics text-primary"></i>
                    Rekap Absensi Karyawan
                </h5>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="btn-group btn-group-sm" role="group" data-attendance-recap-period>
                        <button type="button" class="btn btn-primary" data-period="weekly">Minggu Ini</button>
                        <button type="button" class="btn btn-outline-primary" data-period="monthly">Bulan Ini</button>
                        <button type="button" class="btn btn-outline-primary" data-period="yearly">Tahun Ini</button>
                    </div>
                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-attendance-recap-load title="Muat ulang">
                        <i class="ti ti-refresh"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Loading skeleton -->
                <div class="d-none" data-attendance-recap-loading>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="card bg-light mb-0"><div class="card-body py-4 placeholder-glow"><span class="placeholder col-12 h-100"></span></div></div></div>
                        <div class="col-md-3"><div class="card bg-light mb-0"><div class="card-body py-4 placeholder-glow"><span class="placeholder col-12"></span></div></div></div>
                        <div class="col-md-3"><div class="card bg-light mb-0"><div class="card-body py-4 placeholder-glow"><span class="placeholder col-12"></span></div></div></div>
                        <div class="col-md-3"><div class="card bg-light mb-0"><div class="card-body py-4 placeholder-glow"><span class="placeholder col-12"></span></div></div></div>
                    </div>
                    <div class="placeholder-glow"><span class="placeholder col-12" style="height:200px"></span></div>
                </div>
                <!-- Error -->
                <div class="alert alert-warning d-none mb-0" data-attendance-recap-error>
                    <div class="d-flex align-items-center gap-2">
                        <i class="ti ti-alert-triangle fs-5"></i>
                        <span data-attendance-recap-error-msg>Gagal memuat data.</span>
                        <button type="button" class="btn btn-sm btn-outline-warning ms-auto" data-attendance-recap-retry>
                            <i class="ti ti-refresh me-1"></i>Coba Lagi
                        </button>
                    </div>
                </div>
                <!-- Empty -->
                <div class="text-center py-5 d-none" data-attendance-recap-empty>
                    <i class="ti ti-calendar-off fs-1 text-muted d-block mb-2"></i>
                    <p class="text-muted mb-0">Belum ada data absensi untuk periode ini.</p>
                </div>
                <!-- Summary Cards -->
                <div class="d-none" data-attendance-recap-summary>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 bg-gradient-primary-light mb-0">
                                <div class="card-body d-flex align-items-center gap-3 py-3">
                                    <div class="avatar avatar-lg bg-white rounded shadow-sm">
                                        <i class="ti ti-users fs-20 text-primary"></i>
                                    </div>
                                    <div>
                                        <p class="text-muted fs-12 mb-0">Total Karyawan</p>
                                        <h4 class="mb-0 fw-bold" data-recap-total-employees>0</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 bg-gradient-success-light mb-0">
                                <div class="card-body d-flex align-items-center gap-3 py-3">
                                    <div class="avatar avatar-lg bg-white rounded shadow-sm">
                                        <i class="ti ti-user-check fs-20 text-success"></i>
                                    </div>
                                    <div>
                                        <p class="text-muted fs-12 mb-0">Hadir</p>
                                        <h4 class="mb-0 fw-bold text-success" data-recap-total-present>0</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 bg-gradient-danger-light mb-0">
                                <div class="card-body d-flex align-items-center gap-3 py-3">
                                    <div class="avatar avatar-lg bg-white rounded shadow-sm">
                                        <i class="ti ti-user-off fs-20 text-danger"></i>
                                    </div>
                                    <div>
                                        <p class="text-muted fs-12 mb-0">Bolos</p>
                                        <h4 class="mb-0 fw-bold text-danger" data-recap-total-absent>0</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 bg-gradient-warning-light mb-0">
                                <div class="card-body d-flex align-items-center gap-3 py-3">
                                    <div class="avatar avatar-lg bg-white rounded shadow-sm">
                                        <i class="ti ti-percentage fs-20 text-warning"></i>
                                    </div>
                                    <div>
                                        <p class="text-muted fs-12 mb-0">Kehadiran</p>
                                        <h4 class="mb-0 fw-bold text-warning" data-recap-attendance-rate>0%</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Table -->
                <div class="table-responsive d-none" data-attendance-recap-table>
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:160px">Karyawan</th>
                                <th style="width:100px" class="text-center">Bolos</th>
                                <th style="width:100px" class="text-center">Hadir</th>
                                <th style="width:90px" class="text-center">Total</th>
                                <th style="min-width:160px">Kehadiran</th>
                                <th style="min-width:200px">Tanggal Bolos</th>
                            </tr>
                        </thead>
                        <tbody data-attendance-recap-tbody>
                            <!-- filled by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- /Rekap Absensi Karyawan -->

    </div>
    <!-- /content -->
</div>
<!-- /Page Wrapper -->

@endsection
