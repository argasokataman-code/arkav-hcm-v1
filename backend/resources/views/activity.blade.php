<?php $page = 'activity'; ?>
@extends('layout.mainlayout')
@section('content')

   <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Activity</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                CRM
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Activity List</li>
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
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1"><i class="ti ti-file-type-pdf me-1"></i>Export as PDF</a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1"><i class="ti ti-file-type-xls me-1"></i>Export as Excel </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center" data-activity-add>
                            <i class="ti ti-circle-plus me-2"></i>Add Activity
                        </button>
                    </div>
                    <div class="ms-2 head-icons">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Leads List -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>Activity List</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <div class="me-3" data-activity-company-select-wrap style="display: none;">
                            <select class="form-select" data-activity-company>
                                <option value="">Select Company</option>
                            </select>
                        </div>
                        <div class="me-3">
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Search activity"
                                data-activity-search
                            >
                        </div>
                        <div class="dropdown me-3">
                            <select class="form-select" data-activity-type>
                                <option value="all">All Types</option>
                                <option value="asset">Asset</option>
                                <option value="user_access">User Access</option>
                                <option value="payroll">Payroll</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                        <div class="dropdown me-3">
                            <select class="form-select" data-activity-source>
                                <option value="all">All Sources</option>
                                <option value="manual">Manual</option>
                                <option value="system">System</option>
                            </select>
                        </div>
                        <div class="dropdown me-3">
                            <select class="form-select" data-activity-status>
                                <option value="all">All Statuses</option>
                                <option value="created">Created</option>
                                <option value="updated">Updated</option>
                                <option value="deleted">Deleted</option>
                                <option value="assigned">Assigned</option>
                                <option value="revoked">Revoked</option>
                                <option value="calculated">Calculated</option>
                                <option value="finalized">Finalized</option>
                                <option value="void">Void</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Activity Type</th>
                                    <th>Source</th>
                                    <th>Company</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Owner</th>
                                    <th>Created Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody data-activity-body>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">Loading activity...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex align-items-center justify-content-between p-3 border-top" data-activity-pagination-wrap>
                        <small class="text-muted" data-activity-page-info>Page 1</small>
                        <div class="btn-group">
                            <button type="button" class="btn btn-white btn-sm" data-activity-prev>Prev</button>
                            <button type="button" class="btn btn-white btn-sm" data-activity-next>Next</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Leads List -->

        </div>

    

    </div>
    <!-- /Page Wrapper -->

    <div class="modal fade" id="manual_activity_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" data-manual-activity-modal-title>Add Manual Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form data-manual-activity-form>
                    <div class="modal-body">
                        <input type="hidden" data-manual-activity-id>
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" data-manual-activity-title maxlength="255" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kind <span class="text-danger">*</span></label>
                                <select class="form-select" data-manual-activity-kind required>
                                    <option value="task">Task</option>
                                    <option value="call">Call</option>
                                    <option value="email">Email</option>
                                    <option value="meeting">Meeting</option>
                                    <option value="note">Note</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" data-manual-activity-status required>
                                    <option value="planned">Planned</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" data-manual-activity-due-date>
                        </div>
                        <div class="alert alert-danger d-none mb-0" data-manual-activity-error></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-manual-activity-submit>Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @component('components.modal-popup')
    @endcomponent

@endsection