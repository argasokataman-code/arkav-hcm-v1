<!-- Goal Tracking (Phase 1) modals -->

<!-- Goal Type modal (admin) -->
<div class="modal fade" id="arcav_goal_type_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-goal-type-form>
                <div class="modal-header">
                    <h5 class="modal-title" data-arcav-goal-type-modal-title>Add Goal Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" data-arcav-goal-type-id>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Type name</label>
                            <input type="text" class="form-control" name="name" maxlength="120" required placeholder="Mis: Development Goals">

    <div class="invalid-feedback">This field is required.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description (opsional)</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="5000"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between border rounded p-3">
                                <div>
                                    <div class="fw-medium">Active</div>
                                    <div class="text-muted fs-12">Type aktif muncul di dropdown Goal Tracking.</div>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="isActive" value="1" checked>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                Catatan: CRUD goal type hanya untuk <strong>HCM Admin</strong>.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="me-1"><i class="ti ti-device-floppy"></i></span>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Goal modal (create/edit) -->
<div class="modal fade" id="arcav_goal_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-goal-form>
                <div class="modal-header">
                    <h5 class="modal-title" data-arcav-goal-modal-title>Add Goal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" data-arcav-goal-id>

                    <div class="alert alert-info mb-3" data-arcav-goal-manager-note style="display:none;">
                        Kamu membuka goal tim sebagai <strong>Manager</strong>. Pada Phase 1 kamu hanya bisa update <strong>Status</strong> dan <strong>Progress</strong>.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Goal Type</label>
                            <select class="form-select" name="goalTypeId" data-arcav-goal-type-select></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Progress (%)</label>
                            <input type="number" class="form-control" name="progressPercent" min="0" max="100" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" maxlength="200" required>

    <div class="invalid-feedback">This field is required.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Target achievement (opsional)</label>
                            <input type="text" class="form-control" name="targetAchievement" maxlength="255" placeholder="Mis: Complete a HTML course">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start date</label>
                            <input type="date" class="form-control" name="startDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End date</label>
                            <input type="date" class="form-control" name="endDate">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description (opsional)</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="5000"></textarea>
                        </div>
                        <div class="col-12" data-arcav-goal-userid-wrap style="display:none;">
                            <label class="form-label">Employee user ID (admin)</label>
                            <input type="number" class="form-control" name="userId" min="1" placeholder="Isi untuk membuat goal atas nama karyawan (Phase 1)">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="me-1"><i class="ti ti-device-floppy"></i></span>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

