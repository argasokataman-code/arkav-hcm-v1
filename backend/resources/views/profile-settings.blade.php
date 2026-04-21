<?php $page = 'profile-settings'; ?>
@extends('layout.mainlayout')
@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">My Account</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            Account
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Profile Settings</li>
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
                <a class="nav-link active" href="{{ url('profile-settings') }}"><i class="ti ti-settings me-2"></i>General Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ url('business-settings') }}"><i class="ti ti-world-cog me-2"></i>Website Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ url('salary-settings') }}"><i class="ti ti-device-ipad-horizontal-cog me-2"></i>App Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ url('email-settings') }}"><i class="ti ti-server-cog me-2"></i>System Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ url('payment-gateways') }}"><i class="ti ti-settings-dollar me-2"></i>Financial Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ url('custom-css') }}"><i class="ti ti-settings-2 me-2"></i>Other Settings</a>
            </li>
        </ul>
        <div class="row">
            <div class="col-xl-3 theiaStickySidebar">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column list-group settings-list">
                            <a href="{{ url('profile-settings') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Profile Settings</a>
                            <a href="{{ url('security-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Security Settings</a>
                            <a href="{{ url('notification-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Notifications</a>
                            <a href="{{ url('connected-apps') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Connected Apps</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-body">
                        <div class="border-bottom mb-3 pb-3">
                            <h4 class="mb-1">Profile Settings</h4>
                            <div class="text-muted fs-12">
                                Kelola data akun kamu. Kalau kamu login sebagai company, kode perusahaan akan tampil di sini.
                            </div>
                        </div>

                        <div class="border rounded-3 p-3 mb-3 bg-light" data-company-context-card>
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold mb-1">Company Context</div>
                                    <div class="text-muted fs-12">Mode ini dipakai untuk akses workspace perusahaan (tenant).</div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-info-subtle text-info" data-company-context-mode>—</span>
                                </div>
                            </div>
                            <div class="row mt-3 g-2">
                                <div class="col-md-6">
                                    <div class="small text-muted">Company Name</div>
                                    <div class="fw-medium" data-company-name>—</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted">Company ID</div>
                                    <div class="fw-medium" data-company-id>—</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted">Company Code</div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="fw-medium" data-company-code>—</div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 d-none" data-copy-company-code>
                                            <i class="ti ti-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 text-muted fs-12">
                                Tips: simpan <strong>Company Code</strong> ini untuk login mode <em>Login Company</em>.
                            </div>
                        </div>
                        <div class="border rounded-3 p-3 mb-3 d-none" data-subscription-summary-card>
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                                <div>
                                    <div class="fw-semibold mb-1">Subscription Summary</div>
                                    <div class="text-muted fs-12">Ringkasan paket aktif dan pembayaran berikutnya untuk company ini.</div>
                                </div>
                                <span class="badge bg-primary-subtle text-primary" data-subscription-status>—</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 col-xl-3">
                                    <div class="small text-muted">Package</div>
                                    <div class="fw-medium" data-subscription-package>—</div>
                                </div>
                                <div class="col-md-6 col-xl-3">
                                    <div class="small text-muted">Billing Cycle</div>
                                    <div class="fw-medium" data-subscription-billing-cycle>—</div>
                                </div>
                                <div class="col-md-6 col-xl-3">
                                    <div class="small text-muted">Current Period</div>
                                    <div class="fw-medium" data-subscription-period>—</div>
                                </div>
                                <div class="col-md-6 col-xl-3">
                                    <div class="small text-muted">Next Payment</div>
                                    <div class="fw-medium" data-subscription-next-payment-date>—</div>
                                    <div class="text-muted fs-12" data-subscription-next-payment-amount>—</div>
                                </div>
                            </div>
                        </div>
                        <div class="alert d-none" data-profile-settings-feedback></div>
                        <form action="javascript:void(0);" data-profile-settings-form>
                            <div class="border-bottom mb-3">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div>					
                                            <h6 class="mb-3">Basic Information</h6>
                                            <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                                <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded-circle border border-dashed me-2 flex-shrink-0 text-dark frames">
                                                    <i class="ti ti-photo text-gray-3 fs-16"></i>
                                                </div>                                              
                                                <div class="profile-upload">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1">Profile Photo</h6>
                                                        <p class="fs-12">Kelola foto profil user lewat halaman Profile.</p>
                                                    </div>
                                                    <div class="profile-uploader d-flex align-items-center">
                                                        <a href="{{ url('profile') }}" class="btn btn-sm btn-primary me-2">Buka My Profile</a>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label mb-md-0">First Name</label>
                                            </div>
                                            <div class="col-md-8">
                                                <input type="text" class="form-control" data-general-setting="first_name" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label mb-md-0">Last Name</label>
                                            </div>
                                            <div class="col-md-8">
                                                <input type="text" class="form-control" data-general-setting="last_name">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label mb-md-0">Email</label>
                                            </div>
                                            <div class="col-md-8">
                                                <input type="email" class="form-control" data-general-setting="email" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label mb-md-0">Phone</label>
                                            </div>
                                            <div class="col-md-8">
                                                <input type="text" class="form-control" data-general-setting="phone">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="border-bottom mb-3">
                                <h6 class="mb-3">Address Information</h6>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-2">
                                                <label class="form-label mb-md-0">Address</label>
                                            </div>
                                            <div class="col-md-10">
                                                <input type="text" class="form-control" data-general-setting="address">
                                            </div>	
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label mb-md-0">City</label>
                                            </div>
                                            <div class="col-md-8">
                                                <input type="text" class="form-control" data-general-setting="city">
                                            </div>	
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label mb-md-0">State</label>
                                            </div>
                                            <div class="col-md-8">
                                                <input type="text" class="form-control" data-general-setting="state">
                                            </div>	
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label mb-md-0">Country</label>
                                            </div>
                                            <div class="col-md-8">
                                                <input type="text" class="form-control" data-general-setting="country">
                                            </div>	
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label mb-md-0">Postal Code</label>
                                            </div>
                                            <div class="col-md-8">
                                                <input type="text" class="form-control" data-general-setting="postal_code">
                                            </div>	
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="border-bottom mb-3 pb-3">
                                <h6 class="mb-3">Change Password</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Current Password</label>
                                            </div>
                                            <div class="col-md-7">
                                                <div class="pass-group">
                                                    <input type="password" class="pass-input form-control" data-profile-settings-current-password autocomplete="current-password">
                                                    <span class="ti toggle-password ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">New Password</label>
                                            </div>
                                            <div class="col-md-7">
                                                <div class="pass-group">
                                                    <input type="password" class="pass-inputs form-control" data-profile-settings-new-password autocomplete="new-password">
                                                    <span class="ti toggle-passwords ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Confirm Password</label>
                                            </div>
                                            <div class="col-md-7">
                                                <div class="pass-group">
                                                    <input type="password" class="pass-inputa form-control" data-profile-settings-confirm-password autocomplete="new-password">
                                                    <span class="ti toggle-passworda ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-end">
                                <button type="button" class="btn btn-outline-light border me-3" data-profile-settings-reset>Cancel</button>
                                <button type="submit" class="btn btn-primary" data-profile-settings-submit>Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<!-- /Page Wrapper -->

@endsection