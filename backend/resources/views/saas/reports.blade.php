@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/saas">SaaS</a></li>
                    <li class="breadcrumb-item active">Reports</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Billing Reports</h4>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="report-type" class="form-label">Report Type</label>
                            <select id="report-type" class="form-select">
                                <option value="revenue">Revenue Report</option>
                                <option value="aging">Aging Report</option>
                                <option value="churn">Churn Report</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="period-filter" class="form-label">Period</label>
                            <select id="period-filter" class="form-select">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="report-container">
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>

    <div id="toast-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 11"></div>
</div>

@push('scripts')
<script src="{{ asset('js/reports-management.js') }}?v={{ time() }}"></script>
@endpush
@endsection
