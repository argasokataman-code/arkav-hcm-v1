<?php $page = 'payroll-deduction'; ?>
@extends('layout.mainlayout')
@section('content')

    <div class="page-wrapper">
        <div class="content">

            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Payroll — Potongan</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">HR</li>
                            <li class="breadcrumb-item active" aria-current="page">Payroll / Potongan</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="mb-2">
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                <i class="ti ti-file-export me-1"></i>Export
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end p-3">
                                <li><a href="javascript:void(0);" class="dropdown-item rounded-1" data-payroll-items-export="csv"><i class="ti ti-file-type-pdf me-1"></i>Export as CSV</a></li>
                                <li><a href="javascript:void(0);" class="dropdown-item rounded-1" data-payroll-items-export="xlsx"><i class="ti ti-file-type-xls me-1"></i>Export as Excel</a></li>
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

            <div class="d-flex flex-wrap gy-2 justify-content-between my-4">
                @include('hcm.partials.payroll-section-tabs', ['payrollTab' => 'deductions'])
                <div class="mb-2">
                    <button type="button" class="btn btn-primary d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_payroll_item_add">
                        <i class="ti ti-circle-plus me-2"></i>Tambah item potongan
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">Payroll items — potongan (mapping)</h5>
                        <span class="text-muted small">CRUD komponen gaji langsung ada di <a href="{{ url('salary-component-master') }}">Master Komponen Salary</a>.</span>
                    </div>
                    <span class="text-muted small">Admin HCM</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Nama / kode</th>
                                    <th>Kategori</th>
                                    <th>Default / komponen</th>
                                    <th>Catatan</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody data-payroll-items-catalog-body>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Memuat…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @include('hcm.partials.payroll-item-modals')
    @component('components.modal-popup')
    @endcomponent
@endsection
