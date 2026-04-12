<!-- Termination modal -->
<div class="modal fade" id="arcav_termination_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-termination-form novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" data-arcav-termination-modal-title>Termination</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert d-none" role="alert" data-arcav-termination-flash></div>

                    <input type="hidden" data-arcav-termination-id />

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" required data-arcav-termination-user>
                                <option value="">Loading…</option>
                            </select>
                            <div class="invalid-feedback">Employee wajib dipilih.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" data-arcav-termination-status>
                                <option value="pending">pending</option>
                                <option value="approved">approved</option>
                                <option value="cancelled">cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Termination type <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" required maxlength="150" list="arcav_termination_type_suggestions" data-arcav-termination-type placeholder="e.g. Retirement, Layoff" />
                            <datalist id="arcav_termination_type_suggestions">
                                <option value="Retirement"></option>
                                <option value="Insubordination"></option>
                                <option value="Lack of Skills"></option>
                                <option value="Layoff"></option>
                                <option value="Breach of Contract"></option>
                            </datalist>
                            <div class="invalid-feedback">Tipe terminasi wajib diisi (maks. 150 karakter).</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notice date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" required data-arcav-termination-notice-date />
                            <div class="invalid-feedback">Tanggal pemberitahuan wajib diisi.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Termination date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" required data-arcav-termination-termination-date />
                            <div class="invalid-feedback">Tanggal terminasi wajib diisi (harus ≥ notice date).</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control bg-light" maxlength="150" data-arcav-termination-department disabled />
                            <small class="text-muted fs-12">Otomatis dari data employee (team).</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" rows="3" required maxlength="2000" data-arcav-termination-reason></textarea>
                            <div class="invalid-feedback">Alasan wajib diisi (maks. 2000 karakter).</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" rows="2" maxlength="2000" data-arcav-termination-notes></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" data-arcav-termination-save>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
