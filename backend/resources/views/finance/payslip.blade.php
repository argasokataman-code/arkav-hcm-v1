<?php $page = 'payslip'; ?>
@extends('layout.mainlayout')
@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">My Payslip</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                            <li class="breadcrumb-item">HR</li>
                            <li class="breadcrumb-item active" aria-current="page">My Payslip</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="javascript:void(0);" class="btn btn-dark d-flex align-items-center disabled" data-payslip-download aria-disabled="true">
                        <i class="ti ti-download me-2"></i>Download PDF
                    </a>
                    <a href="{{ url('payslip-report') }}" class="btn btn-outline-primary d-none" data-payslip-admin-shortcut>
                        <i class="ti ti-file-text me-2"></i>Buka Payslip Report
                    </a>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-end g-2">
                        <div class="col-md-3">
                            <label class="form-label">Tahun</label>
                            <input type="number" class="form-control" min="2000" max="2100" value="{{ date('Y') }}" data-payslip-year>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bulan</label>
                            <select class="form-select" data-payslip-month>
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ (int) date('n') === $m ? 'selected' : '' }}>{{ $m }} - {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-auto">
                            <button type="button" class="btn btn-primary" onclick="if(window.payslipLoad) window.payslipLoad({ allowLatestFallback: false });">Muat Slip Saya</button>
                        </div>
                    </div>
                    <div class="alert alert-danger d-none py-2 small mt-3 mb-0" role="alert" data-payslip-error></div>
                </div>
            </div>

            <p class="text-muted small mb-3" data-payslip-empty>
                Slip gaji pribadi akan tampil setelah payroll periode yang dipilih difinalisasi.
            </p>
            <p class="text-info small mb-3 d-none" data-payslip-context-hint></p>

            <div class="d-none" data-payslip-content>
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row justify-content-between align-items-center border-bottom mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="mb-2">
                                        <img src="{{ URL::asset('build/img/image111.png') }}" class="img-fluid" alt="logo">
                                    </div>
                                    <p class="mb-1">{{ $companyName ?? '' }}</p>
                                    <p class="mb-0 text-muted">Divisi SDM / Payroll</p>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="mb-3">
                                    <h5 class="text-gray mb-1">Payslip No <span class="text-primary">#<span data-payslip-slip-no>—</span></span></h5>
                                    <p class="fw-medium mb-1">Salary Month : <span class="text-dark" data-payslip-period-label>—</span></p>
                                    <p class="mb-0">Status run: <span class="badge bg-success" data-payslip-status>—</span></p>
                                </div>
                            </div>
                        </div>

                        <div class="row border-bottom align-items-center mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <p class="text-dark mb-2 fw-semibold">Employee</p>
                                    <div>
                                        <h4 class="mb-1" data-payslip-employee-name>—</h4>
                                        <p class="mb-1">Jabatan : <span class="text-dark" data-payslip-employee-designation>—</span></p>
                                        <p class="mb-1">Email : <span class="text-dark" data-payslip-employee-email>—</span></p>
                                        <p class="mb-0">Tim : <span class="text-dark" data-payslip-employee-team>—</span></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <div class="mb-3">
                                    <p class="mb-1">Total Additions: <strong data-payslip-earnings-total>Rp 0</strong></p>
                                    <p class="mb-1">Total Overtime: <strong class="text-info" data-payslip-overtime-total>Rp 0</strong></p>
                                    <p class="mb-1">Total Deductions: <strong data-payslip-deductions-total>Rp 0</strong></p>
                                    <p class="mb-0 fs-16">Take Home Pay: <strong class="text-primary" data-payslip-net-pay>Rp 0</strong></p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="list-group mb-3">
                                    <div class="list-group-item bg-light p-3 border-bottom-0"><h6 class="mb-0">Additions</h6></div>
                                    <div data-payslip-earnings></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="list-group mb-3">
                                    <div class="list-group-item bg-light p-3 border-bottom-0"><h6 class="mb-0">Deductions</h6></div>
                                    <div data-payslip-deductions></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
         
        <!-- /Footer -->
    </div>
    <!-- /Page Wrapper -->
@endsection
