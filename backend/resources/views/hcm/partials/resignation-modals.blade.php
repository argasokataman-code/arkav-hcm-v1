<!-- Resignation modal -->
<div class="modal fade" id="arcav_resignation_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-resignation-form novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" data-arcav-resignation-modal-title>Resignation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert d-none" role="alert" data-arcav-resignation-flash></div>

                    <input type="hidden" data-arcav-resignation-id />

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" required data-arcav-resignation-user>
                                <option value="">Loading…</option>
                            </select>
                            <div class="invalid-feedback">Employee wajib dipilih.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" data-arcav-resignation-status>
                                <option value="pending">pending</option>
                                <option value="approved">approved</option>
                                <option value="cancelled">cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notice date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" required data-arcav-resignation-notice-date />
                            <div class="invalid-feedback">Tanggal pemberitahuan wajib diisi.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Resignation date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" required data-arcav-resignation-resignation-date />
                            <div class="invalid-feedback">Tanggal resign wajib diisi (harus ≥ notice date).</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control bg-light" maxlength="150" data-arcav-resignation-department disabled />
                            <small class="text-muted fs-12">Otomatis dari data employee (team); bisa dikosongkan.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" rows="3" required maxlength="2000" data-arcav-resignation-reason></textarea>
                            <div class="invalid-feedback">Alasan wajib diisi (maks. 2000 karakter).</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" rows="2" maxlength="2000" data-arcav-resignation-notes></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" data-arcav-resignation-save>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
