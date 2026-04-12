<!-- Promotion modal -->
<div class="modal fade" id="arcav_promotion_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-promotion-form novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" data-arcav-promotion-modal-title>Promotion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert d-none" role="alert" data-arcav-promotion-flash></div>

                    <input type="hidden" data-arcav-promotion-id />

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" required data-arcav-promotion-user>
                                <option value="">Loading…</option>
                            </select>
                            <div class="invalid-feedback">Employee wajib dipilih.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Promotion Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" required data-arcav-promotion-date />
                            <div class="invalid-feedback">Tanggal promosi wajib diisi.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control bg-light" maxlength="150" data-arcav-promotion-department disabled />
                            <small class="text-muted fs-12">Otomatis dari data employee (team).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Designation From</label>
                            <input type="text" class="form-control bg-light" maxlength="150" data-arcav-promotion-from disabled />
                            <small class="text-muted fs-12">Otomatis dari jabatan saat ini (employee).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Designation To</label>
                            <select class="form-select" data-arcav-promotion-to>
                                <option value="">Select designation…</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" rows="3" maxlength="2000" data-arcav-promotion-notes></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" data-arcav-promotion-save>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

