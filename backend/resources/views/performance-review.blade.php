<?php $page = 'performance-review'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Performance Review</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Performance</li>
                            <li class="breadcrumb-item active" aria-current="page">Performance Review</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="javascript:void(0);" class="btn btn-light btn-sm d-inline-flex align-items-center"
                       data-bs-toggle="modal" data-bs-target="#arcav_perf_review_guide">
                        <i class="ti ti-info-circle me-1"></i>Panduan pemakaian
                    </a>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <div class="row">
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                            <div>
                                <h5 class="mb-1">My / Team Reviews</h5>
                                <div class="text-muted fs-12">Pilih review untuk mengisi self review / manager review.</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <select class="form-select" style="min-width: 140px" data-arcav-perf-review-scope>
                                    <option value="me">Me</option>
                                    <option value="team">Team</option>
                                    <option value="all">All (Admin)</option>
                                </select>
                                <button type="button" class="btn btn-white" data-arcav-perf-review-reload>
                                    <i class="ti ti-refresh me-1"></i>Reload
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0" data-arcav-perf-reviews-mode="compact">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Cycle</th>
                                            <th>Employee</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody data-arcav-perf-reviews-tbody>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">Memuat reviews...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                            <div>
                                <h5 class="mb-1">Review Detail</h5>
                                <div class="text-muted fs-12">Isi skor KPI (0–100) + behavioral (1–5), lalu proses sesuai role.</div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <button type="button" class="btn btn-white disabled" data-arcav-perf-review-refresh-detail>
                                    <i class="ti ti-refresh me-1"></i>Refresh
                                </button>
                                <button type="button" class="btn btn-primary disabled" data-arcav-perf-review-primary-action>
                                    Save
                                </button>
                                <button type="button" class="btn btn-success disabled" data-arcav-perf-review-secondary-action>
                                    Next
                                </button>
                            </div>
                        </div>
                        <div class="card-body" data-arcav-perf-review-detail>
                            <div class="text-center text-muted py-5">Pilih review di kiri untuk mulai.</div>
                        </div>
                    </div>
                </div>
            </div>

            @include('hcm.partials.performance-modals')

        </div>
    </div>
    <!-- /Page Wrapper -->

@endsection

