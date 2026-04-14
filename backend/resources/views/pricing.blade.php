<?php $page = 'pricing'; ?>
@extends('layout.mainlayout')
@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Pricing</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">SaaS</li>
                            <li class="breadcrumb-item active" aria-current="page">Pricing</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <a href="{{ url('packages') }}" class="btn btn-primary d-flex align-items-center mb-2">
                        <i class="ti ti-settings me-2"></i>Manage Plans
                    </a>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top"
                            data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body pb-1">
                    <div class="d-flex justify-content-center align-items-center mb-4">
                        <p class="mb-0 me-2">Monthly</p>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="pricing-billing-cycle-toggle" data-pricing-billing-toggle>
                        </div>
                        <p class="mb-0">Yearly</p>
                    </div>
                    <div class="row justify-content-center" data-pricing-cards>
                        <div class="col-12 text-center text-muted py-4">Loading plans...</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <p class="fs-12 fw-medium mb-1 text-truncate">Total Active Plans</p>
                                <h4 class="mb-0" data-pricing-total-plans>0</h4>
                            </div>
                            <span class="avatar avatar-lg bg-primary flex-shrink-0"><i class="ti ti-box fs-16"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <p class="fs-12 fw-medium mb-1 text-truncate">Total Active Subscribers</p>
                                <h4 class="mb-0" data-pricing-total-active-subscribers>0</h4>
                            </div>
                            <span class="avatar avatar-lg bg-success flex-shrink-0"><i class="ti ti-users fs-16"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <p class="fs-12 fw-medium mb-1 text-truncate">Avg Monthly Price</p>
                                <h4 class="mb-0" data-pricing-avg-monthly-price>Rp0</h4>
                            </div>
                            <span class="avatar avatar-lg bg-info flex-shrink-0"><i class="ti ti-cash fs-16"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <p class="fs-12 fw-medium mb-1 text-truncate">Avg Yearly Price</p>
                                <h4 class="mb-0" data-pricing-avg-yearly-price>Rp0</h4>
                            </div>
                            <span class="avatar avatar-lg bg-warning flex-shrink-0"><i class="ti ti-calendar fs-16"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>Plan Details</h5>
                    <div class="d-flex align-items-center gap-2">
                        <input type="search" class="form-control" style="min-width: 260px;" placeholder="Search plan name/code" data-pricing-search>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Plan</th>
                                    <th>Code</th>
                                    <th>Created Date</th>
                                    <th>Monthly</th>
                                    <th>Yearly</th>
                                    <th>Subscribers</th>
                                </tr>
                            </thead>
                            <tbody data-pricing-table-body>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Loading plans...</td>
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
@endsection
