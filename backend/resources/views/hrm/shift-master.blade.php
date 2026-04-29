<?php $page = 'shift-master'; ?>
@extends('layout.mainlayout')
@section('content')

    <div class="page-wrapper">
        <div class="content">

            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Master Shift</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Administration</li>
                            <li class="breadcrumb-item active" aria-current="page">Master Shift</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="me-2 mb-2">
                        <a href="{{ url('schedule-timing') }}" class="btn btn-white d-inline-flex align-items-center">
                            <i class="ti ti-calendar-time me-1"></i>Schedule Timing
                        </a>
                    </div>
                    <div class="mb-2">
                        <a href="javascript:void(0);" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_add_shift">
                            <i class="ti ti-circle-plus me-2"></i>Add Shift
                        </a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>Shift definitions</h5>
                    <span class="text-muted small">Digunakan saat override jadwal di Schedule Timing.</span>
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
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Slot</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-hcm-shifts-body>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Loading…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @component('components.modal-popup')
    @endcomponent

    @include('hcm.partials.shift-modals')

@endsection
