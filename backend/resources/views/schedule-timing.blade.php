<?php $page = 'schedule-timing'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        [data-schedule-timing-body]:not([data-hydrated="1"]) {
            display: none;
        }
    </style>

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Schedule Timing</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Administration
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Schedule Timing</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="mb-2">
                        <a href="javascript:void(0);" class="btn btn-white d-inline-flex align-items-center" data-schedule-timing-export="csv">
                            <i class="ti ti-file-export me-1"></i>Export CSV
                        </a>
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
                    <h5>Schedule Timing List</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <div class="me-2">
                            <input type="text" class="form-control" placeholder="Search name / job title" data-schedule-timing-search>
                        </div>
                        <div>
                            <select class="form-select" data-schedule-timing-sort>
                                <option value="name_asc">Sort: Name A-Z</option>
                                <option value="name_desc">Sort: Name Z-A</option>
                                <option value="start_asc">Sort: Start earliest</option>
                                <option value="start_desc">Sort: Start latest</option>
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
                                    <th>Job Title</th>
                                    <th>User Available Timings</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-schedule-timing-body>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Loading schedule timings...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2" data-schedule-timing-pagination style="display: none;">
                    <span class="text-muted small" data-schedule-timing-page-info></span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light border" data-schedule-timing-prev>Sebelumnya</button>
                        <button type="button" class="btn btn-sm btn-light border" data-schedule-timing-next>Berikutnya</button>
                    </div>
                </div>
            </div>

        </div>


    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

    <div class="modal fade" id="arcav_schedule_timing_edit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set schedule timing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form data-schedule-timing-edit-form>
                    <div class="modal-body">
                        <p class="fw-medium mb-1" data-st-edit-employee>—</p>
                        <p class="text-muted small mb-3">Pilih shift master atau isi jam manual (custom).</p>
                        <input type="hidden" data-st-edit-user-id value="">
                        <div class="mb-3">
                            <label class="form-label">Shift master</label>
                            <select class="form-select" data-st-edit-shift>
                                <option value="">Custom (manual)</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Start</label>
                                <input type="time" class="form-control" data-st-edit-start required value="09:00">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">End</label>
                                <input type="time" class="form-control" data-st-edit-end required value="18:00">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-outline-danger d-none" data-st-edit-reset>Hapus override · kembali ke otomatis</button>
                        <div class="d-flex gap-2 ms-auto">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" data-st-edit-submit>Simpan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection