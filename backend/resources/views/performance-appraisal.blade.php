<?php $page = 'performance-appraisal'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Performance Appraisal</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Performance
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Performance Appraisal</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <div class="mb-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_perf_cycle_modal">
                            <i class="ti ti-circle-plus me-2"></i>Add Cycle
                        </button>
                    </div>
                    <div class="mb-2">
                        <button type="button" class="btn btn-white d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_perf_review_create_modal">
                            <i class="ti ti-user-plus me-2"></i>Create Review
                        </button>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Cycles -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">Performance Cycles</h5>
                        <div class="text-muted fs-12">Admin buat cycle, aktifkan 1 cycle untuk periode berjalan.</div>
                    </div>
                    <button type="button" class="btn btn-white" data-arcav-perf-cycle-reload>
                        <i class="ti ti-refresh me-1"></i>Reload
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Cycle</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-arcav-perf-cycles-tbody>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Memuat cycles...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Reviews -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">Performance Reviews</h5>
                        <div class="text-muted fs-12">List review (scope: all/team/me) mengikuti role.</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <select class="form-select" style="min-width: 160px" data-arcav-perf-review-scope>
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
                        <table class="table mb-0" data-arcav-perf-reviews-mode="full">
                            <thead class="thead-light">
                                <tr>
                                    <th>Cycle</th>
                                    <th>Employee</th>
                                    <th>Manager</th>
                                    <th>Status</th>
                                    <th>Final score</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-arcav-perf-reviews-tbody>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Memuat reviews...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @include('hcm.partials.performance-modals')

        </div>


    </div>
    <!-- /Page Wrapper -->

    {{-- Modals are included per page via hcm.partials.performance-modals --}}

@endsection