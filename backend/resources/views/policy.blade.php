<?php $page = 'policy'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        [data-policies-body]:not([data-hydrated="1"]) {
            display: none;
        }
        .policy-file-preview {
            min-height: 4.5rem;
        }
        .policy-file-preview img.policy-preview-thumb {
            object-fit: cover;
        }
    </style>

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Policies</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                HR
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Policies</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="me-2 mb-2">
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                <i class="ti ti-file-export me-1"></i>Export
                            </a>
                            <ul class="dropdown-menu  dropdown-menu-end p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1" data-hcm-export="pdf" data-hcm-export-module="policies"><i class="ti ti-file-type-pdf me-1"></i>Export as PDF</a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1" data-hcm-export="xlsx" data-hcm-export-module="policies"><i class="ti ti-file-type-xls me-1"></i>Export as Excel </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="me-2 mb-2">
                        <a href="javascript:void(0);" class="btn btn-light d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_policy_guide">
                            <i class="ti ti-info-circle me-1"></i>Panduan kebijakan
                        </a>
                    </div>
                    <div class="mb-2">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#add_policy" class="btn btn-primary d-flex align-items-center"><i class="ti ti-circle-plus me-2"></i>Add Policy</a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Policy list -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>Policies List</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <div class="me-3">
                            <div class="input-icon-end position-relative">
                                <input type="text" class="form-control" data-hcm-search-input="policies" placeholder="Search policies...">
                                <span class="input-icon-addon">
                                    <i class="ti ti-search"></i>
                                </span>
                            </div>
                        </div>
                        <div class="me-3">
                            <select class="form-select" data-hcm-policy-department-filter>
                                <option value="">All Departments</option>
                            </select>
                        </div>
                        <div>
                            <select class="form-select" data-hcm-per-page="policies">
                                <option value="10">10 / page</option>
                                <option value="20" selected>20 / page</option>
                                <option value="50">50 / page</option>
                                <option value="100">100 / page</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th class="no-sort">
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox" id="select-all">
                                        </div>
                                    </th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Description</th>
                                    <th>Appraisal date</th>
                                    <th>Attachment</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-policies-body>
                                <tr><td class="text-center text-muted py-4">Loading...</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top" data-hcm-pagination-wrap="policies">
                        <small class="text-muted" data-hcm-showing="policies">Showing 0 - 0 of 0 entries</small>
                        <ul class="pagination pagination-sm mb-0" data-hcm-pagination="policies"></ul>
                    </div>
                </div>
            </div>
            <!-- /Policylist list -->

        </div>

     

    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

    <div class="modal fade" id="arcav_policy_guide" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Panduan pengelolaan kebijakan HR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        Halaman ini digunakan untuk menyimpan <strong>dokumen kebijakan</strong> yang berlaku di perusahaan,
                        baik untuk seluruh departemen maupun untuk departemen tertentu. Policy di sini bukan struktur organisasi baru,
                        melainkan catatan aturan/dokumen yang menjadi rujukan operasional HR.
                    </p>

                    <div class="alert alert-light border mb-3">
                        <div class="fw-semibold mb-1">Arti field utama</div>
                        <ul class="mb-0 small text-muted ps-3">
                            <li><strong>Name</strong>: judul kebijakan yang akan tampil di daftar dan hasil pencarian.</li>
                            <li><strong>Department</strong>: scope penerapan policy. Jika kosong, policy diperlakukan sebagai kebijakan umum lintas departemen.</li>
                            <li><strong>Description</strong>: ringkasan isi kebijakan atau konteks implementasi.</li>
                            <li><strong>Appraisal date</strong>: saat ini dipakai sebagai tanggal mulai berlaku policy di data sistem.</li>
                            <li><strong>Attachment</strong>: file pendukung seperti PDF kebijakan, formulir, atau lampiran sosialisasi.</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning border mb-0">
                        <div class="fw-semibold mb-1">Catatan penggunaan</div>
                        <p class="mb-1 small">Pisahkan policy per topik agar pencarian, audit, dan pembaruan versi lebih mudah ditelusuri.</p>
                        <p class="mb-0 small">Jika kebijakan hanya berlaku untuk satu fungsi kerja, pilih departemen terkait agar penerapannya jelas.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

@endsection