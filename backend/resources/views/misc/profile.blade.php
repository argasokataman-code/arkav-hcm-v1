<?php $page = 'profile'; ?>
@extends('layout.mainlayout')
@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Profile </h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            Pages
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Profile </li>
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

        <div class="card">
            <div class="card-body">
                <div class="border-bottom mb-3 pb-3">
                    <h4>Profile </h4>
                </div>
                <div class="alert d-none" data-profile-feedback></div>
                <form action="javascript:void(0);" data-profile-form>
                    <div class="border-bottom mb-3">
                        <div class="row">
                            <div class="col-md-12">
                                <div>					
                                    <h6 class="mb-3">Basic Information</h6>
                                    <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                        <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded-circle border border-dashed me-2 flex-shrink-0 text-dark frames">
                                            <img src="{{ URL::asset('build/img/profiles/avatar-12.jpg') }}" alt="Profile photo" class="img-fluid rounded-circle" data-profile-photo>
                                        </div>                                              
                                        <div class="profile-upload">
                                            <div class="mb-2">
                                                <h6 class="mb-1">Profile Photo</h6>
                                                <p class="fs-12">Photo diambil dari profil karyawan aktif (Employees).</p>
                                            </div>
                                            <div class="profile-uploader d-flex align-items-center">
                                                <a href="{{ url('employee-details') }}" class="btn btn-sm btn-primary me-2">Kelola di Employee Details</a>
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
                                        <input type="text" class="form-control" name="firstName" data-profile-first-name required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label mb-md-0">Last Name</label>
                                    </div>
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" name="lastName" data-profile-last-name>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label mb-md-0">Email</label>
                                    </div>
                                    <div class="col-md-8">
                                        <input type="email" class="form-control" name="email" data-profile-email required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label mb-md-0">Phone</label>
                                    </div>
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" name="phone" data-profile-phone>
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
                                        <input type="text" class="form-control" name="address" data-profile-address>
                                    </div>	
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label mb-md-0">City</label>
                                    </div>
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" name="addressDetail" data-profile-address-detail>
                                    </div>	
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label mb-md-0">State</label>
                                    </div>
                                    <div class="col-md-8">
                                        <div>
                                            <select class="select">
                                                <option>Select</option>
                                                <option>France</option>
                                                <option>India</option>
                                                <option>UK</option>
                                            </select>
                                        </div>
                                    </div>	
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label mb-md-0">Country</label>
                                    </div>
                                    <div class="col-md-8">
                                        <div>
                                            <select class="select">
                                                <option>Select</option>
                                                <option>Belgium</option>
                                                <option>Turkey</option>
                                                <option>Ukraine</option>
                                            </select>
                                        </div>
                                    </div>	
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label mb-md-0">Postal Code</label>
                                    </div>
                                    <div class="col-md-8">
                                        <input type="text" class="form-control">
                                    </div>	
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="border-bottom mb-3">
                        <h6 class="mb-3">Change Password</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-5">
                                        <label class="form-label mb-md-0">Current Password</label>
                                    </div>
                                    <div class="col-md-7">
                                        <div class="pass-group">
                                            <input type="password" class="pass-input form-control" name="currentPassword" autocomplete="current-password">
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
                                            <input type="password" class="pass-inputs form-control" name="newPassword" autocomplete="new-password">
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
                                            <input type="password" class="pass-inputa form-control" name="confirmPassword" autocomplete="new-password">
                                            <span class="ti toggle-passworda ti-eye-off"></span>
                                        </div>
                                    </div>	
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-end">
                        <button type="button" class="btn btn-outline-light border me-3" data-profile-reset>Cancel</button>
                        <button type="submit" class="btn btn-primary" data-profile-submit>Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
<!-- /Page Wrapper -->

@endsection