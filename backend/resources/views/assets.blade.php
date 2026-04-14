<?php $page = 'assets'; ?>
@extends('layout.mainlayout')
@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Assets</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Administration</li>
                            <li class="breadcrumb-item active" aria-current="page">Assets</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <a href="{{ url('asset-categories') }}" class="btn btn-white d-inline-flex align-items-center mb-2">
                        <i class="ti ti-category me-1"></i>Manage Categories
                    </a>
                    <button type="button" class="btn btn-primary d-flex align-items-center mb-2" data-bs-toggle="modal"
                        data-bs-target="#asset_add_modal">
                        <i class="ti ti-circle-plus me-2"></i>Add Asset
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
                    <h5 class="mb-0">Assets List</h5>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <input type="search" class="form-control form-control-sm" style="min-width: 220px;" maxlength="120"
                            placeholder="Search code / name / serial..." data-hcm-assets-search>
                        <select class="form-select form-select-sm" style="width: auto; min-width: 160px;" data-hcm-assets-status>
                            <option value="">All Status</option>
                            <option value="available">Available</option>
                            <option value="assigned">Assigned</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="retired">Retired</option>
                        </select>
                        <select class="form-select form-select-sm" style="width: auto; min-width: 190px;" data-hcm-assets-category>
                            <option value="">All Categories</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Asset User</th>
                                    <th>Purchase Date</th>
                                    <th>Price</th>
                                    <th>Warranty End</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-hcm-assets-body>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">Loading assets...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap py-3 gap-2"
                    data-hcm-assets-pagination style="display:none;">
                    <span class="text-muted small" data-hcm-assets-page-info></span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-hcm-assets-prev>Previous</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-hcm-assets-next>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="asset_add_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Asset</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form data-hcm-asset-form="add">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" data-hcm-field="asset_category_id" required></select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Asset Name</label>
                                <input type="text" class="form-control" data-hcm-field="name" maxlength="150" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" class="form-control" data-hcm-field="purchase_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Price</label>
                                <input type="number" class="form-control" data-hcm-field="purchase_price" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Brand</label>
                                <input type="text" class="form-control" data-hcm-field="brand" maxlength="120">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" class="form-control" data-hcm-field="model" maxlength="120">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Serial Number</label>
                                <input type="text" class="form-control" data-hcm-field="serial_number" maxlength="150">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" data-hcm-field="location" maxlength="255">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Condition</label>
                                <select class="form-select" data-hcm-field="condition">
                                    <option value="good">Good</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="lost">Lost</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" data-hcm-field="status">
                                    <option value="available">Available</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="retired">Retired</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Warranty Start Date</label>
                                <input type="date" class="form-control" data-hcm-field="warranty_start_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Warranty End Date</label>
                                <input type="date" class="form-control" data-hcm-field="warranty_end_date">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" rows="2" data-hcm-field="notes" maxlength="10000"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-hcm-submit-btn>Add Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="asset_edit_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Asset</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form data-hcm-asset-form="edit">
                    <input type="hidden" data-hcm-field="id" value="">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" data-hcm-field="asset_category_id" required></select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Asset Name</label>
                                <input type="text" class="form-control" data-hcm-field="name" maxlength="150" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" class="form-control" data-hcm-field="purchase_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Price</label>
                                <input type="number" class="form-control" data-hcm-field="purchase_price" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Brand</label>
                                <input type="text" class="form-control" data-hcm-field="brand" maxlength="120">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" class="form-control" data-hcm-field="model" maxlength="120">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Serial Number</label>
                                <input type="text" class="form-control" data-hcm-field="serial_number" maxlength="150">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" data-hcm-field="location" maxlength="255">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Condition</label>
                                <select class="form-select" data-hcm-field="condition">
                                    <option value="good">Good</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="lost">Lost</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" data-hcm-field="status">
                                    <option value="available">Available</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="retired">Retired</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Warranty Start Date</label>
                                <input type="date" class="form-control" data-hcm-field="warranty_start_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Warranty End Date</label>
                                <input type="date" class="form-control" data-hcm-field="warranty_end_date">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" rows="2" data-hcm-field="notes" maxlength="10000"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-hcm-submit-btn>Save Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="asset_assign_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Assign Asset</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form data-hcm-asset-assign-form>
                    <input type="hidden" data-hcm-field="asset_id" value="">
                    <div class="modal-body pb-0">
                        <div class="mb-3">
                            <label class="form-label">Asset</label>
                            <input type="text" class="form-control" data-hcm-field="asset_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <select class="form-select" data-hcm-field="employee_id" required></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned Date</label>
                            <input type="date" class="form-control" data-hcm-field="assigned_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Condition at Assign</label>
                            <select class="form-select" data-hcm-field="condition_at_assign">
                                <option value="good">Good</option>
                                <option value="damaged">Damaged</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" rows="2" data-hcm-field="notes" maxlength="5000"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-hcm-submit-btn>Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="asset_return_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Return Asset</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form data-hcm-asset-return-form>
                    <input type="hidden" data-hcm-field="asset_id" value="">
                    <div class="modal-body pb-0">
                        <div class="mb-3">
                            <label class="form-label">Asset</label>
                            <input type="text" class="form-control" data-hcm-field="asset_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Returned Date</label>
                            <input type="date" class="form-control" data-hcm-field="returned_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Condition at Return</label>
                            <select class="form-select" data-hcm-field="condition_at_return">
                                <option value="good">Good</option>
                                <option value="damaged">Damaged</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" rows="2" data-hcm-field="notes" maxlength="5000"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-hcm-submit-btn>Return Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
