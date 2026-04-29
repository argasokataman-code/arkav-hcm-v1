<?php $page = 'goal-tracking'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Goal Tracking</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Performance</li>
                            <li class="breadcrumb-item active" aria-current="page">Goal Tracking</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <div class="me-2 mb-2">
                        <div class="dropdown">
                            <button class="btn btn-white d-inline-flex align-items-center dropdown-toggle" data-bs-toggle="dropdown" type="button">
                                <i class="ti ti-file-export me-1"></i>Export
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end p-3">
                                <li>
                                    <button type="button" class="dropdown-item rounded-1" data-arcav-goal-export="csv">
                                        <i class="ti ti-file-type-csv me-1"></i>Export as CSV
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_goal_modal">
                            <i class="ti ti-circle-plus me-2"></i>Add Goal
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

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">Goal Tracking List</h5>
                        <div class="text-muted fs-12">Filter goal berdasarkan type, status, scope, dan keyword.</div>
                    </div>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-2">
                        <select class="form-select me-2" style="min-width: 140px" data-arcav-goal-scope>
                            <option value="me">Me</option>
                            <option value="team">Team</option>
                            <option value="all">All (Admin)</option>
                        </select>
                        <select class="form-select me-2" style="min-width: 160px" data-arcav-goal-type-filter>
                            <option value="">Goal Type (All)</option>
                        </select>
                        <select class="form-select me-2" style="min-width: 140px" data-arcav-goal-status-filter>
                            <option value="">Status (All)</option>
                            <option value="active">active</option>
                            <option value="inactive">inactive</option>
                            <option value="completed">completed</option>
                        </select>
                        <div class="me-2">
                            <input type="date" class="form-control" style="min-width: 160px" data-arcav-goal-start-date>
                        </div>
                        <div class="me-2">
                            <input type="date" class="form-control" style="min-width: 160px" data-arcav-goal-end-date>
                        </div>
                        <button type="button" class="btn btn-white me-2" data-arcav-goal-clear-dates>
                            <i class="ti ti-x me-1"></i>Clear date
                        </button>
                        <div class="input-icon-start me-2">
                            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                            <input type="text" class="form-control" placeholder="Search..." data-arcav-goal-q>
                        </div>
                        <button type="button" class="btn btn-white" data-arcav-goal-reload>
                            <i class="ti ti-refresh me-1"></i>Reload
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Goal Type</th>
                                    <th>Subject</th>
                                    <th>Target Achievement</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-arcav-goals-tbody>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Memuat goals...</td>
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

