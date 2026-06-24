<?php $page = 'geofences'; ?>
@extends('layout.mainlayout')
@section('content')

<div class="page-wrapper">
    <div class="content">

        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Geofence Settings</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Settings</li>
                        <li class="breadcrumb-item active" aria-current="page">Geofences</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#add_geofence"
                       class="btn btn-primary d-flex align-items-center">
                        <i class="ti ti-circle-plus me-2"></i>Add Geofence
                    </a>
                </div>
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top"
                       data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <h5>Geofence List</h5>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                    <div class="me-3">
                        <div class="input-icon-end position-relative">
                            <input type="text" class="form-control" data-gf-search placeholder="Search geofences...">
                            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        </div>
                    </div>
                    <div>
                        <select class="form-select" data-gf-per-page>
                            <option value="10">10 / page</option>
                            <option value="20" selected>20 / page</option>
                            <option value="50">50 / page</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="custom-datatable-filter table-responsive">
                    <table class="table">
                        <thead class="thead-light">
                            <tr>
                                <th>Name</th>
                                <th>Latitude</th>
                                <th>Longitude</th>
                                <th>Radius (m)</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-gf-body>
                            <tr><td class="text-center text-muted py-4" colspan="6">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
                    <small class="text-muted" data-gf-showing>Showing 0 - 0 of 0 entries</small>
                    <ul class="pagination pagination-sm mb-0" data-gf-pagination></ul>
                </div>
            </div>
        </div>

    </div>
</div>

@component('components.modal-popup')
@endcomponent

<div class="modal fade" id="add_geofence">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Add Geofence</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <form data-gf-form="add">
                <div class="modal-body pb-0">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Geofence Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" data-gf-field="name" required maxlength="100"
                                       placeholder="e.g., Kantor Pusat">
                                <div class="invalid-feedback">Nama geofence wajib diisi.</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Latitude <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" data-gf-field="latitude" required
                                       placeholder="-6.2088" readonly>
                                <div class="invalid-feedback">Klik map untuk set lokasi.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Longitude <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" data-gf-field="longitude" required
                                       placeholder="106.8456" readonly>
                                <div class="invalid-feedback">Klik map untuk set lokasi.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Radius (meters) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" data-gf-field="radius" required min="10" max="50000"
                                       placeholder="200" value="200">
                                <div class="invalid-feedback">Radius 10 - 50.000 meter.</div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Cari Alamat atau Gunakan Lokasi Saat Ini</label>
                            <div class="input-group">
                                <input type="text" class="form-control" data-gf-address-search="add" placeholder="Cari alamat..." autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button" data-gf-search-btn="add"><i class="ti ti-search"></i></button>
                                <button class="btn btn-outline-primary" type="button" data-gf-current-loc="add"><i class="ti ti-crosshair"></i> Lokasi Saya</button>
                            </div>
                            <div class="list-group mt-1" data-gf-search-results="add" style="display:none; position:absolute; z-index:1000; max-height:200px; overflow-y:auto;"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Pilih Lokasi di Map</label>
                                <div id="gf-add-map" style="height:360px; border-radius:6px; border:1px solid var(--bs-border-color);"></div>
                                <small class="text-muted d-block mt-1">Klik di map untuk set titik pusat. Drag marker untuk menyesuaikan.</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" data-gf-field="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Geofence</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="edit_geofence">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Edit Geofence</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <form data-gf-form="edit">
                <div class="modal-body pb-0">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Geofence Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" data-gf-field="name" required maxlength="100">
                                <div class="invalid-feedback">Nama geofence wajib diisi.</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Latitude <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" data-gf-field="latitude" required readonly>
                                <div class="invalid-feedback">Klik map untuk set lokasi.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Longitude <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" data-gf-field="longitude" required readonly>
                                <div class="invalid-feedback">Klik map untuk set lokasi.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Radius (meters) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" data-gf-field="radius" required min="10" max="50000">
                                <div class="invalid-feedback">Radius 10 - 50.000 meter.</div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Cari Alamat atau Gunakan Lokasi Saat Ini</label>
                            <div class="input-group">
                                <input type="text" class="form-control" data-gf-address-search="edit" placeholder="Cari alamat..." autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button" data-gf-search-btn="edit"><i class="ti ti-search"></i></button>
                                <button class="btn btn-outline-primary" type="button" data-gf-current-loc="edit"><i class="ti ti-crosshair"></i> Lokasi Saya</button>
                            </div>
                            <div class="list-group mt-1" data-gf-search-results="edit" style="display:none; position:absolute; z-index:1000; max-height:200px; overflow-y:auto;"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Pilih Lokasi di Map</label>
                                <div id="gf-edit-map" style="height:360px; border-radius:6px; border:1px solid var(--bs-border-color);"></div>
                                <small class="text-muted d-block mt-1">Drag marker atau klik map untuk mengubah posisi.</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" data-gf-field="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Geofence</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
@endpush

@endsection
