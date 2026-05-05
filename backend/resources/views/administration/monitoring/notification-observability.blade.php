<?php $page = 'notification-observability'; ?>
@extends('layout.mainlayout')
@section('content')

<div class="page-wrapper">
    <div class="content">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Notification Observability</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Notifications</li>
                        <li class="breadcrumb-item active" aria-current="page">Observability</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ url('notification-settings') }}" class="btn btn-light">Back to Settings</a>
                <button type="button" class="btn btn-secondary" data-notification-observability-export>Export CSV</button>
                <button type="button" class="btn btn-primary" data-notification-observability-refresh>Refresh</button>
            </div>
        </div>

        <div class="card" data-notification-observability-page>
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom pb-3 mb-3">
                    <div>
                        <h4 class="mb-1">Delivery Summary</h4>
                        <p class="text-muted mb-0">Pantau pengiriman notifikasi ke semua channel (Email, SMS, Database, Webhook) secara real-time.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <select class="form-select" data-notification-observability-hours>
                            <option value="24" selected>Last 24h</option>
                            <option value="72">Last 72h</option>
                            <option value="168">Last 7d</option>
                        </select>
                        <select class="form-select" data-notification-observability-channel>
                            <option value="">All channels</option>
                            <option value="database">Database</option>
                            <option value="mail">Mail</option>
                            <option value="sms">SMS</option>
                            <option value="webhook">Webhook</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block mb-1">Total Events</small>
                            <h4 class="mb-0" data-observability-total-all>0</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block mb-1">Sent</small>
                            <h4 class="mb-0 text-success" data-observability-total-sent>0</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block mb-1">Failed</small>
                            <h4 class="mb-0 text-danger" data-observability-total-failed>0</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block mb-1">Dropped</small>
                            <h4 class="mb-0 text-warning" data-observability-total-dropped>0</h4>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-2">Breakdown by Status</h6>
                            <div data-observability-status-breakdown class="small text-muted">No data</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-2">Top Failed Events</h6>
                            <div data-observability-top-failed class="small text-muted">No data</div>
                        </div>
                    </div>
                </div>

                <small class="text-muted d-block mt-3" data-observability-last-updated>Last updated: -</small>
            </div>
        </div>
    </div>
</div>
<!-- Drilldown Modal -->
<div class="modal fade" id="failedEventDrilldownModal" tabindex="-1" aria-labelledby="failedEventDrilldownLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="failedEventDrilldownLabel">Failed Event Details</h5>
                    <small class="text-muted" data-drilldown-event-key></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div data-drilldown-content>
                    <div class="row g-3">
                        <!-- Event Metadata -->
                        <div class="col-md-6">
                            <div class="border-bottom pb-2">
                                <label class="form-label small text-muted">Event Key</label>
                                <p class="mb-0 fw-semibold" data-drilldown-event-key-value>-</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-bottom pb-2">
                                <label class="form-label small text-muted">Channel</label>
                                <p class="mb-0 fw-semibold" data-drilldown-channel>-</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-bottom pb-2">
                                <label class="form-label small text-muted">Status</label>
                                <span class="badge bg-danger" data-drilldown-status>failed</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-bottom pb-2">
                                <label class="form-label small text-muted">Attempt Count</label>
                                <p class="mb-0 fw-semibold" data-drilldown-attempt-count>-</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-bottom pb-2">
                                <label class="form-label small text-muted">Recipient</label>
                                <p class="mb-0 fw-semibold" data-drilldown-recipient>-</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-bottom pb-2">
                                <label class="form-label small text-muted">Created At</label>
                                <p class="mb-0 fw-semibold" data-drilldown-created-at>-</p>
                            </div>
                        </div>

                        <!-- Error Details -->
                        <div class="col-12">
                            <div class="border-top pt-3">
                                <label class="form-label small text-muted">Last Error</label>
                                <div class="alert alert-warning small" role="alert" data-drilldown-last-error>
                                    No error recorded.
                                </div>
                            </div>
                        </div>

                        <!-- Metadata JSON -->
                        <div class="col-12">
                            <div class="border-top pt-3">
                                <label class="form-label small text-muted">Metadata</label>
                                <pre class="bg-light p-2 rounded small" style="max-height: 200px; overflow-y: auto;" data-drilldown-metadata>{}</pre>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border-top pt-3">
                                <label class="form-label small text-muted">Retry Audit Trail</label>
                                <div class="small text-muted" data-drilldown-retry-log>No manual retries recorded.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="flex-grow-1" data-drilldown-pagination></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" data-drilldown-retry-btn>
                    <i class="ti ti-reload"></i> Retry Now
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
