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



            <div class="mb-4">

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
                    <form data-payroll-settings-form>
                        <ul class="nav nav-tabs border-bottom mb-3" id="payroll-settings-tab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="payroll-settings-editor-tab" data-bs-toggle="tab" data-bs-target="#payroll-settings-editor" type="button" role="tab" aria-controls="payroll-settings-editor" aria-selected="true">
                                    Form Setup
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="payroll-settings-preview-tab" data-bs-toggle="tab" data-bs-target="#payroll-settings-preview" type="button" role="tab" aria-controls="payroll-settings-preview" aria-selected="false">
                                    Preview Operasional
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="payroll-settings-tab-content">
                            <div class="tab-pane fade show active" id="payroll-settings-editor" role="tabpanel" aria-labelledby="payroll-settings-editor-tab" tabindex="0">
                                <div class="row g-3">
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
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="payroll_disburse_before_payday_allowed" data-payroll-settings-disburse-early>
                                            <label class="form-check-label" for="payroll_disburse_before_payday_allowed">Izinkan bayar sebelum payday</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="payroll-settings-preview" role="tabpanel" aria-labelledby="payroll-settings-preview-tab" tabindex="0">
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
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="button" class="btn btn-primary" data-payroll-settings-confirm>Simpan policy payroll</button>
                        </div>
                    </form>
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

                    <div class="border rounded bg-light p-3 mb-3">
                        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                            <div>
                                <div class="text-muted text-uppercase small fw-semibold">Workflow Payroll</div>
                                <h6 class="mb-1" data-payroll-run-stage-title>Memuat status workflow…</h6>
                                <p class="text-muted small mb-0" data-payroll-run-stage-description>Halaman ini akan memandu urutan Calculate Draft, review, export evidence, lalu payment.</p>
                            </div>
                            <span class="badge bg-secondary" data-payroll-run-stage-badge>Memuat</span>
                        </div>

                        <div class="row g-3 align-items-start">
                            <div class="col-xl-8">
                                <ol class="list-group list-group-numbered" data-payroll-run-workflow-steps>
                                    <li class="list-group-item d-flex justify-content-between align-items-start" data-payroll-step="period">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-semibold">Periode aktif</div>
                                            <div class="text-muted small">Pastikan operator bekerja pada bulan payroll aktif yang sedang dibuka sistem.</div>
                                        </div>
                                        <span class="badge bg-light text-dark rounded-pill" data-payroll-step-status>Menunggu</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-start" data-payroll-step="calculate">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-semibold">Calculate draft</div>
                                            <div class="text-muted small">Hitung atau refresh draft payroll sebelum review komponen dan status employee.</div>
                                        </div>
                                        <span class="badge bg-light text-dark rounded-pill" data-payroll-step-status>Menunggu</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-start" data-payroll-step="review">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-semibold">Review payroll</div>
                                            <div class="text-muted small">Tinjau eligibility, total THP, tenant aktif, snapshot policy, dan anomali operasional.</div>
                                        </div>
                                        <span class="badge bg-light text-dark rounded-pill" data-payroll-step-status>Menunggu</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-start" data-payroll-step="export">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-semibold">Export evidence</div>
                                            <div class="text-muted small">Buat lalu unduh file XLSX reconciliation terbaru sebagai bukti sebelum payment.</div>
                                        </div>
                                        <span class="badge bg-light text-dark rounded-pill" data-payroll-step-status>Menunggu</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-start" data-payroll-step="pay">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-semibold">Pay via gateway</div>
                                            <div class="text-muted small">Lanjutkan batch transfer hanya setelah evidence valid terunduh dan policy mengizinkan.</div>
                                        </div>
                                        <span class="badge bg-light text-dark rounded-pill" data-payroll-step-status>Menunggu</span>
                                    </li>
                                </ol>
                            </div>
                            <div class="col-xl-4">
                                <div class="border rounded bg-white h-100 p-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                        <div>
                                            <h6 class="mb-1">Checklist Operasional</h6>
                                            <p class="text-muted small mb-0">Ringkas blocker dan readiness sebelum operator lanjut ke langkah berikutnya.</p>
                                        </div>
                                        <span class="badge bg-light text-dark" data-payroll-run-readiness-badge>Memuat</span>
                                    </div>

                                    <div class="list-group list-group-flush small mb-3">
                                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="fw-semibold">Tenant aktif</div>
                                                <div class="text-muted" data-payroll-checklist-tenant-note>Menunggu konteks tenant…</div>
                                            </div>
                                            <span class="badge bg-light text-dark" data-payroll-checklist-tenant>Status</span>
                                        </div>
                                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="fw-semibold">Snapshot policy</div>
                                                <div class="text-muted" data-payroll-checklist-policy-note>Menunggu policy snapshot pada run aktif…</div>
                                            </div>
                                            <span class="badge bg-light text-dark" data-payroll-checklist-policy>Status</span>
                                        </div>
                                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="fw-semibold">Profil PPh21 karyawan</div>
                                                <div class="text-muted" data-payroll-checklist-tax-profile-note>Menunggu hasil deteksi anomali profil PPh21…</div>
                                            </div>
                                            <span class="badge bg-light text-dark" data-payroll-checklist-tax-profile>Status</span>
                                        </div>
                                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="fw-semibold">Evidence export</div>
                                                <div class="text-muted" data-payroll-checklist-evidence-note>Belum ada evidence yang siap dipakai.</div>
                                            </div>
                                            <span class="badge bg-light text-dark" data-payroll-checklist-evidence>Status</span>
                                        </div>
                                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="fw-semibold">Window disburse</div>
                                                <div class="text-muted" data-payroll-checklist-disburse-note>Menunggu evaluasi payday/cutoff run aktif…</div>
                                            </div>
                                            <span class="badge bg-light text-dark" data-payroll-checklist-disburse>Status</span>
                                        </div>
                                    </div>

                                    <div class="alert alert-warning d-none py-2 small mb-2" role="alert" data-payroll-run-reconciliation-hint></div>
                                    <div class="alert alert-warning d-none py-2 small mb-2" role="alert" data-payroll-run-tenant-hint></div>
                                    <div class="alert alert-warning d-none py-2 small mb-2" role="alert" data-payroll-run-tax-anomaly-hint></div>
                                    <div class="alert alert-info d-none py-2 small mb-0" role="alert" data-payroll-run-tax-policy-hint></div>

                                    <div class="d-none mt-3" role="status" data-payroll-run-evidence-indicator>
                                        <small class="text-muted">Evidence status: <span class="badge bg-success" data-evidence-status>Loading...</span></small>
                                        <small class="d-block mt-1 text-muted" data-evidence-timestamp></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <div class="border rounded p-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                    <div>
                                        <div class="text-muted text-uppercase small fw-semibold">Aksi Saat Ini</div>
                                        <h6 class="mb-1" data-payroll-run-primary-action-title>Menyiapkan aksi utama…</h6>
                                        <p class="text-muted small mb-0" data-payroll-run-primary-action-note>Primary action akan berubah mengikuti stage workflow payroll run.</p>
                                    </div>
                                    <span class="badge bg-light text-dark" data-payroll-run-primary-action-state>Menunggu</span>
                                </div>

                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <button type="button" class="btn btn-outline-primary" data-payroll-run-calculate disabled title="Hitung / refresh draft selama run masih status draft.">Calculate Draft</button>
                                    <button type="button" class="btn btn-outline-secondary" data-payroll-run-export-evidence disabled title="Hanya aktif jika run berstatus draft. Urutan: Calculate Draft → Export → unduh XLSX → Pay via Gateway.">Export Reconciliation</button>
                                    <button type="button" class="btn btn-success" data-payroll-run-disburse disabled title="Aktif setelah unduh file export reconciliation untuk run ini.">Pay via Gateway</button>
                                </div>
                                <div class="small text-muted" data-payroll-run-action-guidance>
                                    Gunakan Calculate Draft untuk memulai run payroll aktif.
                                </div>
                            </div>
                        </div>
                        @if (app()->environment(['local', 'development', 'testing']) && $isPrimarySuperAdminCodeOne)
                            <div class="col-lg-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted text-uppercase small fw-semibold mb-1">Aksi DEV</div>
                                    <h6 class="mb-3">Debug tools</h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-danger" data-payroll-run-reset-payments>Reset Pembayaran (DEV)</button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="border rounded px-3 py-2 mb-0 bg-light small d-flex flex-wrap gap-4">
                        <div><span class="text-muted">Total Karyawan:</span> <strong data-payroll-run-emp-count>0</strong></div>
                        <div><span class="text-muted">Dipilih:</span> <strong data-payroll-run-selected-count>0</strong></div>
                        <div><span class="text-muted">Total Line (Rincian):</span> <strong data-payroll-run-line-count>0</strong></div>
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

            <div class="modal fade" id="payroll_settings_confirm_modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Konfirmasi Simpan Policy Payroll</h5>
                            <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-1">Anda yakin ingin menyimpan perubahan policy payroll?</p>
                            <p class="text-muted small mb-0">Perubahan hanya berlaku untuk draft berikutnya atau draft yang dihitung ulang.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="button" class="btn btn-primary" data-payroll-settings-save>Ya, Simpan</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="payroll_gateway_modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Pay via Gateway</h5>
                            <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                                <i class="ti ti-x"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-light border small mb-3">
                                Gateway akan memfinalkan draft bila masih draft, lalu memproses batch pembayaran yang dipilih secara aman dan idempotent.
                            </div>
                            <div class="border rounded bg-light p-3 mb-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <h6 class="mb-0">Ringkasan Batch</h6>
                                    <span class="badge bg-light text-dark border" data-payroll-gateway-status>—</span>
                                </div>
                                <div class="row g-2 small">
                                    <div class="col-sm-6 d-flex justify-content-between"><span class="text-muted">Periode</span><strong data-payroll-gateway-period>—</strong></div>
                                    <div class="col-sm-6 d-flex justify-content-between"><span class="text-muted">Karyawan dipilih</span><strong data-payroll-gateway-count>0</strong></div>
                                    <div class="col-sm-6 d-flex justify-content-between"><span class="text-muted">Total Penghasilan</span><span class="text-success fw-semibold" data-payroll-gateway-gross>Rp0</span></div>
                                    <div class="col-sm-6 d-flex justify-content-between"><span class="text-muted">Total Potongan</span><span class="text-danger fw-semibold" data-payroll-gateway-deductions>Rp0</span></div>
                                    <div class="col-sm-6 d-flex justify-content-between"><span class="text-muted">Total THP</span><strong data-payroll-gateway-total>Rp0</strong></div>
                                </div>
                            </div>

                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="mb-0">Rincian Karyawan</h6>
                                    <span class="text-muted small">Komponen payroll yang mempengaruhi THP</span>
                                </div>
                                <div class="list-group list-group-flush border rounded" data-payroll-gateway-list></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-success" data-payroll-gateway-pay>Pay now</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="payroll_reconciliation_preview_modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title">Preview Reconciliation</h5>
                                <p class="text-muted small mb-0">Tinjau data payroll sebelum membuat dan mengunduh file evidence.</p>
                            </div>
                            <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                                <i class="ti ti-x"></i>
                            </button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="px-3 pt-3 pb-2 bg-light border-bottom" data-recon-preview-summary>
                                <div class="row g-3 small">
                                    <div class="col-sm-3 d-flex justify-content-between border-end">
                                        <span class="text-muted">Periode</span>
                                        <strong data-recon-preview-period>—</strong>
                                    </div>
                                    <div class="col-sm-3 d-flex justify-content-between border-end">
                                        <span class="text-muted">Karyawan</span>
                                        <strong data-recon-preview-count>0</strong>
                                    </div>
                                    <div class="col-sm-3 d-flex justify-content-between border-end">
                                        <span class="text-muted">Total THP</span>
                                        <strong class="text-primary" data-recon-preview-net>Rp0</strong>
                                    </div>
                                    <div class="col-sm-3 d-flex justify-content-between">
                                        <span class="text-muted">Total Bruto</span>
                                        <strong data-recon-preview-gross>Rp0</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-nowrap table-hover mb-0 align-middle">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Karyawan</th>
                                            <th class="text-end">Bruto</th>
                                            <th class="text-end">Potongan</th>
                                            <th class="text-end">THP</th>
                                            <th class="text-center">Komponen</th>
                                            <th class="text-center">THR</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody data-recon-preview-body>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">Memuat data…</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <p class="text-muted small mb-0">
                                <i class="ti ti-info-circle me-1"></i>
                                File XLSX akan dibuat dari data di atas. Setelah diunduh, tombol Pay via Gateway akan terbuka.
                            </p>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="button" class="btn btn-secondary" data-recon-preview-download>
                                    <i class="ti ti-download me-1"></i>Download XLSX &amp; Konfirmasi Evidence
                                </button>
                            </div>
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
