<?php $page = 'payroll-run'; ?>
@extends('layout.mainlayout')
@section('content')

    @php
        $authUser = request()->user() ?: auth()->user();
        $primarySuperAdminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
        $authUserEmail = strtolower(trim((string) ($authUser->email ?? '')));
        $isPrimarySuperAdminCodeOne = (bool) ($authUser && $authUserEmail === $primarySuperAdminEmail);
    @endphp

    <div class="page-wrapper">
        <div class="content">

            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Payroll — Run Bulanan</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">HR</li>
                            <li class="breadcrumb-item active" aria-current="page">Payroll / Run Bulanan</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>



            <div class="alert alert-light border mb-3" role="status">
                <strong>Payroll Run Bulanan:</strong> Halaman ini terkunci ke periode payroll aktif. Untuk melihat periode historis gunakan <a href="{{ url('payroll-run-history') }}" class="alert-link fw-semibold">History Monthly Payroll</a>.
            </div>

            <div class="card mb-4" data-payroll-settings-panel>
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">Policy Cutoff &amp; Payday</h5>
                        <p class="text-muted small mb-0">Atur tanggal gajian bulanan dan batas cutoff variabel payroll untuk tenant aktif.</p>
                    </div>
                    <span class="badge bg-secondary" data-payroll-settings-stage>Memuat status…</span>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger d-none py-2 small mb-3" role="alert" data-payroll-settings-feedback></div>
                    <form class="row g-3" data-payroll-settings-form>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal gajian</label>
                            <input type="number" class="form-control" min="1" max="31" step="1" data-payroll-settings-payday-day>
                            <span class="text-muted small">Jika hari tidak ada, sistem pakai hari terakhir bulan.</span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cutoff variabel payroll</label>
                            <div class="input-group">
                                <input type="number" class="form-control" min="0" max="15" step="1" data-payroll-settings-cutoff-offset>
                                <span class="input-group-text">hari sebelum payday</span>
                            </div>
                            <span class="text-muted small">Contoh umum: payday 28, cutoff 3 hari sebelumnya menjadi tanggal 25.</span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Timezone payroll</label>
                            <select class="form-select" data-payroll-settings-timezone>
                                <option value="Asia/Jakarta">Asia/Jakarta (WIB)</option>
                                <option value="Asia/Makassar">Asia/Makassar (WITA)</option>
                                <option value="Asia/Jayapura">Asia/Jayapura (WIT)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Strategi payday saat libur</label>
                            <select class="form-select" data-payroll-settings-holiday-strategy>
                                <option value="previous_working_day">Majukan ke hari kerja sebelumnya</option>
                                <option value="next_working_day">Geser ke hari kerja berikutnya</option>
                                <option value="exact_calendar_day">Tetap tanggal kalender</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="payroll_disburse_before_payday_allowed" data-payroll-settings-disburse-early>
                                <label class="form-check-label" for="payroll_disburse_before_payday_allowed">Izinkan bayar sebelum payday</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border rounded bg-light px-3 py-3">
                                <div class="row g-3 align-items-start">
                                    <div class="col-md-4">
                                        <div class="text-muted small">Periode aktif</div>
                                        <div class="fw-semibold" data-payroll-settings-preview-period>Menunggu periode aktif…</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Resolved payday</div>
                                        <div class="fw-semibold" data-payroll-settings-preview-payday>—</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Resolved cutoff</div>
                                        <div class="fw-semibold" data-payroll-settings-preview-cutoff>—</div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="text-muted small">Panduan operasional</div>
                                        <div class="small mb-0" data-payroll-settings-preview-note>Perubahan variabel payroll setelah cutoff akan diperlakukan sebagai input periode berikutnya.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" data-payroll-settings-save>Simpan policy payroll</button>
                        </div>
                    </form>
                </div>
            </div>

            @include('hcm.partials.payroll-lifecycle-alert', ['variant' => 'warning', 'title' => 'Aturan void & perubahan setup'])

            <div class="card mb-4 d-none" data-payroll-work-config-panel>
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">Konfigurasi Pola Kerja Payroll</h5>
                        <p class="text-muted small mb-0">Atur rule office hour vs shift worker untuk fallback kalkulasi lembur payroll.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-primary" data-payroll-work-auto-generate>Auto Generate dari Run Aktif</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-payroll-work-refresh>Refresh</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger d-none py-2 small mb-3" role="alert" data-payroll-work-error></div>
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <div class="border rounded p-3 h-100">
                                <h6 class="mb-2">Tambah Work Profile</h6>
                                <form data-payroll-work-profile-form>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Kode profile</label>
                                            <input type="text" class="form-control" data-payroll-work-profile-code placeholder="contoh: shift_6d_ops" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Nama profile</label>
                                            <input type="text" class="form-control" data-payroll-work-profile-name placeholder="Shift 6 Hari Ops" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Mode</label>
                                            <select class="form-select" data-payroll-work-profile-mode>
                                                <option value="office_hour">Office Hour</option>
                                                <option value="shift_worker">Shift Worker</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Default day type</label>
                                            <select class="form-select" data-payroll-work-profile-day-type>
                                                <option value="workday">Workday</option>
                                                <option value="public_holiday">Public Holiday</option>
                                                <option value="weekly_rest_day">Weekly Rest Day</option>
                                                <option value="weekly_rest_day_short">Weekly Rest Day Short</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Weekly work days</label>
                                            <select class="form-select" data-payroll-work-profile-weekly-days>
                                                <option value="5">5 Hari</option>
                                                <option value="6">6 Hari</option>
                                            </select>
                                        </div>
                                        <div class="col-12 form-check form-switch mt-2 ms-1">
                                            <input class="form-check-input" type="checkbox" id="payroll_work_profile_default" data-payroll-work-profile-default>
                                            <label class="form-check-label" for="payroll_work_profile_default">Set sebagai default profile tenant</label>
                                        </div>
                                    </div>
                                    <div class="mt-3 text-end">
                                        <button type="submit" class="btn btn-primary" data-payroll-work-profile-submit>Simpan Profile</button>
                                    </div>
                                </form>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Profile</th>
                                                <th>Rule</th>
                                                <th class="text-center">Default</th>
                                            </tr>
                                        </thead>
                                        <tbody data-payroll-work-profiles-body>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-3">Memuat profile...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="border rounded p-3 h-100">
                                <h6 class="mb-2">Assignment Karyawan</h6>
                                <form data-payroll-work-arrangement-form>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Karyawan</label>
                                            <select class="form-select" data-payroll-work-arrangement-user required>
                                                <option value="">Pilih karyawan</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Profile (opsional)</label>
                                            <select class="form-select" data-payroll-work-arrangement-profile>
                                                <option value="">Custom tanpa profile</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Mode</label>
                                            <select class="form-select" data-payroll-work-arrangement-mode>
                                                <option value="office_hour">Office Hour</option>
                                                <option value="shift_worker">Shift Worker</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Default day type</label>
                                            <select class="form-select" data-payroll-work-arrangement-day-type>
                                                <option value="">Ikuti profile/auto</option>
                                                <option value="workday">Workday</option>
                                                <option value="public_holiday">Public Holiday</option>
                                                <option value="weekly_rest_day">Weekly Rest Day</option>
                                                <option value="weekly_rest_day_short">Weekly Rest Day Short</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Weekly work days</label>
                                            <select class="form-select" data-payroll-work-arrangement-weekly-days>
                                                <option value="">Auto</option>
                                                <option value="5">5 Hari</option>
                                                <option value="6">6 Hari</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Effective from</label>
                                            <input type="date" class="form-control" data-payroll-work-arrangement-effective-from required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Effective to (opsional)</label>
                                            <input type="date" class="form-control" data-payroll-work-arrangement-effective-to>
                                        </div>
                                    </div>
                                    <div class="mt-3 text-end">
                                        <button type="submit" class="btn btn-primary" data-payroll-work-arrangement-submit>Simpan Assignment</button>
                                    </div>
                                </form>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Karyawan</th>
                                                <th>Profile / Rule</th>
                                                <th>Periode Aktif</th>
                                            </tr>
                                        </thead>
                                        <tbody data-payroll-work-arrangements-body>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-3">Memuat assignment...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" data-payroll-run-panel>
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">Payroll Periode Aktif</h5>
                        <p class="text-muted small mb-0">Draft payroll direfresh otomatis setiap hari pukul 00:00 WIB untuk periode yang masih open.</p>
                    </div>
                    <span class="badge bg-light text-dark">HCM Admin</span>
                </div>
                <div class="card-body">
                    <div class="row align-items-end g-2 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Tahun</label>
                            <input type="number" class="form-control" name="periodYear" min="2000" max="2100" value="{{ date('Y') }}" data-payroll-run-year placeholder="YYYY" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bulan</label>
                            <select class="form-select" name="periodMonth" data-payroll-run-month disabled>
                                <option value="">Pilih bulan</option>
                                <option value="1" @selected((int) date('n') === 1)>1 - Januari</option>
                                <option value="2" @selected((int) date('n') === 2)>2 - Februari</option>
                                <option value="3" @selected((int) date('n') === 3)>3 - Maret</option>
                                <option value="4" @selected((int) date('n') === 4)>4 - April</option>
                                <option value="5" @selected((int) date('n') === 5)>5 - Mei</option>
                                <option value="6" @selected((int) date('n') === 6)>6 - Juni</option>
                                <option value="7" @selected((int) date('n') === 7)>7 - Juli</option>
                                <option value="8" @selected((int) date('n') === 8)>8 - Agustus</option>
                                <option value="9" @selected((int) date('n') === 9)>9 - September</option>
                                <option value="10" @selected((int) date('n') === 10)>10 - Oktober</option>
                                <option value="11" @selected((int) date('n') === 11)>11 - November</option>
                                <option value="12" @selected((int) date('n') === 12)>12 - Desember</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-danger d-none py-2 small mb-3" role="alert" data-payroll-run-error></div>
                    <div class="alert alert-warning d-none py-2 small mb-3" role="alert" data-payroll-run-reconciliation-hint></div>
                    <div class="alert alert-warning d-none py-2 small mb-3" role="alert" data-payroll-run-tenant-hint></div>
                    <div class="alert alert-info d-none py-2 small mb-3" role="alert" data-payroll-run-tax-policy-hint></div>
                    <div class="alert alert-info d-none py-2 small mb-3" role="alert" data-payroll-run-void-hint></div>
                    <div class="d-none mb-3" role="status" data-payroll-run-evidence-indicator>
                        <small class="text-muted">Evidence status: <span class="badge bg-success" data-evidence-status>Loading...</span></small>
                        <small class="d-block mt-1 text-muted" data-evidence-timestamp></small>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <button type="button" class="btn btn-outline-primary" data-payroll-run-calculate disabled title="Hitung / refresh draft selama run masih status draft.">Calculate Draft</button>
                        <button type="button" class="btn btn-outline-warning" data-payroll-run-void disabled title="Hanya aktif untuk run finalized yang belum ada pembayaran.">Void Finalized Run</button>
                        <button type="button" class="btn btn-outline-secondary" data-payroll-run-export-evidence disabled title="Hanya aktif jika run berstatus draft. Urutan: Calculate Draft → Export → unduh CSV → Pay via Gateway.">Export Reconciliation</button>
                        <button type="button" class="btn btn-success" data-payroll-run-disburse disabled title="Aktif setelah unduh file export reconciliation untuk run ini.">Pay via Gateway</button>
                        @if (app()->environment(['local', 'development', 'testing']) && $isPrimarySuperAdminCodeOne)
                            <button type="button" class="btn btn-outline-danger" data-payroll-run-reset-payments>Reset Pembayaran (DEV)</button>
                        @endif
                    </div>

                    <div class="border rounded px-3 py-2 mb-0 bg-light small d-flex flex-wrap gap-4">
                        <div><span class="text-muted">Total Karyawan:</span> <strong data-payroll-run-emp-count>0</strong></div>
                        <div><span class="text-muted">Dipilih:</span> <strong data-payroll-run-selected-count>0</strong></div>
                        <div><span class="text-muted">Total Line (Rincian):</span> <strong data-payroll-run-line-count>0</strong></div>
                        <div><span class="text-muted">Biaya Layanan Payroll:</span> <strong data-payroll-run-service-fee>Rp0</strong></div>
                        <div><span class="text-muted">Status Periode:</span> <strong data-payroll-run-status>—</strong></div>
                        <div><span class="text-muted">Status Pembayaran:</span> <strong data-payroll-run-payment-status>—</strong></div>
                        <div><span class="text-muted">Tenant Aktif:</span> <strong data-payroll-run-tenant-context>—</strong></div>
                        <div><span class="text-muted">Policy Pajak Run:</span> <strong data-payroll-run-tax-policy>—</strong></div>
                    </div>
                </div>
                <div class="card-body p-0 border-top">
                    <p class="text-muted small mb-0 px-3 py-2 border-bottom bg-white" data-payroll-run-empty>
                        Payroll dimuat otomatis untuk periode yang dipilih.
                    </p>
                    <div class="table-responsive d-none" data-payroll-run-grid>
                        <table class="table table-nowrap mb-0 align-middle">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 42px;">
                                        <div class="form-check form-check-md mb-0">
                                            <input class="form-check-input" type="checkbox" data-payroll-run-select-all>
                                        </div>
                                    </th>
                                    <th>Karyawan</th>
                                    <th class="text-end">Bruto</th>
                                    <th class="text-end">Potongan</th>
                                    <th class="text-end">Netto</th>
                                    <th class="text-center">THR</th>
                                    <th class="text-center">Compensation</th>
                                    <th class="text-center">Komponen</th>
                                    <th>Status Pembayaran</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">Payroll akan muncul otomatis setelah draft tersedia.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="payroll_gateway_modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Pay via Gateway</h5>
                            <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                                <i class="ti ti-x"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-light border small">
                                Gateway akan memfinalkan draft bila masih draft, lalu memproses batch pembayaran yang dipilih secara aman dan idempotent.
                            </div>
                            <div class="border rounded p-3 bg-light mb-3">
                                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Periode</span><strong data-payroll-gateway-period>—</strong></div>
                                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Karyawan dipilih</span><strong data-payroll-gateway-count>0</strong></div>
                                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Penghasilan</span><span class="text-success fw-semibold" data-payroll-gateway-gross>Rp0</span></div>
                                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Potongan</span><span class="text-danger fw-semibold" data-payroll-gateway-deductions>Rp0</span></div>
                                <div class="d-flex justify-content-between mb-2 border-top pt-2"><span class="text-muted fw-semibold">Total THP</span><strong data-payroll-gateway-total>Rp0</strong></div>
                                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Biaya Layanan Payroll (run)</span><strong data-payroll-gateway-service-fee>Rp0</strong></div>
                                <div class="d-flex justify-content-between"><span class="text-muted">Status run</span><strong data-payroll-gateway-status>—</strong></div>
                            </div>
                            <div class="list-group list-group-flush small" data-payroll-gateway-list></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-success" data-payroll-gateway-pay>Pay now</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="payroll_detail_modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Detail Payroll Karyawan</h5>
                            <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                                <i class="ti ti-x"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="border rounded p-3 bg-light mb-3">
                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                    <div>
                                        <div class="fw-semibold" data-payroll-detail-name>—</div>
                                        <div class="text-muted small" data-payroll-detail-meta>UID: —</div>
                                    </div>
                                    <div class="text-md-end">
                                        <div class="small text-muted">Periode</div>
                                        <div class="fw-semibold" data-payroll-detail-period>—</div>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-light text-dark border" data-payroll-detail-payment-status>Payment: —</span>
                                    <span class="badge bg-light text-dark border" data-payroll-detail-eligibility>Status: —</span>
                                    <span class="badge bg-light text-dark border" data-payroll-detail-thr>THR: —</span>
                                    <span class="badge bg-light text-dark border" data-payroll-detail-compensation>Compensation: —</span>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-3">
                                    <div class="border rounded p-2 h-100">
                                        <div class="text-muted small">Total Bruto</div>
                                        <div class="fw-semibold text-success" data-payroll-detail-gross>Rp0</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2 h-100">
                                        <div class="text-muted small">Total Potongan</div>
                                        <div class="fw-semibold text-danger" data-payroll-detail-deductions>Rp0</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2 h-100">
                                        <div class="text-muted small">Take Home Pay</div>
                                        <div class="fw-semibold" data-payroll-detail-net>Rp0</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-2 h-100">
                                        <div class="text-muted small">Total Komponen</div>
                                        <div class="fw-semibold" data-payroll-detail-line-count>0</div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive border rounded">
                                <table class="table table-nowrap mb-0 align-middle">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Komponen</th>
                                            <th>Jenis</th>
                                            <th>Kategori</th>
                                            <th class="text-end">Nominal</th>
                                            <th class="text-center">Affects THP</th>
                                            <th class="text-center">Payment Status</th>
                                        </tr>
                                    </thead>
                                    <tbody data-payroll-detail-lines>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-3">Belum ada data komponen.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @component('components.modal-popup')
    @endcomponent
@endsection
