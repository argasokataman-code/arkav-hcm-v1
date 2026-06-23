<?php $page = 'saas-subscriptions'; ?>
@extends('layout.mainlayout')

@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-saas-subscriptions-page>

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">SaaS Subscriptions</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">SaaS</li>
                        <li class="breadcrumb-item active" aria-current="page">Subscriptions</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2 me-2">
                    <button type="button" class="btn btn-outline-primary d-none d-flex align-items-center" id="btn_open_renew_by_id" data-subscription-renew-by-id-button>
                        <i class="ti ti-refresh me-2"></i>Reactivate by ID
                    </button>
                </div>
                <div class="mb-2">
                    <button class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#subscriptionModal" id="btn_add_subscription" data-subscription-add-button>
                        <i class="ti ti-circle-plus me-2"></i>Add Subscription
                    </button>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <div class="alert alert-warning d-none" role="alert" id="subscription_readonly_notice" data-subscription-readonly-notice>
            <strong>Read-only / Hanya baca</strong> — subscription changes require HCM admin access. Perubahan subscription memerlukan akses HCM admin.
        </div>

        <div class="card d-none" data-subscription-change-queue-card>
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="card-title mb-0">Riwayat Pengajuan Upgrade/Downgrade</h5>
                    <small class="text-muted">Pantau request pending atau seluruh history status per company dari satu panel.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <select class="form-select form-select-sm" data-subscription-change-queue-filter>
                        <option value="all">Semua status</option>
                        <option value="pending">Pending saja</option>
                    </select>
                    <span class="badge bg-warning-subtle text-warning" data-subscription-change-queue-count>0 records</span>
                </div>
            </div>
            <div class="card-body" data-subscription-change-queue-content>
                <div class="text-muted">Memuat riwayat pengajuan...</div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control" placeholder="Search subscriptions..." id="search_subscriptions" data-subscription-filter-search>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filter_status" data-subscription-filter-status>
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="trial">Trial</option>
                            <option value="inactive">Inactive</option>
                            <option value="expired">Expired</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="suspended">Suspended</option>
                            <option value="pending_payment">Pending payment</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="filter_cycle" data-subscription-filter-cycle>
                            <option value="">All Cycles</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
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

        <!-- Subscriptions List Container -->
        <div data-subscriptions-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">Loading subscriptions...</div></div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

<!-- Add/Edit Subscription Modal -->
<div class="modal fade" id="subscriptionModal" tabindex="-1" role="dialog" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subscriptionModalTitle">Add Subscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="subscriptionForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div data-subscription-company-select-group>
                                <label class="form-label">Company *</label>
                                
                                    <div class="invalid-feedback">Please select an option.</div><select class="form-select" id="input_subscription_company" required></select>
                            </div>
                            <div class="d-none" data-subscription-company-readonly-group>
                                <label class="form-label">Company</label>
                                <input type="text" class="form-control" id="input_subscription_company_readonly" readonly>
                                <small class="text-muted">Company tidak bisa diubah setelah subscription dibuat.</small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Package *</label>
                            
                                <div class="invalid-feedback">Please select an option.</div><select class="form-select" id="input_subscription_package" required></select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            
                                <div class="invalid-feedback">Please select a date.</div><input type="date" class="form-control" id="input_subscription_start" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Billing Cycle *</label>
                            
                                <div class="invalid-feedback">Please select an option.</div><select class="form-select" id="input_subscription_cycle" required>
                                <option value="">Select cycle</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date *</label>
                            
                                <div class="invalid-feedback">Please select a date.</div><input type="date" class="form-control" id="input_subscription_end" required>
                            <small class="text-muted">Diisi otomatis dari start + cycle; bisa disesuaikan.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subscription status *</label>
                            
                                <div class="invalid-feedback">Please select an option.</div>
                                    <div class="invalid-feedback">Please select an option.</div><select class="form-select" id="input_subscription_status" required>
                                <option value="active">Active</option>
                                <option value="trial">Trial</option>
                                <option value="inactive">Inactive</option>
                                <option value="expired">Expired</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            <small class="text-muted">Trial requires trial end date (on or before end date). Pending payment dikelola oleh sistem dari flow checkout, onboarding, atau konversi trial.</small>
                        </div>
                    </div>
                    <div class="row d-none" id="subscription_trial_row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Trial end date *</label>
                            <input type="date" class="form-control" id="input_subscription_trial_end">
                            <small class="text-muted">Last day of trial (after start date, not after subscription end date).</small>
                        </div>
                    </div>
                    <div class="alert alert-warning d-none small py-2" data-subscription-edit-impact-note role="note">
                        Perubahan package, status, billing cycle, atau tanggal dapat memengaruhi billing dan entitlement tenant.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Special Instructions</label>
                        <textarea class="form-control" id="input_subscription_instructions" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Subscription</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reactivate subscription manually (new ends_at) -->
