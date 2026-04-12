<?php $page = 'leave-report'; ?>
@extends('layout.mainlayout')
@section('content')
<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Leave Report</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            HR
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Leave Report</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                <div class="mb-2">
                    <span class="text-muted small d-inline-flex align-items-center"><i class="ti ti-file-export me-1"></i>Export menyusul.</span>
                </div>
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->
        <div class="alert alert-light border text-muted small mb-3">
            Ringkasan angka dan grafik template telah dihapus. Data akan mengikuti API laporan cuti.
        </div>
        <div class="card mb-3">
            <div class="card-body py-3">
                <div id="leave-report-chart-placeholder" class="rounded border border-dashed text-muted small d-flex align-items-center justify-content-center" style="min-height: 200px;">
                    Grafik laporan cuti belum dihubungkan ke API (elemen template <code>#leave-report</code> tidak digunakan).
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <h5>Daftar cuti</h5>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                    <span class="text-muted small">Filter menyusul bersama API.</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="custom-datatable-filter table-responsive">
                    <table class="table">
                        <thead class="thead-light">
                            <tr>
                                <th>Employee</th>
                                <th>Leave type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Days</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody data-arcav-hrm-empty="1">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada data. Hubungkan API laporan cuti untuk menampilkan baris.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

 

</div>
<!-- /Page Wrapper -->
@endsection