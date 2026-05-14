<?php $page = 'teams'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        [data-team-members-body]:not([data-hydrated="1"]) {
            display: none;
        }

        .team-members-summary-grid .card {
            border: 1px solid var(--bs-border-color);
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        }

        .team-members-summary-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--bs-secondary-color);
            margin-bottom: 0.35rem;
        }

        .team-members-summary-value {
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.35;
            margin-bottom: 0;
            color: var(--bs-body-color);
        }

        .team-members-summary-value.is-total {
            font-size: 1.55rem;
            line-height: 1;
        }

        .team-members-toolbar {
            gap: 0.65rem;
        }

        .team-members-toolbar .form-control,
        .team-members-toolbar .form-select {
            min-width: 160px;
        }

        .team-members-toolbar .team-members-search {
            min-width: 260px;
            flex: 1 1 260px;
        }

        @media (max-width: 767.98px) {
            .team-members-toolbar .form-control,
            .team-members-toolbar .form-select,
            .team-members-toolbar .btn {
                width: 100%;
            }

            .team-members-toolbar .team-members-search {
                min-width: 100%;
            }
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

            <div class="row g-3 mb-3 team-members-summary-grid">
                <div class="col-12 col-sm-6 col-xl-3 d-flex">
                    <div class="card w-100 h-100">
                        <div class="card-body py-3">
                            <p class="team-members-summary-label">Team Name</p>
                            <p class="team-members-summary-value" data-team-members-team-name>-</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3 d-flex">
                    <div class="card w-100 h-100">
                        <div class="card-body py-3">
                            <p class="team-members-summary-label">Department</p>
                            <p class="team-members-summary-value" data-team-members-team-department>-</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3 d-flex">
                    <div class="card w-100 h-100">
                        <div class="card-body py-3">
                            <p class="team-members-summary-label">Team Lead</p>
                            <p class="team-members-summary-value" data-team-members-team-lead>-</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3 d-flex">
                    <div class="card w-100 h-100">
                        <div class="card-body py-3">
                            <p class="team-members-summary-label">Total Members</p>
                            <p class="team-members-summary-value is-total" data-team-members-total>0</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <h5 class="mb-0">Members Directory</h5>
                                <button type="button" class="btn btn-primary" data-team-members-assign-open>
                                    <i class="ti ti-user-plus me-1"></i>Assign Members
                                </button>
                            </div>
                            <div class="d-flex align-items-center flex-wrap team-members-toolbar">
                                <input type="text" class="form-control team-members-search" placeholder="Search name/email/NIK" data-team-members-search>
                                <select class="form-select" data-team-members-status>
                                    <option value="all">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="probation">Probation</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <select class="form-select" data-team-members-per-page>
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
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody data-team-members-body>
                                        <tr><td class="text-center text-muted py-4" colspan="7">Loading...</td></tr>
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

    <div class="modal fade" id="team_members_assign_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Members To Team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form data-team-members-assign-form>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Employees</label>
                            <select class="form-select" multiple data-team-members-assign-users></select>
                            <small class="text-muted d-block mt-1">Ketik nama/email untuk mencari. Employee yang sudah berada di team ini otomatis tidak ditampilkan.</small>
                        </div>
                        <div class="alert d-none mb-0" data-team-members-assign-result></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-team-members-assign-submit>Assign Selected</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('build/js/employees/team-master-data.js') }}"></script>
    @endpush
@endsection
