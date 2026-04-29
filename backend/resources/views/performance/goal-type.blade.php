<?php $page = 'goal-type'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Goal Type</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Performance</li>
                            <li class="breadcrumb-item active" aria-current="page">Goal Type</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <div class="mb-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_goal_type_modal">
                            <i class="ti ti-circle-plus me-2"></i>Add Goal Type
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

            <!-- Goal Type list (shell; rendered by JS) -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">Goal Types</h5>
                        <div class="text-muted fs-12">Master goal types untuk dipakai di Goal Tracking.</div>
                    </div>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-2">
                        <div class="input-icon-start me-2">
                            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                            <input type="text" class="form-control" placeholder="Cari goal type..." data-arcav-goal-type-search>
                        </div>
                        <button type="button" class="btn btn-white" data-arcav-goal-type-reload>
                            <i class="ti ti-refresh me-1"></i>Reload
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-arcav-goal-types-tbody>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Memuat goal types...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @include('hcm.partials.goal-modals')
        </div>
    </div>
    <!-- /Page Wrapper -->

@endsection

