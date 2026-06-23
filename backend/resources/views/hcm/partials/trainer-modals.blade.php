<!-- Trainers (Phase 1) modals -->

<div class="modal fade" id="arcav_trainer_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-trainer-form>
                <div class="modal-header">
                    <h5 class="modal-title" data-arcav-trainer-modal-title>Add Trainer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" data-arcav-trainer-id>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" maxlength="200" required>

    <div class="invalid-feedback">This field is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email (opsional)</label>
                            <input type="email" class="form-control" name="email" maxlength="200" placeholder="trainer@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone (opsional)</label>
                            <input type="text" class="form-control" name="phone" maxlength="50" placeholder="08xx...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Active</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="isActive" value="1" checked>
                                <label class="form-check-label text-muted fs-12">Trainer aktif muncul di list.</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description (opsional)</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="5000"></textarea>
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

