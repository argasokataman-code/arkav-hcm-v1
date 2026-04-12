<?php $page = 'payroll-run'; ?>
@extends('layout.mainlayout')
@section('content')

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



            <div class="alert alert-light border mb-4" role="status">
                <strong>Payroll Run Bulanan:</strong> Halaman ini terkunci ke periode payroll aktif. Untuk melihat periode historis gunakan <a href="{{ url('payroll-run-history') }}" class="alert-link fw-semibold">History Monthly Payroll</a>.
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

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <button type="button" class="btn btn-outline-primary" data-payroll-run-calculate disabled>Calculate Draft</button>
                        <button type="button" class="btn btn-success" data-payroll-run-disburse disabled>Pay via Gateway</button>
                        @if (app()->environment(['local', 'development', 'testing']))
                            <button type="button" class="btn btn-outline-danger" data-payroll-run-reset-payments>Reset Pembayaran (DEV)</button>
                        @endif
                    </div>

                    <div class="border rounded px-3 py-2 mb-0 bg-light small d-flex flex-wrap gap-4">
                        <div><span class="text-muted">Total Karyawan:</span> <strong data-payroll-run-emp-count>0</strong></div>
                        <div><span class="text-muted">Dipilih:</span> <strong data-payroll-run-selected-count>0</strong></div>
                        <div><span class="text-muted">Total Line (Rincian):</span> <strong data-payroll-run-line-count>0</strong></div>
                        <div><span class="text-muted">Status Periode:</span> <strong data-payroll-run-status>—</strong></div>
                        <div><span class="text-muted">Status Pembayaran:</span> <strong data-payroll-run-payment-status>—</strong></div>
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
                                    <th class="text-center">Komponen</th>
                                    <th>Status Pembayaran</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Payroll akan muncul otomatis setelah draft tersedia.</td>
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

        </div>
    </div>

    @component('components.modal-popup')
    @endcomponent
@endsection
