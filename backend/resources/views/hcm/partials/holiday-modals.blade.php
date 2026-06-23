{{-- API-driven holiday CRUD (arcav) --}}
<div class="modal fade" id="arcav_add_holiday" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Add Holiday</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
            </div>
            <form data-hcm-holiday-form="add">
                <div class="modal-body pb-0">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" data-hcm-field="title" required maxlength="200">

    <div class="invalid-feedback">This field is required.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" data-hcm-field="holidayDate" required>

    <div class="invalid-feedback">Please select a date.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="2" data-hcm-field="description" maxlength="5000"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" data-hcm-field="isActive">
                            <option value="1" selected>Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="arcav_edit_holiday" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Edit Holiday</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
            </div>
            <form data-hcm-holiday-form="edit">
                <div class="modal-body pb-0">
                    <input type="hidden" data-hcm-field="id" value="">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" data-hcm-field="title" required maxlength="200">

    <div class="invalid-feedback">This field is required.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" data-hcm-field="holidayDate" required>

    <div class="invalid-feedback">Please select a date.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="2" data-hcm-field="description" maxlength="5000"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" data-hcm-field="isActive">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
