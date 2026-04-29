<?php $page = 'employee-salary'; ?>
@extends('layout.mainlayout')
@section('content')
    <div class="page-wrapper">
        <div class="content">

            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Gaji karyawan</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Payroll</li>
                            <li class="breadcrumb-item active" aria-current="page">Employee salary</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="me-2 mb-2">
                        <a href="{{ url('payroll') }}" class="btn btn-white d-inline-flex align-items-center">
                            <i class="ti ti-components me-1"></i>Payroll items
                        </a>
                    </div>
                    <div class="me-2 mb-2">
                        <a href="{{ url('overtime') }}" class="btn btn-white d-inline-flex align-items-center">
                            <i class="ti ti-clock-hour-4 me-1"></i>Lembur (pakai gaji pokok + tunj. tetap)
                        </a>
                    </div>
                    <div class="me-2 mb-2">
                        <a href="{{ url('payroll-pkwt-compensation') }}" class="btn btn-white d-inline-flex align-items-center">
                            <i class="ti ti-file-dollar me-1"></i>Contract Compensation
                        </a>
                    </div>
                    <div class="me-2 mb-2">
                        <a href="{{ url('employees') }}" class="btn btn-white d-inline-flex align-items-center">
                            <i class="ti ti-users me-1"></i>Directory karyawan
                        </a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top"
                            data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-0">Ringkasan kompensasi</h5>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <input type="search" class="form-control form-control-sm" style="min-width: 200px;"
                            placeholder="Cari nama / email…" data-hcm-employee-salary-search maxlength="100">
                        <select class="form-select form-select-sm" style="width: auto; min-width: 140px;"
                            data-hcm-employee-salary-status>
                            <option value="">Semua status kerja</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                            <option value="resigned">Resign</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Telepon</th>
                                    <th>Jabatan</th>
                                    <th>Tim</th>
                                    <th>Departemen</th>
                                    <th>Bergabung</th>
                                    <th class="text-end">Gaji pokok</th>
                                    <th class="text-end">Tunj. tetap</th>
                                    <th class="text-end">Dasar / bln</th>
                                    <th>Permanent / contract</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-hcm-employee-salary-body>
                                <tr>
                                    <td colspan="13" class="text-center text-muted py-4">Memuat data…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap py-3 gap-2"
                    data-hcm-employee-salary-pagination style="display: none;">
                    <span class="text-muted small" data-hcm-employee-salary-page-info></span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-hcm-employee-salary-prev>Sebelumnya</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-hcm-employee-salary-next>Berikutnya</button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @include('hcm.partials.employee-salary-compensation-modal')
@endsection
