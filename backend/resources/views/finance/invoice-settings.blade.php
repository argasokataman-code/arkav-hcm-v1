<?php $page = 'invoice-settings'; ?>
@php
    $isGlobalHcmAdmin = (bool) ((request()->user() ?: auth()->user())?->isGlobalHcmAdmin());
@endphp

@extends('layout.mainlayout')
@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Settings</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            Administration
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Settings</li>
                    </ol>
                </nav>
            </div>
            <div class="head-icons ms-2">
                <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                    <i class="ti ti-chevrons-up"></i>
                </a>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <ul class="nav nav-tabs nav-tabs-solid bg-transparent border-bottom mb-3">
            <li class="nav-item">
                <a class="nav-link" href="{{ url('profile-settings') }}"><i class="ti ti-settings me-2"></i>General Settings</a>
            </li>
            @if ($isGlobalHcmAdmin)
            <li class="nav-item">
                <a class="nav-link" href="{{ url('business-settings') }}"><i class="ti ti-world-cog me-2"></i>Website Settings</a>
            </li>
            @endif
            <li class="nav-item">
                <a class="nav-link active" href="{{ url('approval-settings') }}"><i class="ti ti-device-ipad-horizontal-cog me-2"></i>App Settings</a>
            </li>
            @if ($isGlobalHcmAdmin)
            <li class="nav-item">
                <a class="nav-link" href="{{ url('email-settings') }}"><i class="ti ti-server-cog me-2"></i>System Settings</a>
            </li>
            @endif
            @if ($isGlobalHcmAdmin)
            <li class="nav-item">
            </li>
            @endif
            @if ($isGlobalHcmAdmin)
            <li class="nav-item">
                <a class="nav-link" href="{{ url('custom-css') }}"><i class="ti ti-settings-2 me-2"></i>Other Settings</a>
            </li>
            @endif
        </ul>
        <div class="row">
            <div class="col-xl-3 theiaStickySidebar">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column list-group settings-list">
                            <a href="{{ url('approval-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Approval Settings</a>
                            <a href="{{ url('invoice-settings') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Invoice Settings</a>
                            <a href="{{ url('leave-type') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Leave Type</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9">
                <!-- Feedback alert -->
                <div class="alert d-none mb-3" role="alert" data-invoice-settings-feedback></div>

                <!-- Loading skeleton (shown while fetching) -->
                <div class="card" data-invoice-settings-loading>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-muted">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                            <span>Loading invoice settings…</span>
                        </div>
                    </div>
                </div>

                <!-- Settings form (hidden until loaded) -->
                <div class="card d-none" data-invoice-settings-panel>
                    <div class="card-body">
                        <div class="border-bottom mb-3 pb-3">
                            <h4>Invoice Settings</h4>
                            <p class="text-muted fs-13 mb-0">Configure invoice defaults for your company. Changes apply to all new invoices generated.</p>
                        </div>
                        <form data-invoice-settings-form novalidate>
                            <div class="border-bottom mb-4 pb-2">

                                <!-- Invoice Prefix -->
                                <div class="row align-items-center mb-3">
                                    <div class="col-xl-3 col-md-4">
                                        <label class="form-label fw-semibold mb-1">Invoice Prefix</label>
                                        <p class="text-muted fs-12 mb-0">Prefix added before invoice numbers (e.g. INV-)</p>
                                    </div>
                                    <div class="col-xl-4 col-md-5">
                                        <input type="text" class="form-control" maxlength="20"
                                               placeholder="INV-"
                                               data-invoice-field="invoice_prefix"
                                               data-invoice-settings-input />
                                    </div>
                                </div>

                                <!-- Invoice Due Days -->
                                <div class="row align-items-center mb-3">
                                    <div class="col-xl-3 col-md-4">
                                        <label class="form-label fw-semibold mb-1">Default Due Days</label>
                                        <p class="text-muted fs-12 mb-0">Number of days until invoice is due after issue date</p>
                                    </div>
                                    <div class="col-xl-4 col-md-5">
                                        <select class="form-select" data-invoice-field="invoice_due_days" data-invoice-settings-input>
                                            <option value="7">7 Days</option>
                                            <option value="14">14 Days</option>
                                            <option value="30">30 Days</option>
                                            <option value="45">45 Days</option>
                                            <option value="60">60 Days</option>
                                            <option value="90">90 Days</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Invoice Round Off -->
                                <div class="row align-items-center mb-3">
                                    <div class="col-xl-3 col-md-4">
                                        <label class="form-label fw-semibold mb-1">Round Off Method</label>
                                        <p class="text-muted fs-12 mb-0">How invoice totals are rounded</p>
                                    </div>
                                    <div class="col-xl-3 col-md-4">
                                        <select class="form-select" data-invoice-field="invoice_round_off" data-invoice-settings-input>
                                            <option value="none">No Rounding</option>
                                            <option value="round_up">Round Up</option>
                                            <option value="round_down">Round Down</option>
                                        </select>
                                    </div>
                                    <div class="col-xl-3 col-md-4 d-flex align-items-center gap-2">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="invoiceRoundOffSwitch"
                                                   data-invoice-field="invoice_round_off_enabled"
                                                   data-invoice-settings-toggle />
                                            <label class="form-check-label" for="invoiceRoundOffSwitch">Enable</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Show Tax -->
                                <div class="row align-items-center mb-3">
                                    <div class="col-xl-3 col-md-4">
                                        <label class="form-label fw-semibold mb-1">Show Tax on Invoice</label>
                                        <p class="text-muted fs-12 mb-0">Display tax line on generated invoices and PDFs</p>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="invoiceShowTaxSwitch"
                                                   data-invoice-field="invoice_show_tax"
                                                   data-invoice-settings-toggle />
                                            <label class="form-check-label" for="invoiceShowTaxSwitch">Enable</label>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- Header Terms -->
                            <div class="row align-items-start mb-3">
                                <div class="col-xl-3 col-md-4">
                                    <label class="form-label fw-semibold mb-1">Invoice Header Terms</label>
                                    <p class="text-muted fs-12 mb-0">Displayed at the top of each invoice</p>
                                </div>
                                <div class="col-md-8">
                                    <textarea class="form-control" rows="4" maxlength="2000"
                                              placeholder="Enter header terms and conditions…"
                                              data-invoice-field="invoice_header_terms"
                                              data-invoice-settings-input></textarea>
                                </div>
                            </div>

                            <!-- Footer Terms -->
                            <div class="row align-items-start mb-4">
                                <div class="col-xl-3 col-md-4">
                                    <label class="form-label fw-semibold mb-1">Invoice Footer Terms</label>
                                    <p class="text-muted fs-12 mb-0">Displayed at the bottom of each invoice</p>
                                </div>
                                <div class="col-md-8">
                                    <textarea class="form-control" rows="4" maxlength="2000"
                                              placeholder="Enter footer notes or payment instructions…"
                                              data-invoice-field="invoice_footer_terms"
                                              data-invoice-settings-input></textarea>
                                </div>
                            </div>

                            <div class="border-top pt-3 mt-3" data-invoice-documents-section>
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <div>
                                        <h5 class="mb-1">Invoice Documents</h5>
                                        <p class="text-muted fs-12 mb-0">List dokumen invoice yang tersedia di tenant ini. Atur status active dan gunakan preview tanpa hardcode.</p>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-1">
                                        <thead>
                                            <tr>
                                                <th>Document</th>
                                                <th>Template</th>
                                                <th class="text-center">Generated</th>
                                                <th>Latest</th>
                                                <th class="text-center">Active</th>
                                                <th class="text-end">Preview</th>
                                            </tr>
                                        </thead>
                                        <tbody data-invoice-documents-list>
                                            <tr data-invoice-documents-empty>
                                                <td colspan="6" class="text-muted">No invoice documents found yet for this tenant.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <button type="button" class="btn btn-outline-light border" data-invoice-settings-reset>Reset</button>
                                <button type="submit" class="btn btn-primary" data-invoice-settings-submit>
                                    <span data-invoice-settings-submit-label>Save Changes</span>
                                    <span class="d-none spinner-border spinner-border-sm ms-1" role="status" data-invoice-settings-spinner></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="invoiceDocumentPreviewModal" tabindex="-1" aria-hidden="true" data-invoice-preview-modal>
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" data-invoice-preview-title>Invoice Document Preview</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted fs-12">Document</div>
                                    <div class="fw-semibold" data-invoice-preview-name>-</div>
                                    <div class="text-muted fs-12" data-invoice-preview-code>-</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted fs-12">Template</div>
                                    <div class="fw-semibold" data-invoice-preview-template>-</div>
                                    <div class="text-muted fs-12">Generated: <span data-invoice-preview-generated>0</span></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted fs-12">Latest Generated</div>
                                    <div class="fw-semibold" data-invoice-preview-latest>-</div>
                                    <div class="text-muted fs-12" data-invoice-preview-note>-</div>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-2">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="mb-0">PDF Preview</h6>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-primary" data-invoice-preview-mode="design">Design Preview</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-invoice-preview-mode="pdf">Generated PDF</button>
                                    <a class="btn btn-sm btn-outline-primary d-none" target="_blank" rel="noopener" data-invoice-preview-download>Open/Download</a>
                                </div>
                            </div>
                            <div class="text-muted fs-12 mb-2" data-invoice-preview-status>Preparing preview…</div>
                            <div class="border rounded p-3" style="background:#f8f9fa; min-height: 70vh; overflow:auto;" data-invoice-preview-mock></div>
                            <iframe title="Invoice document preview" class="w-100 border rounded d-none" style="min-height: 70vh;" data-invoice-preview-frame></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<!-- /Page Wrapper -->

@endsection
