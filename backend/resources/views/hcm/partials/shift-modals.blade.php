<div class="modal fade" id="arcav_add_shift" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form data-hcm-shift-form="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" data-hcm-field="name" required maxlength="200" placeholder="e.g. Office Standard">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" class="form-control" data-hcm-field="code" maxlength="64" placeholder="auto if empty (a-z, 0-9, _, -)">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Start <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" data-hcm-field="startTime" required value="09:00">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">End <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" data-hcm-field="endTime" required value="18:00">
                        </div>
                    </div>
                    <p class="text-muted small mb-3">Tips: untuk shift overnight, isi jam pulang lebih kecil dari jam masuk (contoh 22:00 - 06:00). Jam masuk dan jam pulang tidak boleh sama.</p>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="2" data-hcm-field="description" maxlength="500"></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Sort order</label>
                        <input type="number" class="form-control" data-hcm-field="sortOrder" min="0" max="65535" value="0">
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" value="1" data-hcm-field="isActive" id="arcav_shift_add_active" checked>
                        <label class="form-check-label" for="arcav_shift_add_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="arcav_edit_shift" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form data-hcm-shift-form="edit">
                <input type="hidden" data-hcm-field="id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" data-hcm-field="name" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" data-hcm-field="code" required maxlength="64">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Start <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" data-hcm-field="startTime" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">End <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" data-hcm-field="endTime" required>
                        </div>
                    </div>
                    <p class="text-muted small mb-3">Tips: untuk shift overnight, isi jam pulang lebih kecil dari jam masuk (contoh 22:00 - 06:00). Jam masuk dan jam pulang tidak boleh sama.</p>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="2" data-hcm-field="description" maxlength="500"></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Sort order</label>
                        <input type="number" class="form-control" data-hcm-field="sortOrder" min="0" max="65535" value="0">
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" value="1" data-hcm-field="isActive" id="arcav_shift_edit_active">
                        <label class="form-check-label" for="arcav_shift_edit_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
