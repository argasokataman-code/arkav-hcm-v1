<?php $page = 'log-viewer'; ?>
@php
    $isGlobalHcmAdmin = (bool) ((request()->user() ?: auth()->user())?->isGlobalHcmAdmin());
@endphp

@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper">
    <div class="content" data-log-viewer-page>
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Log Viewer</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Administration</li>
                        <li class="breadcrumb-item active" aria-current="page">System Logs</li>
                    </ol>
                </nav>
            </div>
        </div>

        <ul class="nav nav-tabs nav-tabs-solid bg-transparent border-bottom mb-3">
            <li class="nav-item"><a class="nav-link" href="{{ url('profile-settings') }}"><i class="ti ti-settings me-2"></i>General Settings</a></li>
            @if ($isGlobalHcmAdmin)
            <li class="nav-item"><a class="nav-link" href="{{ url('business-settings') }}"><i class="ti ti-world-cog me-2"></i>Website Settings</a></li>
            @endif
            <li class="nav-item"><a class="nav-link active" href="{{ url('email-settings') }}"><i class="ti ti-server-cog me-2"></i>System Settings</a></li>
        </ul>

        <div class="row">
            <div class="col-xl-3 theiaStickySidebar">
                <div class="card"><div class="card-body"><div class="d-flex flex-column list-group settings-list">
                    <a href="{{ url('email-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3"><i class="ti ti-mail-cog me-2"></i>Email Settings</a>
                    <a href="{{ url('notification-observability') }}" class="d-inline-flex align-items-center rounded py-2 px-3"><i class="ti ti-bell-ringing me-2"></i>Notification Observability</a>
                    <a href="{{ url('log-viewer') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-file-text me-2"></i>Log Viewer</a>
                </div></div></div>
            </div>
            <div class="col-xl-9">
                <div class="alert d-none mb-3" data-log-viewer-feedback></div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <h4 class="mb-0">Runtime Logs</h4>
                            <button type="button" class="btn btn-outline-light border" data-log-viewer-refresh>
                                <i class="ti ti-refresh me-1"></i>Refresh
                            </button>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <div class="border rounded p-3">
                                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Log Files</h6>
                                    <div class="list-group list-group-flush" data-log-viewer-file-list></div>
                                    <div class="small text-muted text-center py-3 d-none" data-log-viewer-file-empty>No log files found.</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-8">
                                <div class="border rounded p-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                        <h6 class="mb-0" data-log-viewer-file-title>Select a log file</h6>
                                        <span class="small text-muted" data-log-viewer-file-meta></span>
                                    </div>
                                    <div class="table-responsive" style="max-height:60vh;overflow-y:auto;">
                                        <table class="table table-sm table-striped mb-0 d-none" data-log-viewer-entries-table>
                                            <thead><tr>
                                                <th style="width:170px;">Time</th>
                                                <th style="width:80px;">Level</th>
                                                <th>Message</th>
                                            </tr></thead>
                                            <tbody data-log-viewer-entries-body></tbody>
                                        </table>
                                        <div class="text-center text-muted py-5" data-log-viewer-entries-empty>Pilih file log di samping untuk melihat entri.</div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between gap-2 mt-2 d-none" data-log-viewer-pagination>
                                        <small class="text-muted" data-log-viewer-pagination-info></small>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-log-viewer-prev>&larr; Prev</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-log-viewer-next>Next &rarr;</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('components.modals.log-viewer-entry')
@endsection

@if (Route::is(['log-viewer']))
    <script src="{{ URL::asset('build/js/settings/log-viewer-data.js') }}?v={{ filemtime(public_path('build/js/settings/log-viewer-data.js')) }}"></script>
@endif