<div class="modal fade" id="subscriptionRenewModal" tabindex="-1" role="dialog" data-bs-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reactivate subscription manually</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Gunakan hanya jika operator perlu mengaktifkan lagi subscription secara manual.</p>
                <p class="text-muted small mb-3">Status menjadi <strong>active</strong>; start date di-set ke hari ini oleh server.</p>
                <label class="form-label">New end date *</label>
                <input type="date" class="form-control" id="input_renew_ends_at" required>

    <div class="invalid-feedback">Please select a date.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn_confirm_renew_subscription">Reactivate</button>
            </div>
        </div>
    </div>
</div>

<!-- Reactivate by subscription ID (when row is not on current list page) -->
<div class="modal fade" id="subscriptionRenewByIdModal" tabindex="-1" role="dialog" data-bs-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reactivate by subscription ID</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Gunakan jika subscription tidak terlihat di tabel dan operator perlu mengaktifkan ulang secara manual.</p>
                <p class="text-muted small mb-3">ID subscription tampil di kolom pertama tabel. Muat dulu dari server, lalu set tanggal akhir baru.</p>
                <label class="form-label">Subscription ID / Reference *</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="input_renew_lookup_id" placeholder="e.g. 42 atau SUB-10042">
                    <button type="button" class="btn btn-outline-secondary" id="btn_renew_lookup_load">Load</button>
                </div>
                <div id="renew_by_id_summary" class="alert alert-light border mt-3 mb-0 d-none small" role="status"></div>
                <div id="renew_by_id_step2" class="mt-3 d-none">
                    <p class="text-muted small mb-2">Status menjadi <strong>active</strong>; start date di-set ke hari ini oleh server.</p>
                    <label class="form-label">New end date *</label>
                    <input type="date" class="form-control" id="input_renew_by_id_ends_at" required>

    <div class="invalid-feedback">Please select a date.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary d-none" id="btn_confirm_renew_by_id">Reactivate</button>
            </div>
        </div>
    </div>
</div>

<!-- Reactivation confirmation modal -->
<div class="modal fade" id="subscriptionReactivateConfirmModal" tabindex="-1" role="dialog" data-bs-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reactivate Subscription?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="reactivate_confirm_message">
                    This action will reactivate a suspended subscription and restore service access.
                </p>
                <p class="text-muted small mb-0" id="reactivate_confirm_hint">
                    Proceed only if payment or the blocking issue has been resolved.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn_confirm_subscription_reactivation">Yes, Reactivate</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('build/js/saas/subscriptions/http-client.js') }}?v={{ file_exists(public_path('build/js/saas/subscriptions/http-client.js')) ? filemtime(public_path('build/js/saas/subscriptions/http-client.js')) : time() }}"></script>
<script src="{{ asset('build/js/saas/subscriptions/utils.js') }}?v={{ file_exists(public_path('build/js/saas/subscriptions/utils.js')) ? filemtime(public_path('build/js/saas/subscriptions/utils.js')) : time() }}"></script>
<script src="{{ asset('build/js/saas/subscriptions-management.js') }}?v={{ filemtime(public_path('build/js/saas/subscriptions-management.js')) }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.SubscriptionsManager?.init?.();
    });
</script>

@endsection
