<?php $page = 'document-center'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Document Center</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Employee</li>
                            <li class="breadcrumb-item active" aria-current="page">Document Center</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="mb-2 me-2" id="docCenterAdminActions" style="display:none !important">
                        <button type="button" class="btn btn-primary d-flex align-items-center"
                            data-bs-toggle="modal" data-bs-target="#arcav_doc_upload_modal">
                            <i class="ti ti-upload me-2"></i>Upload Document
                        </button>
                    </div>
                    <div class="mb-2 me-2" id="docCenterCategoryBtn" style="display:none !important">
                        <button type="button" class="btn btn-outline-secondary d-flex align-items-center"
                            data-bs-toggle="modal" data-bs-target="#arcav_doc_category_modal">
                            <i class="ti ti-tag me-2"></i>Categories
                        </button>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top"
                            data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Document List -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">Document List</h5>
                        <div class="text-muted fs-12">Dokumen personal, kontrak, dan arsip employee.</div>
                    </div>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-2">
                        <select class="form-select me-2" style="min-width: 180px" id="docCenterCategoryFilter">
                            <option value="">Category (All)</option>
                        </select>
                        <select class="form-select me-2" style="min-width: 180px" id="docCenterVisibilityFilter">
                            <option value="">Visibility (All)</option>
                            <option value="hr_only">HR Only</option>
                            <option value="employee_visible">Employee Visible</option>
                        </select>
                        <div class="input-icon-start me-2">
                            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                            <input type="text" class="form-control" placeholder="Cari judul / nama file..." id="docCenterSearch">
                        </div>
                        <button type="button" class="btn btn-white" id="docCenterReload">
                            <i class="ti ti-refresh me-1"></i>Reload
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Employee</th>
                                    <th>Category</th>
                                    <th>File</th>
                                    <th>Visibility</th>
                                    <th>Expires</th>
                                    <th>Uploaded</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="docCenterTbody">
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Memuat dokumen...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex align-items-center justify-content-between p-3" id="docCenterPagination" style="display:none !important">
                        <div class="text-muted fs-12" id="docCenterPaginationInfo"></div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-white" id="docCenterPrevPage" disabled>
                                <i class="ti ti-chevron-left"></i>
                            </button>
                            <button class="btn btn-sm btn-white" id="docCenterNextPage" disabled>
                                <i class="ti ti-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Document List -->

        </div>
    </div>
    <!-- /Page Wrapper -->

    @include('hcm.partials.document-center-modals')

@endsection
