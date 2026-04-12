<div class="employee-home-dashboard" data-employee-home-dashboard>
    <style>
        .employee-home-dashboard {
            --ehd-surface: #ffffff;
            --ehd-text: #0f172a;
            --ehd-muted: #64748b;
            --ehd-border: #e2e8f0;
            --ehd-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            --ehd-shadow-hover: 0 12px 28px rgba(15, 23, 42, 0.1);
        }

        .employee-home-dashboard .card {
            border: 1px solid var(--ehd-border);
            border-radius: 16px;
            box-shadow: var(--ehd-shadow);
            background: var(--ehd-surface);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .employee-home-dashboard .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--ehd-shadow-hover);
        }

        .employee-home-dashboard .card-header {
            border-bottom: 1px solid var(--ehd-border);
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px 16px 0 0;
        }

        .employee-home-dashboard .profile-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--ehd-border);
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            color: var(--ehd-muted);
            background: #fff;
        }

        .employee-home-dashboard .metric-value {
            color: var(--ehd-text);
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .employee-home-dashboard .metric-label {
            color: var(--ehd-muted);
            font-size: 12px;
        }

        .employee-home-dashboard .soft-progress {
            height: 10px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
        }

        .employee-home-dashboard .soft-progress > span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #0ea5e9 0%, #14b8a6 100%);
            border-radius: 999px;
            width: 0;
            transition: width 0.3s ease;
        }

        .employee-home-dashboard .quick-actions {
            display: grid;
            gap: 10px;
        }

        .employee-home-dashboard .quick-action {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--ehd-border);
            border-radius: 12px;
            padding: 11px 12px;
            text-decoration: none;
            color: var(--ehd-text);
            background: #fff;
            transition: all 0.2s ease;
        }

        .employee-home-dashboard .quick-action:hover {
            border-color: #cbd5e1;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            color: var(--ehd-text);
        }

        .employee-home-dashboard .quick-action i:first-child {
            margin-right: 8px;
        }
    </style>

    <div class="row g-3 mb-3">
        <div class="col-xxl-5 d-flex">
            <div class="card flex-fill">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                        <div>
                            <p class="text-muted mb-1" data-employee-greeting>Good Day</p>
                            <h4 class="mb-1" data-employee-name>User</h4>
                            <p class="mb-0 text-muted"><span data-employee-designation>Employee</span> - <span data-employee-team>General</span></p>
                        </div>
                        <a href="{{ url('employee-details') }}" class="btn btn-light btn-sm">View Profile</a>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="profile-chip"><i class="ti ti-mail"></i><span data-employee-email>-</span></span>
                        <span class="profile-chip"><i class="ti ti-phone"></i><span data-employee-phone>-</span></span>
                        <span class="profile-chip"><i class="ti ti-calendar"></i>Join <span data-employee-join-date>-</span></span>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <p class="metric-label mb-1">Today Check-in</p>
                            <h6 class="metric-value mb-0" data-attendance-checkin>-</h6>
                        </div>
                        <div class="col-6">
                            <p class="metric-label mb-1">Today Check-out</p>
                            <h6 class="metric-value mb-0" data-attendance-checkout>-</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-4 d-flex">
            <div class="card flex-fill">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Attendance Today</h5>
                    <span class="badge bg-light text-dark" data-attendance-now-label>-</span>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-end justify-content-between mb-2">
                        <div>
                            <p class="metric-label mb-1">Productive Hours</p>
                            <h3 class="metric-value mb-0"><span data-attendance-production-hours>0</span>h</h3>
                        </div>
                        <a href="{{ url('attendance-employee') }}" class="btn btn-primary btn-sm">Open Attendance</a>
                    </div>
                    <div class="soft-progress mb-3">
                        <span data-attendance-progress-bar></span>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <p class="metric-label mb-1">Total Working</p>
                            <h6 class="metric-value mb-0" data-attendance-total-working>-</h6>
                        </div>
                        <div class="col-6">
                            <p class="metric-label mb-1">Break</p>
                            <h6 class="metric-value mb-0" data-attendance-break>-</h6>
                        </div>
                        <div class="col-6">
                            <p class="metric-label mb-1">Productive</p>
                            <h6 class="metric-value mb-0" data-attendance-productive>-</h6>
                        </div>
                        <div class="col-6">
                            <p class="metric-label mb-1">Overtime</p>
                            <h6 class="metric-value mb-0" data-attendance-overtime>-</h6>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0 d-none" data-attendance-warning>
                        Attendance hari ini butuh review admin.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-3 d-flex">
            <div class="card flex-fill">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body quick-actions">
                    <a href="{{ url('attendance-employee') }}" class="quick-action"><span><i class="ti ti-fingerprint"></i>Attendance</span><i class="ti ti-chevron-right"></i></a>
                    <a href="{{ url('leaves-employee') }}" class="quick-action"><span><i class="ti ti-calendar-event"></i>Leave Request</span><i class="ti ti-chevron-right"></i></a>
                    <a href="{{ url('overtime-employee') }}" class="quick-action"><span><i class="ti ti-clock-hour-4"></i>Overtime Request</span><i class="ti ti-chevron-right"></i></a>
                    <a href="{{ url('payslip') }}" class="quick-action"><span><i class="ti ti-receipt-2"></i>Payslip</span><i class="ti ti-chevron-right"></i></a>
                    <a href="{{ url('employee-details') }}" class="quick-action"><span><i class="ti ti-id-badge-2"></i>My Profile</span><i class="ti ti-chevron-right"></i></a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xxl-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body">
                    <p class="metric-label mb-1">Today Hours</p>
                    <h3 class="metric-value mb-0"><span data-stat-today-hours>0</span> / <span class="text-muted fs-6" data-stat-today-target>9</span></h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body">
                    <p class="metric-label mb-1">Week Hours</p>
                    <h3 class="metric-value mb-0"><span data-stat-week-hours>0</span> / <span class="text-muted fs-6" data-stat-week-target>40</span></h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body">
                    <p class="metric-label mb-1">Month Hours</p>
                    <h3 class="metric-value mb-0"><span data-stat-month-hours>0</span> / <span class="text-muted fs-6" data-stat-month-target>98</span></h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body">
                    <p class="metric-label mb-1">Month Overtime</p>
                    <h3 class="metric-value mb-0"><span data-stat-month-ot-hours>0</span> / <span class="text-muted fs-6" data-stat-month-ot-target>28</span></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-4 d-flex">
            <div class="card flex-fill">
                <div class="card-header">
                    <h5 class="mb-0">Leave Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Total Request</span><strong data-leave-total>0</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Pending</span><strong data-leave-pending>0</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Approved</span><strong data-leave-approved>0</strong></div>
                    <div class="d-flex justify-content-between"><span>Declined</span><strong data-leave-declined>0</strong></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 d-flex">
            <div class="card flex-fill">
                <div class="card-header">
                    <h5 class="mb-0">Overtime Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Pending Request</span><strong data-ot-pending>0</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Approved This Month</span><strong data-ot-approved-month>0</strong></div>
                    <div class="d-flex justify-content-between"><span>Approved Hours This Month</span><strong data-ot-approved-hours>0</strong></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 d-flex">
            <div class="card flex-fill">
                <div class="card-header">
                    <h5 class="mb-0">Payroll Snapshot</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Latest Period</span><strong data-payroll-latest-period>-</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Run Status</span><strong class="text-capitalize" data-payroll-latest-status>-</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Payment Status</span><strong class="text-capitalize" data-payroll-payment-status>-</strong></div>
                    <div class="d-flex justify-content-between"><span>Estimated Net Pay</span><strong data-payroll-net-pay>Rp 0</strong></div>
                </div>
            </div>
        </div>
    </div>
</div>
