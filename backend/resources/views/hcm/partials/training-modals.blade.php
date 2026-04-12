<!-- Training (Phase 1) modals -->

<!-- Training Type modal (admin) -->
<div class="modal fade" id="arcav_training_type_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-training-type-form>
                <div class="modal-header">
                    <h5 class="modal-title" data-arcav-training-type-modal-title>Add Training Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" data-arcav-training-type-id>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Type</label>
                            <input type="text" class="form-control" name="name" maxlength="200" required placeholder="Mis: Git Training">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description (opsional)</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="5000"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between border rounded p-3">
                                <div>
                                    <div class="fw-medium">Active</div>
                                    <div class="text-muted fs-12">Type aktif muncul di dropdown Training.</div>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="isActive" value="1" checked>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                Catatan: CRUD training type hanya untuk <strong>HCM Admin</strong>.
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

<!-- Training modal (admin) -->
<div class="modal fade" id="arcav_training_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-training-form>
                <div class="modal-header">
                    <h5 class="modal-title" data-arcav-training-modal-title>Add Training</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div data-arcav-training-flash style="display:none;"></div>
                    <input type="hidden" name="id" data-arcav-training-id>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Training Type</label>
                            <select class="form-select" name="trainingTypeId" data-arcav-training-type-select></select>
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
                            <label class="form-label">Cost (IDR, opsional)</label>
                            <input type="number" class="form-control" name="costIdr" min="0" step="1000" placeholder="Mis: 250000">
                            <div class="text-muted fs-12 mt-1">Phase 1: disimpan sebagai integer.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trainer (opsional)</label>
                            <select class="form-select" data-arcav-training-trainer-select>
                                <option value="">—</option>
                            </select>
                            <input type="text" class="form-control mt-2" name="trainerName" maxlength="200" placeholder="Isi nama trainer (Other)" data-arcav-training-trainer-other style="display:none;">
                            <div class="text-muted fs-12 mt-1">Pilih dari master Trainers, atau pilih <strong>Other</strong> untuk input manual.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start date</label>
                            <input type="date" class="form-control" name="startDate" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End date</label>
                            <input type="date" class="form-control" name="endDate" required>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between">
                                <label class="form-label mb-0">Participants</label>
                                <button type="button" class="btn btn-white d-inline-flex align-items-center px-3" data-arcav-open-training-participants-picker>
                                    <i class="ti ti-users me-2"></i><span class="fw-medium">Pilih karyawan</span>
                                </button>
                            </div>
                            <div class="mt-2" data-arcav-training-participants-summary>
                                <div class="text-muted fs-12">Belum ada peserta dipilih.</div>
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

<!-- Training detail modal (read-only) -->
<div class="modal fade" id="arcav_training_detail_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Training Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Training Type</small>
                        <div class="fw-medium" data-arcav-training-detail-type>—</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Trainer</small>
                        <div class="fw-medium" data-arcav-training-detail-trainer>—</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Status</small>
                        <div data-arcav-training-detail-status>—</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Start date</small>
                        <div class="fw-medium" data-arcav-training-detail-start>—</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">End date</small>
                        <div class="fw-medium" data-arcav-training-detail-end>—</div>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Cost</small>
                        <div class="fw-medium" data-arcav-training-detail-cost>—</div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block">Participants</small>
                        <div class="mt-2" data-arcav-training-detail-participants>—</div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block">Description</small>
                        <div class="mt-1 text-break" data-arcav-training-detail-desc>—</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Participants picker modal (admin) -->
<div class="modal fade" id="arcav_training_participants_picker" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pilih karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div data-arcav-training-participants-flash style="display:none;"></div>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div class="input-icon-start" style="min-width: 280px">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="text" class="form-control" placeholder="Cari nama/email..." data-arcav-training-participants-search>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-white" data-arcav-training-participants-prev>
                            <i class="ti ti-chevron-left"></i>
                        </button>
                        <div class="text-muted fs-12" data-arcav-training-participants-page>Page 1</div>
                        <button type="button" class="btn btn-white" data-arcav-training-participants-next>
                            <i class="ti ti-chevron-right"></i>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:44px">
                                    <div class="form-check form-check-md">
                                        <input class="form-check-input" type="checkbox" data-arcav-training-participants-select-all>
                                    </div>
                                </th>
                                <th>Employee</th>
                                <th>Team</th>
                                <th>Designation</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody data-arcav-training-participants-tbody>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Memuat karyawan...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 text-muted fs-12" data-arcav-training-participants-selected-count>Selected: 0</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-arcav-training-participants-apply>
                    <i class="ti ti-check me-1"></i>Apply selection
                </button>
            </div>
        </div>
    </div>
</div>

