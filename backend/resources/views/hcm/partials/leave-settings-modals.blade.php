{{-- Leave Settings — wired by leave-settings-data.js (template-aligned) --}}
<div class="modal fade" id="new_custom_policy" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" data-hcm-ls-custom-title>Add Custom Policy</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <form data-hcm-ls-custom-form>
                <input type="hidden" data-hcm-ls-field="id" value="">
                <div class="modal-body pb-0">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Leave type</label>
                            <select class="form-select" data-hcm-ls-field="leaveTypeCode" required data-hcm-ls-leave-type-select></select>

    <div class="invalid-feedback">Please select an option.</div>
                            <input type="text" class="form-control d-none mt-2" data-hcm-ls-field="newLeaveTypeName" value="" maxlength="150" placeholder="Nama leave type baru, contoh: Marriage Leave" data-hcm-ls-new-type-input>
                            <input type="text" class="form-control d-none mt-2" data-hcm-ls-field="leaveTypeName" value="" readonly data-hcm-ls-leave-type-readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Policy name</label>
                            <input type="text" class="form-control" data-hcm-ls-field="name" required maxlength="200" placeholder="e.g. 2 Days Leave">

    <div class="invalid-feedback">This field is required.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">No. of days</label>
                            <input type="number" class="form-control" data-hcm-ls-field="days" required step="0.5" min="0.5" max="366" placeholder="2">

    <div class="invalid-feedback">Please enter a value.</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Assign employees (optional)</label>
                            <select class="form-select" data-hcm-ls-field="assigneeIds" multiple size="5"></select>
                            <span class="text-muted fs-12">Ctrl/Cmd + click to select multiple. Kosong = tidak dibatasi per orang di UI ini.</span>
                        </div>
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

<div class="modal fade" id="arcav_leave_type_settings" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" data-hcm-ls-type-modal-title>Leave settings</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <form data-hcm-ls-type-form>
                <input type="hidden" data-hcm-ls-type-field="code" value="">
                <div class="contact-grids-tab">
                    <ul class="nav nav-underline" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#arcav-ls-pane-settings" role="tab">Settings</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#arcav-ls-pane-custom" role="tab">View Custom Policy</button>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="arcav-ls-pane-settings" role="tabpanel">
                        <div class="modal-body pb-0">
                            <div class="row" data-hcm-ls-full-fields>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">No of days</label>
                                    <input type="number" class="form-control" data-hcm-ls-type-field="days" step="0.5" min="0" max="366" placeholder="12">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Carry forward</label>
                                    <div class="d-flex align-items-center">
                                        <div class="form-check me-2">
                                            <input class="form-check-input" type="radio" name="arcav_ls_carry" id="arcav_ls_carry_y" value="1" checked>
                                            <label class="form-label" for="arcav_ls_carry_y">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="arcav_ls_carry" id="arcav_ls_carry_n" value="0">
                                            <label class="form-label" for="arcav_ls_carry_n">No</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Maximum no of days (carry)</label>
                                    <input type="number" class="form-control" data-hcm-ls-type-field="maxCarryDays" min="0" max="366" placeholder="5">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Earned leave</label>
                                    <div class="d-flex align-items-center">
                                        <div class="form-check me-2">
                                            <input class="form-check-input" type="radio" name="arcav_ls_earned" id="arcav_ls_earned_y" value="1" checked>
                                            <label class="form-label" for="arcav_ls_earned_y">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="arcav_ls_earned" id="arcav_ls_earned_n" value="0">
                                            <label class="form-label" for="arcav_ls_earned_n">No</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row d-none" data-hcm-ls-simple-fields>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Days</label>
                                    <input type="number" class="form-control" data-hcm-ls-type-field="daysSimple" step="0.5" min="0" max="366" placeholder="0">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-light border me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="arcav-ls-pane-custom" role="tabpanel">
                        <div class="modal-body pb-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Custom policies for this leave type</span>
                                <button type="button" class="btn btn-sm btn-primary" data-hcm-ls-add-custom-from-type>Add</button>
                            </div>
                            <div data-hcm-ls-custom-list class="row"></div>
                            <p class="text-muted small mb-0 d-none" data-hcm-ls-custom-empty>No custom policies yet.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="arcav_leave_type_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" data-hcm-ls-detail-title>Leave setting detail</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><span class="text-muted small">Code</span><p class="mb-0 fw-medium" data-hcm-ls-detail-code>—</p></div>
                <div class="mb-2"><span class="text-muted small">No of days</span><p class="mb-0 fw-medium" data-hcm-ls-detail-days>—</p></div>
                <div class="mb-2"><span class="text-muted small">Carry forward</span><p class="mb-0 fw-medium" data-hcm-ls-detail-carry>—</p></div>
                <div class="mb-2"><span class="text-muted small">Maximum no of days (carry)</span><p class="mb-0 fw-medium" data-hcm-ls-detail-max-carry>—</p></div>
                <div class="mb-2"><span class="text-muted small">Earned leave</span><p class="mb-0 fw-medium" data-hcm-ls-detail-earned>—</p></div>
                <div class="mb-0"><span class="text-muted small">Custom policy list</span><div class="mt-2" data-hcm-ls-detail-custom-list></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="arcav_ls_confirm_save" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h4 class="modal-title" data-hcm-ls-confirm-title>Konfirmasi</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-0" data-hcm-ls-confirm-body>Anda yakin ingin menyimpan perubahan ini?</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" data-hcm-ls-confirm-proceed>Ya, simpan</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="arcav_delete_leave_custom" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center">
                <span class="avatar avatar-xl bg-transparent-danger text-danger mb-3">
                    <i class="ti ti-trash-x fs-36"></i>
                </span>
                <h4 class="mb-1">Delete policy</h4>
                <p class="mb-3 text-muted small">Hapus kebijakan custom ini? Tindakan tidak dapat dibatalkan.</p>
                <div class="d-flex justify-content-center">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" data-hcm-ls-delete-confirm>Delete</button>
                </div>
            </div>
        </div>
    </div>
</div>
