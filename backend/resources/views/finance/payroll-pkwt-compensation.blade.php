<?php $page = 'payroll-pkwt-compensation'; ?>
@extends('layout.mainlayout')
@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Payroll — Contract Compensation</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">HR</li>
                            <li class="breadcrumb-item active" aria-current="page">Payroll / Contract Compensation</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="me-2 mb-2">
                        <a href="{{ url('employee-salary') }}" class="btn btn-white d-inline-flex align-items-center">
                            <i class="ti ti-users me-1"></i>Employee Salary
                        </a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="alert alert-light border mb-4" role="status">
                <strong>Contract compensation:</strong> halaman ini membantu HR melihat <strong>siapa saja karyawan contract yang kontraknya berakhir bulan ini</strong>, berikut estimasi kompensasi berdasarkan <strong>gaji pokok</strong> dan lama masa kerja kontrak.
                Cocok untuk review payroll sebelum pembayaran final / posting manual.
            </div>

            <div class="row mb-4">
                <div class="col-xl-6 d-flex">
                    <div class="card flex-fill mb-3 mb-xl-0 border-primary border-opacity-25">
                        <div class="card-header d-flex align-items-center justify-content-between gap-2">
                            <h5 class="mb-0">Daftar kompensasi contract per bulan</h5>
                            <span class="badge bg-light text-dark">HCM Admin</span>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-danger d-none py-2 small mb-3" role="alert" data-pkwt-list-error></div>
                            <div class="alert alert-warning d-none py-2 small mb-3" role="alert" data-pkwt-reconciliation-hint></div>
                            <div class="d-none mb-3" role="status" data-pkwt-evidence-indicator>
                                <small class="text-muted">Evidence status: <span class="badge bg-success" data-evidence-status>Loading...</span></small>
                                <small class="d-block mt-1 text-muted" data-evidence-timestamp></small>
                            </div>
                            <form class="row g-2" data-pkwt-list-form>
                                <div class="col-md-6">
                                    <label class="form-label">Tahun</label>
                                    <input type="number" class="form-control" min="2000" max="2100" step="1" required value="{{ date('Y') }}" data-pkwt-period-year>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bulan</label>
                                    <select class="form-select" required data-pkwt-period-month>
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" {{ (int) date('n') === $m ? 'selected' : '' }}>{{ sprintf('%02d', $m) }} — {{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-12 mt-2 d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary">Load list</button>
                                    <button type="button" class="btn btn-outline-secondary" data-pkwt-export-evidence title="Export data reconciliation untuk bukti audit sebelum pay">Export Reconciliation</button>
                                    <button type="button" class="btn btn-outline-primary" data-pkwt-post-payroll>Generate draft payroll</button>
                                    <button type="button" class="btn btn-success" data-pkwt-pay-run disabled>Pay compensation</button>
                                    <a href="{{ url('employee-salary') }}" class="btn btn-outline-secondary">Edit kontrak karyawan</a>
                                </div>
                            </form>
                            <div class="border rounded px-3 py-2 mt-3 bg-light small d-flex flex-wrap gap-4">
                                <div><span class="text-muted">Total kontrak berakhir:</span> <strong data-pkwt-summary-total>0</strong></div>
                                <div><span class="text-muted">Eligible:</span> <strong data-pkwt-summary-eligible>0</strong></div>
                                <div><span class="text-muted">Grand total:</span> <strong data-pkwt-summary-grand>Rp0</strong></div>
                            </div>
                            <p class="small text-muted mb-0 mt-3" data-pkwt-regulation-note>
                                Formula ringkas mengikuti prinsip kompensasi contract proporsional berdasarkan masa kerja kontrak dan upah bulanan.
                            </p>
                            <div class="border rounded px-3 py-2 mt-3 bg-white small" data-pkwt-run-state>
                                <span class="text-muted">Belum ada payroll kompensasi PKWT untuk periode ini.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header d-flex align-items-center justify-content-between gap-2">
                            <h5 class="mb-0">Estimasi kompensasi contract</h5>
                            <span class="badge bg-light text-dark">Quick check</span>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">
                                Gunakan untuk cek cepat satu kontrak tanpa harus membuka daftar bulanan.
                            </p>
                            <div class="alert alert-danger d-none py-2 small mb-3" role="alert" data-pkwt-calc-error></div>
                            <form class="row g-2" data-pkwt-calc-form>
                                <div class="col-md-6">
                                    <label class="form-label">Mulai kontrak</label>
                                    <input type="date" class="form-control" name="contractStartDate" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Akhir kontrak</label>
                                    <input type="date" class="form-control" name="contractEndDate" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gaji pokok / bulan</label>
                                    <input type="number" class="form-control" name="baseMonthlySalary" min="0" step="1000" required placeholder="5000000">
                                </div>
                                <div class="col-12 mt-2">
                                    <button type="submit" class="btn btn-primary">Hitung estimasi</button>
                                </div>
                            </form>
                            <div class="mt-3 pt-3 border-top" data-pkwt-calc-result>
                                <p class="text-muted small mb-0">Isi form lalu submit untuk melihat hasil.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h5 class="mb-0">Karyawan yang mendapat kompensasi bulan ini</h5>
                    <span class="badge bg-light text-dark">Preview list</span>
                </div>
                <div class="card-body p-0 border-top">
                    <p class="text-muted small mb-0 px-3 py-2 border-bottom bg-white d-none" data-pkwt-list-empty>Belum ada contract yang berakhir pada periode ini.</p>
                    <div class="table-responsive">
                        <table class="table table-nowrap mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Karyawan</th>
                                    <th>Jabatan</th>
                                    <th>Status</th>
                                    <th>Mulai kontrak</th>
                                    <th>Akhir kontrak</th>
                                    <th class="text-center">M</th>
                                    <th class="text-end">Upah acuan</th>
                                    <th class="text-end">%</th>
                                    <th class="text-end">Kompensasi</th>
                                    <th class="text-center">Pembayaran</th>
                                </tr>
                            </thead>
                            <tbody data-pkwt-list-body>
                                <tr><td colspan="10" class="text-center text-muted py-4">Memuat data…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pkwt_reconciliation_preview_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Preview Reconciliation PKWT</h5>
                        <p class="text-muted small mb-0">Tinjau data kompensasi PKWT sebelum membuat dan mengunduh file evidence.</p>
                    </div>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="px-3 pt-3 pb-2 bg-light border-bottom">
                        <div class="row g-3 small">
                            <div class="col-sm-3 d-flex justify-content-between border-end">
                                <span class="text-muted">Periode</span>
                                <strong data-pkwt-recon-preview-period>—</strong>
                            </div>
                            <div class="col-sm-3 d-flex justify-content-between border-end">
                                <span class="text-muted">Karyawan eligible</span>
                                <strong data-pkwt-recon-preview-count>0</strong>
                            </div>
                            <div class="col-sm-3 d-flex justify-content-between border-end">
                                <span class="text-muted">Grand total</span>
                                <strong class="text-primary" data-pkwt-recon-preview-total>Rp0</strong>
                            </div>
                            <div class="col-sm-3 d-flex justify-content-between">
                                <span class="text-muted">Total kontrak</span>
                                <strong data-pkwt-recon-preview-all-count>0</strong>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-nowrap table-hover mb-0 align-middle">
                            <thead class="thead-light">
                                <tr>
                                    <th>Karyawan</th>
                                    <th class="text-end">Upah acuan/bln</th>
                                    <th class="text-center">Masa kerja</th>
                                    <th class="text-center">Multiplier</th>
                                    <th class="text-end">Kompensasi</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody data-pkwt-recon-preview-body>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Memuat data…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <p class="text-muted small mb-0">
                        <i class="ti ti-info-circle me-1"></i>
                        File CSV akan dibuat dari data di atas. Setelah diunduh, tombol Pay Compensation akan terbuka.
                    </p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-secondary" data-pkwt-recon-preview-download>
                            <i class="ti ti-download me-1"></i>Download CSV &amp; Konfirmasi Evidence
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pkwt_pay_confirm_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm PKWT Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Anda akan membayarkan seluruh kompensasi PKWT untuk periode ini.</p>
                    <p class="mb-0 text-muted small" data-pkwt-pay-confirm-detail>Silakan konfirmasi untuk melanjutkan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" data-pkwt-pay-confirm-submit>Confirm Payment</button>
                </div>
            </div>
        </div>
    </div>
@endsection
