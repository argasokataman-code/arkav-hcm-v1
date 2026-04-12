<?php $page = 'trainers'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Trainers</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Performance
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Trainers</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="mb-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_trainer_modal">
                            <i class="ti ti-circle-plus me-2"></i>Add Trainer
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

            <!-- Trainers list (shell; rendered by JS) -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">Trainers List</h5>
                        <div class="text-muted fs-12">Phase 1: dikelola oleh HCM Admin.</div>
                    </div>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-2">
                        <select class="form-select me-2" style="min-width: 160px" data-arcav-trainer-status>
                            <option value="">Status (All)</option>
                            <option value="active">active</option>
                            <option value="inactive">inactive</option>
                        </select>
                        <div class="input-icon-start me-2">
                            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                            <input type="text" class="form-control" placeholder="Search name/email/phone..." data-arcav-trainer-q>
                        </div>
                        <button type="button" class="btn btn-white" data-arcav-trainer-reload>
                            <i class="ti ti-refresh me-1"></i>Reload
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-arcav-trainers-tbody>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Memuat trainers...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- /Trainers list -->

        </div>

    

    </div>
    <!-- /Page Wrapper -->

    @include('hcm.partials.trainer-modals')

@endsection