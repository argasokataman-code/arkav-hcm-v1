<div class="admin-home-dashboard">
    <style>
        .admin-home-dashboard {
            --ahd-bg: #f7f9fc;
            --ahd-surface: #ffffff;
            --ahd-text: #0f172a;
            --ahd-muted: #64748b;
            --ahd-border: #e2e8f0;
            --ahd-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            --ahd-shadow-hover: 0 14px 30px rgba(15, 23, 42, 0.1);
            --ahd-accent: #0f766e;
        }

        .admin-home-dashboard .card {
            border: 1px solid var(--ahd-border);
            border-radius: 16px;
            box-shadow: var(--ahd-shadow);
            background: var(--ahd-surface);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .admin-home-dashboard .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--ahd-shadow-hover);
        }

        .admin-home-dashboard .card-header {
            border-bottom: 1px solid var(--ahd-border);
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px 16px 0 0;
        }

        .admin-home-dashboard .card-header h5 {
            color: var(--ahd-text);
            letter-spacing: 0.2px;
        }

        .admin-home-dashboard .metric-card .card-body {
            background: linear-gradient(145deg, #ffffff 0%, #f8fbff 100%);
            border-radius: 16px;
        }

        .admin-home-dashboard .metric-card h3,
        .admin-home-dashboard .metric-card h6,
        .admin-home-dashboard .metric-card strong,
        .admin-home-dashboard .metric-card h5 {
            color: var(--ahd-text);
        }

        .admin-home-dashboard .metric-card p,
        .admin-home-dashboard .metric-card .fs-12,
        .admin-home-dashboard .metric-card .text-muted {
            color: var(--ahd-muted) !important;
        }

        .admin-home-dashboard .quick-actions-grid {
            display: grid;
            gap: 10px;
        }

        .admin-home-dashboard .quick-action-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid var(--ahd-border);
            border-radius: 12px;
            padding: 11px 14px;
            text-decoration: none;
            color: var(--ahd-text);
            background: #fff;
            transition: all 0.2s ease;
        }

        .admin-home-dashboard .quick-action-item:hover {
            transform: translateY(-1px);
            border-color: #cbd5e1;
            box-shadow: 0 10px 18px rgba(15, 23, 42, 0.08);
            color: var(--ahd-text);
        }

        .admin-home-dashboard .quick-action-item .qa-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .admin-home-dashboard .quick-action-item .qa-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .admin-home-dashboard .quick-action-item .qa-title {
            font-weight: 600;
            line-height: 1.2;
            color: var(--ahd-text);
        }

        .admin-home-dashboard .quick-action-item .qa-subtitle {
            font-size: 12px;
            color: var(--ahd-muted);
            margin-top: 1px;
            line-height: 1.2;
        }

        .admin-home-dashboard .quick-action-item .qa-arrow {
            color: #94a3b8;
            font-size: 16px;
        }

        .admin-home-dashboard .qa-tone-1 .qa-icon {
            background: #fff7ed;
            color: #ea580c;
        }

        .admin-home-dashboard .qa-tone-2 .qa-icon {
            background: #f0f9ff;
            color: #0284c7;
        }

        .admin-home-dashboard .qa-tone-3 .qa-icon {
            background: #ecfdf5;
            color: #16a34a;
        }

        .admin-home-dashboard .qa-tone-4 .qa-icon {
            background: #eff6ff;
            color: #2563eb;
        }

        .admin-home-dashboard .qa-tone-5 .qa-icon {
            background: #fefce8;
            color: #ca8a04;
        }

        .admin-home-dashboard .qa-tone-6 .qa-icon {
            background: #f8fafc;
            color: #334155;
        }

        @media (max-width: 767.98px) {
            .admin-home-dashboard .quick-action-item {
                padding: 10px 12px;
            }
        }
    </style>

