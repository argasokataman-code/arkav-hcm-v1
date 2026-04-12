<div class="modal fade" id="arcav_ticket_create_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form data-ticket-form="create">
                <div class="modal-header">
                    <h5 class="modal-title">Buat Ticket</h5>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" name="subject" maxlength="255" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Kategori</label>
                                <select class="form-select" name="category">
                                    <option value="">-- Pilih kategori --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prioritas</label>
                                <select class="form-select" name="priority" required>
                                    <option value="medium" selected>Medium</option>
                                    <option value="low">Low</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">SLA Due (opsional)</label>
                                <input type="datetime-local" class="form-control" name="slaDueAt">
                            </div>
                        </div>
                        <div class="col-md-12" data-ticket-admin-only style="display:none;">
                            <div class="mb-3">
                                <label class="form-label">Assign ke</label>
                                <select class="form-select" name="assigneeUserId">
                                    <option value="">Unassigned</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-0">
                                <label class="form-label">Deskripsi</label>
                                <textarea class="form-control" rows="5" name="description" maxlength="10000" required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
