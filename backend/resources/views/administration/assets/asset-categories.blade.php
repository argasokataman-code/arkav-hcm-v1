<?php $page = 'asset-categories'; ?>
@extends('layout.mainlayout')
@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Asset Categories</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Administration</li>
                            <li class="breadcrumb-item active" aria-current="page">Asset Categories</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ url('assets') }}" class="btn btn-white d-inline-flex align-items-center mb-2">
                        <i class="ti ti-device-laptop me-1"></i>View Assets
                    </a>
                    <button type="button" class="btn btn-primary d-flex align-items-center mb-2" data-bs-toggle="modal"
                        data-bs-target="#asset_category_add_modal" id="asset_category_open_add_modal">
                        <i class="ti ti-circle-plus me-2"></i>Add Category
                    </button>
                    <div class="ms-2 head-icons">
                        <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top"
                            data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3 gap-2">
                    <h5 class="mb-0">Asset Category List</h5>
                    <input type="search" class="form-control form-control-sm" style="min-width: 220px; max-width: 320px;"
                        placeholder="Search category..." maxlength="150" data-hcm-asset-category-search>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Category Name</th>
                                    <th>Assets</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-hcm-asset-categories-body>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Loading categories...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="asset_category_add_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Category</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form data-hcm-asset-category-form="add">
                    <div class="modal-body pb-0">
                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" class="form-control" data-hcm-field="code" maxlength="80" placeholder="Optional, auto generated if empty">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" data-hcm-field="name" maxlength="150" required>

    <div class="invalid-feedback">This field is required.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" rows="3" data-hcm-field="description" maxlength="5000"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" data-hcm-field="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-hcm-submit-btn>Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="asset_category_edit_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Category</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form data-hcm-asset-category-form="edit">
                    <input type="hidden" data-hcm-field="id" value="">
                    <div class="modal-body pb-0">
                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" class="form-control" data-hcm-field="code" maxlength="80">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" data-hcm-field="name" maxlength="150" required>

    <div class="invalid-feedback">This field is required.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" rows="3" data-hcm-field="description" maxlength="5000"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" data-hcm-field="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-hcm-submit-btn>Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
