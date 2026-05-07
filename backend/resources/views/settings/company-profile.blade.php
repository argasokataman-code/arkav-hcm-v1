<?php $page = 'company-profile'; ?>
@php
    $isGlobalHcmAdmin = (bool) ((request()->user() ?: auth()->user())?->isGlobalHcmAdmin());
    $activeCompanyRole = strtolower(trim((string) request()->attributes->get('activeCompanyRole', '')));
    $isOwnerTenantContext = $activeCompanyRole === 'owner';
@endphp

@php($isOwner = false) {{-- JS will handle visibility based on /me role --}}
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
                        <li class="breadcrumb-item">Account</li>
                        <li class="breadcrumb-item active" aria-current="page">Company Profile</li>
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

        <div class="row">
            <div class="col-xl-3 theiaStickySidebar">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column list-group settings-list">
                            @if (! $isOwnerTenantContext)
                            <a href="{{ url('profile-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Profile Settings</a>
                            @endif
                            <a href="{{ url('company-profile') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Company Profile</a>
                            @if ($isGlobalHcmAdmin)
                            <a href="{{ url('security-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Security Settings</a>
                            @endif
                            <a href="{{ url('notification-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Notifications</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-body">
                        <div class="border-bottom mb-3 pb-3">
                            <h4 class="mb-1">Company Profile</h4>
                            <div class="text-muted fs-12">
                                @if ($isOwnerTenantContext)
                                Sebagai owner, halaman ini menjadi satu pintu untuk mengelola identitas akun owner dan identitas perusahaan.
                                @else
                                Data identitas perusahaan yang dipakai pada dokumen billing, invoice, dan kontrak.
                                @endif
                            </div>
                        </div>

                        <div class="alert d-none" data-company-profile-feedback></div>

                        <!-- Not-owner notice -->
                        <div class="alert alert-warning d-none" data-company-profile-not-owner>
                            <i class="ti ti-lock me-2"></i>
                            Halaman ini hanya dapat diakses oleh <strong>Owner</strong> company. Login menggunakan mode <em>Login Company</em> dengan akun owner untuk mengelola profil perusahaan.
                        </div>

                        <!-- Owner-only form -->
                        <div data-company-profile-owner-only>
                            <form action="javascript:void(0);" data-company-profile-form>
                                <div class="border-bottom mb-3 pb-3">
                                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                                        <div>
                                            <h6 class="mb-1">Owner Account</h6>
                                            <div class="text-muted fs-12">Profil owner dan foto akun dikelola dari halaman ini agar tidak terpecah dengan company profile.</div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">
                                        <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded-circle border border-dashed me-2 flex-shrink-0 text-dark frames overflow-hidden" style="width:80px;height:80px;">
                                            <img src="" alt="Owner Photo" class="img-fluid rounded-circle w-100 h-100 d-none" data-owner-photo-preview style="object-fit:cover;">
                                            <i class="ti ti-photo text-gray-3 fs-16" data-owner-photo-placeholder></i>
                                        </div>
                                        <div class="profile-upload">
                                            <div class="mb-2">
                                                <h6 class="mb-1">Owner Photo</h6>
                                                <p class="fs-12 mb-0">JPG, PNG, atau GIF. Maks 2MB.</p>
                                                <p class="fs-12 text-danger d-none" data-owner-photo-error></p>
                                            </div>
                                            <div class="profile-uploader d-flex align-items-center gap-2">
                                                <label class="btn btn-sm btn-primary mb-0" style="cursor:pointer;">
                                                    <i class="ti ti-upload me-1"></i>Upload Foto
                                                    <input type="file" accept="image/jpeg,image/png,image/gif" class="d-none" data-owner-photo-input>
                                                </label>
                                                <button type="button" class="btn btn-sm btn-outline-danger d-none" data-owner-photo-remove>Hapus</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">First Name <span class="text-danger">*</span></label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-owner-field="first_name" maxlength="50" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Last Name</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-owner-field="last_name" maxlength="50">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Email <span class="text-danger">*</span></label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="email" class="form-control" data-owner-field="email" maxlength="255" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Phone</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-owner-field="phone" maxlength="20">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-bottom mb-3 pb-3">
                                    <h6 class="mb-3">Identitas Perusahaan</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Company Name <span class="text-danger">*</span></label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-company-field="name" placeholder="Nama perusahaan" minlength="2" maxlength="255" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Legal Name</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-company-field="legal_name" placeholder="Nama legal / PT / CV">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">NPWP</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-company-field="npwp" inputmode="numeric" maxlength="32" placeholder="Contoh: 12.345.678.9-012.345">
                                                    <div class="form-text">Gunakan 15-16 digit (boleh dengan titik/strip, sistem akan normalisasi).</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-bottom mb-3 pb-3">
                                    <h6 class="mb-3">Alamat Perusahaan</h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="row align-items-center">
                                                <div class="col-md-2">
                                                    <label class="form-label mb-md-0">Alamat</label>
                                                </div>
                                                <div class="col-md-10">
                                                    <input type="text" class="form-control" data-company-field="address" placeholder="Jl. ...">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Kota</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-company-field="city" placeholder="Jakarta">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Provinsi</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-company-field="state" placeholder="DKI Jakarta">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Negara</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-company-field="country" placeholder="Indonesia">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Kode Pos</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-company-field="postal_code" placeholder="10270">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center justify-content-end" data-company-profile-actions>
                                    <button type="button" class="btn btn-outline-light border me-3" data-company-profile-reset>Batal</button>
                                    <button type="submit" class="btn btn-primary" data-company-profile-submit>Simpan</button>
                                </div>
                            </form>
                        </div>
                        <!-- /Owner-only form -->

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

@endsection