<div class="row" data-admin-home-dashboard>
    <div class="col-xl-2 col-md-4 d-flex">
        <div class="card flex-fill metric-card">
            <div class="card-body">
                <span class="avatar rounded-circle bg-primary mb-2"><i class="ti ti-users fs-16"></i></span>
                <h6 class="fs-13 fw-medium text-default mb-1">Total Karyawan Aktif</h6>
                <h3 class="mb-1" data-exec-active>0</h3>
                <p class="fs-12 text-muted mb-0">Headcount aktif</p>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 d-flex">
        <div class="card flex-fill metric-card">
            <div class="card-body">
                <span class="avatar rounded-circle bg-warning mb-2"><i class="ti ti-hourglass-empty fs-16"></i></span>
                <h6 class="fs-13 fw-medium text-default mb-1">Probation</h6>
                <h3 class="mb-1" data-exec-probation>0</h3>
                <p class="fs-12 text-muted mb-0">Dalam masa probation</p>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 d-flex">
        <div class="card flex-fill metric-card">
            <div class="card-body">
                <span class="avatar rounded-circle bg-danger mb-2"><i class="ti ti-calendar-exclamation fs-16"></i></span>
                <h6 class="fs-13 fw-medium text-default mb-1">PKWT 30 Hari</h6>
                <h3 class="mb-1" data-exec-pkwt-due>0</h3>
                <p class="fs-12 text-muted mb-0">Segera berakhir</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 d-flex">
        <div class="card flex-fill metric-card">
            <div class="card-body">
                <span class="avatar rounded-circle bg-success mb-2"><i class="ti ti-calendar-check fs-16"></i></span>
                <h6 class="fs-13 fw-medium text-default mb-2">Kehadiran Hari Ini</h6>
                <div class="d-flex justify-content-between mb-1"><span class="fs-12">Hadir</span><strong data-exec-att-present>0</strong></div>
                <div class="d-flex justify-content-between mb-1"><span class="fs-12">Telat</span><strong data-exec-att-late>0</strong></div>
                <div class="d-flex justify-content-between"><span class="fs-12">Tidak Check-in</span><strong data-exec-att-missing>0</strong></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 d-flex">
        <div class="card flex-fill metric-card">
            <div class="card-body">
                <span class="avatar rounded-circle bg-info mb-2"><i class="ti ti-beach fs-16"></i></span>
                <h6 class="fs-13 fw-medium text-default mb-2">Cuti Menunggu Approval</h6>
                <h3 class="mb-1" data-exec-leave-pending>0</h3>
                <div class="d-flex justify-content-between fs-12 mt-2">
                    <span>Draft</span>
                    <strong data-exec-payroll-draft>0</strong>
                </div>
                <div class="d-flex justify-content-between fs-12">
                    <span>Paid</span>
                    <strong data-exec-payroll-paid>0</strong>
                </div>
                <div class="d-flex justify-content-between fs-12">
                    <span>Unpaid</span>
                    <strong data-exec-payroll-unpaid>0</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xxl-5 d-flex">
        <div class="card flex-fill">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Payroll Command Center</h5>
                <a href="{{ url('payroll-run') }}" class="btn btn-light btn-sm">Open Payroll</a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <p class="fs-12 text-muted mb-1">Status Periode Aktif</p>
                        <h6 class="mb-0 text-capitalize" data-payroll-period-status>—</h6>
                    </div>
                    <div class="col-sm-6">
                        <p class="fs-12 text-muted mb-1">Status Run Terbaru</p>
                        <h6 class="mb-0 text-capitalize" data-payroll-run-status>—</h6>
                    </div>
                    <div class="col-sm-6">
                        <p class="fs-12 text-muted mb-1">Status Pembayaran</p>
                        <h6 class="mb-0 text-capitalize" data-payroll-run-payment-status>—</h6>
                    </div>
                    <div class="col-sm-6">
                        <p class="fs-12 text-muted mb-1">Line Payroll Bulan Ini</p>
                        <h6 class="mb-0" data-payroll-line-count>0</h6>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xxl-4 d-flex">
        <div class="card flex-fill">
            <div class="card-header">
                <h5 class="mb-0">Approval Inbox</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span class="fs-13">Pending Leave Request</span><strong data-approval-leave>0</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="fs-13">Pending Overtime Request</span><strong data-approval-overtime>0</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="fs-13">Pending Resign/Termination</span><strong data-approval-resign-termination>0</strong></div>
                <div class="d-flex justify-content-between"><span class="fs-13">Pending Promotion Review</span><strong data-approval-promotion>0</strong></div>
            </div>
        </div>
    </div>

    <div class="col-xxl-3 d-flex">
        <div class="card flex-fill">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body quick-actions-grid">
                <a href="{{ url('employees') }}" class="quick-action-item qa-tone-1">
                    <span class="qa-left">
                        <span class="qa-icon"><i class="ti ti-users"></i></span>
                        <span>
                            <span class="d-block qa-title">Employees Directory</span>
                            <span class="d-block qa-subtitle">Master data karyawan</span>
                        </span>
                    </span>
                    <i class="ti ti-chevron-right qa-arrow"></i>
                </a>
                <a href="{{ url('employee-details') }}" class="quick-action-item qa-tone-2">
                    <span class="qa-left">
                        <span class="qa-icon"><i class="ti ti-id-badge-2"></i></span>
                        <span>
                            <span class="d-block qa-title">Employee Detail</span>
                            <span class="d-block qa-subtitle">Profil dan riwayat personil</span>
                        </span>
                    </span>
                    <i class="ti ti-chevron-right qa-arrow"></i>
                </a>
                <a href="{{ url('payroll-run') }}" class="quick-action-item qa-tone-3">
                    <span class="qa-left">
                        <span class="qa-icon"><i class="ti ti-cash"></i></span>
                        <span>
                            <span class="d-block qa-title">Process Monthly Payroll</span>
                            <span class="d-block qa-subtitle">Jalankan payroll bulanan</span>
                        </span>
                    </span>
                    <i class="ti ti-chevron-right qa-arrow"></i>
                </a>
                <a href="{{ url('payroll-run-history') }}" class="quick-action-item qa-tone-4">
                    <span class="qa-left">
                        <span class="qa-icon"><i class="ti ti-history"></i></span>
                        <span>
                            <span class="d-block qa-title">Payroll History</span>
                            <span class="d-block qa-subtitle">Audit run periode sebelumnya</span>
                        </span>
                    </span>
                    <i class="ti ti-chevron-right qa-arrow"></i>
                </a>
                <a href="{{ url('leaves') }}" class="quick-action-item qa-tone-5">
                    <span class="qa-left">
                        <span class="qa-icon"><i class="ti ti-calendar-event"></i></span>
                        <span>
                            <span class="d-block qa-title">Leave Requests</span>
                            <span class="d-block qa-subtitle">Approval dan monitoring cuti</span>
                        </span>
                    </span>
                    <i class="ti ti-chevron-right qa-arrow"></i>
                </a>
                <a href="{{ url('overtime') }}" class="quick-action-item qa-tone-6">
                    <span class="qa-left">
                        <span class="qa-icon"><i class="ti ti-clock-hour-4"></i></span>
                        <span>
                            <span class="d-block qa-title">Overtime Requests</span>
                            <span class="d-block qa-subtitle">Kontrol lembur dan persetujuan</span>
                        </span>
                    </span>
                    <i class="ti ti-chevron-right qa-arrow"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xxl-12 d-flex">
        <div class="card flex-fill">
            <div class="card-header">
                <h5 class="mb-0">Data Quality & Workforce Signals</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2 col-6">
                        <p class="fs-12 text-muted mb-1">Joiner Bulan Ini</p>
                        <h5 class="mb-0" data-signal-joiner>0</h5>
                    </div>
                    <div class="col-md-2 col-6">
                        <p class="fs-12 text-muted mb-1">Resignation Bulan Ini</p>
                        <h5 class="mb-0" data-signal-resignation>0</h5>
                    </div>
                    <div class="col-md-2 col-6">
                        <p class="fs-12 text-muted mb-1">Promotion Bulan Ini</p>
                        <h5 class="mb-0" data-signal-promotion>0</h5>
                    </div>
                    <div class="col-md-2 col-6">
                        <p class="fs-12 text-muted mb-1">Overtime Jam Bulan Ini</p>
                        <h5 class="mb-0" data-signal-overtime-hours>0</h5>
                    </div>
                    <div class="col-md-2 col-6">
                        <p class="fs-12 text-muted mb-1">Clock-in Missing</p>
                        <h5 class="mb-0" data-signal-anomaly-missing>0</h5>
                    </div>
                    <div class="col-md-2 col-6">
                        <p class="fs-12 text-muted mb-1">Double Shift</p>
                        <h5 class="mb-0" data-signal-anomaly-double>0</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
