<?php $page = 'teams'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        [data-team-members-body]:not([data-hydrated="1"]) {
            display: none;
        }
    </style>

    <div class="page-wrapper">
        <div class="content">
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1" data-team-members-title>Team Members</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Employee</li>
                            <li class="breadcrumb-item"><a href="{{ url('teams') }}">Teams</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Members</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="mb-2 me-2">
                        <a href="{{ url('teams') }}" class="btn btn-light d-inline-flex align-items-center">
                            <i class="ti ti-arrow-left me-1"></i>Back To Teams
                        </a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-xl-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-2">Team Summary</h6>
                            <p class="mb-1 text-muted small">Name</p>
                            <p class="mb-2" data-team-members-team-name>-</p>
                            <p class="mb-1 text-muted small">Department</p>
                            <p class="mb-2" data-team-members-team-department>-</p>
                            <p class="mb-1 text-muted small">Lead</p>
                            <p class="mb-2" data-team-members-team-lead>-</p>
                            <p class="mb-1 text-muted small">Members</p>
                            <p class="mb-0 fw-semibold" data-team-members-total>0</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-8">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                            <h5 class="mb-0">Members</h5>
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <input type="text" class="form-control" style="min-width: 220px;" placeholder="Search name/email/nik" data-team-members-search>
                                <select class="form-select" style="min-width: 170px;" data-team-members-status>
                                    <option value="all">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="probation">Probation</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <select class="form-select" style="min-width: 130px;" data-team-members-per-page>
                                    <option value="10">10 / page</option>
                                    <option value="20" selected>20 / page</option>
                                    <option value="50">50 / page</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="custom-datatable-filter table-responsive">
                                <table class="table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>NIK</th>
                                            <th>Department</th>
                                            <th>Designation</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody data-team-members-body>
                                        <tr><td class="text-center text-muted py-4" colspan="6">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
                                <small class="text-muted" data-team-members-showing>Showing 0 - 0 of 0 entries</small>
                                <ul class="pagination pagination-sm mb-0" data-team-members-pagination></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" value="{{ $teamId }}" data-team-members-id>

    @push('scripts')
    <script src="{{ asset('build/js/team-master-data.js') }}"></script>
    @endpush
@endsection
