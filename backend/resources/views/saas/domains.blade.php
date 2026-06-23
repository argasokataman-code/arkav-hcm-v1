<?php $page = 'saas-domains'; ?>
@extends('layout.mainlayout')

@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-saas-domains-page>

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Domain Management</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">SaaS</li>
                        <li class="breadcrumb-item active" aria-current="page">Domains</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <button class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#domainModal" id="btn_add_domain">
                        <i class="ti ti-circle-plus me-2"></i>Add Domain
                    </button>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <!-- Filter Card -->
        <div class="card">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control" placeholder="Search domains..." id="search_domains" data-domain-filter-search>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filter_status" data-domain-filter-status>
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="verified">Verified</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="filter_company" data-domain-filter-company>
                            <option value="">All Companies</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary w-100" id="btn_reset_filters">
                            <i class="ti ti-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Domains List Container -->
        <div data-domains-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">Loading domains...</div></div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

<!-- Add/Edit Domain Modal -->
<div class="modal fade" id="domainModal" tabindex="-1" role="dialog" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="domainModalTitle">Add Domain</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="domainForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company *</label>
                            <select class="form-select" id="input_domain_company" required></select>

    <div class="invalid-feedback">Please select an option.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Domain Name *</label>
                            <input type="text" class="form-control" id="input_domain_name" placeholder="example.com" inputmode="url" autocapitalize="off" spellcheck="false" pattern="^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$" required>

    <div class="invalid-feedback">This field is required.</div>
                            <div class="form-text">Gunakan host/domain tanpa <span class="fw-medium">http://</span>, slash, atau path.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Verification Type *</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="verification_type" id="verification_dns" value="dns" required>

    <div class="invalid-feedback">This field is required.</div>
                                <label class="form-check-label" for="verification_dns">DNS Record</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="verification_type" id="verification_file" value="file">
                                <label class="form-check-label" for="verification_file">File Upload</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="input_domain_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Domain</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Verification Instructions Modal -->
<div class="modal fade" id="verificationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verification Instructions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="verification_instructions"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn_verify_domain">
                    <i class="ti ti-check"></i> Verify Now
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this domain? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn_confirm_delete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('build/js/company/domain-management.js') }}?v={{ filemtime(public_path('build/js/company/domain-management.js')) }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.DomainManager?.init?.();
    });
</script>

@endsection
