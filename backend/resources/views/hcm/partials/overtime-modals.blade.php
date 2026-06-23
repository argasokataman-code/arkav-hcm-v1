<div class="modal fade" id="arcav_add_overtime" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" data-hcm-ot-add-title>Request overtime</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
            </div>
            <form data-hcm-ot-form="add">
                <div class="modal-body pb-0">
                    <div class="mb-3" data-hcm-ot-admin-only style="display:none">
                        <label class="form-label">Employee</label>
                        <select class="form-select" data-hcm-field="userId"></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Work date</label>
                        <input type="date" class="form-control" data-hcm-field="workDate" required>

    <div class="invalid-feedback">Please select a date.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Minutes</label>
                        <input type="number" class="form-control" data-hcm-field="minutes" required min="1" max="1440" placeholder="e.g. 120">

    <div class="invalid-feedback">Please enter a value.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Project (optional)</label>
                        <input type="text" class="form-control" data-hcm-field="projectName" maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Overtime type (optional)</label>
                        <select class="form-select" data-hcm-field="overtimeTypeId"></select>
                    </div>
                    <div class="mb-3" data-hcm-ot-admin-only style="display:none">
                        <label class="form-label">Policy type</label>
                        <select class="form-select" data-hcm-field="requestType">
                            <option value="employee_request">Employee request</option>
                            <option value="company_assignment">Company assignment (dadakan)</option>
                            <option value="missed_log_correction">Missed log correction (lupa catat)</option>
                        </select>
                    </div>
                    <div class="mb-3" data-hcm-ot-admin-only style="display:none">
                        <label class="form-label">Policy note</label>
                        <textarea class="form-control" rows="2" data-hcm-field="policyNote" maxlength="500" placeholder="Alasan kebijakan/perbaikan overtime"></textarea>
                    </div>
                    <div class="mb-3" data-hcm-ot-admin-only style="display:none">
                        <label class="form-label">Initial status</label>
                        <select class="form-select" data-hcm-field="status">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="declined">Declined</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" rows="2" data-hcm-field="notes" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="arcav_edit_overtime" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Overtime detail</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
            </div>
            <form data-hcm-ot-form="edit">
                <div class="modal-body pb-0">
                    <input type="hidden" data-hcm-field="id" value="">
                    <input type="hidden" data-hcm-field="ownerUserId" value="">
                    <div class="mb-3" data-hcm-ot-admin-only style="display:none">
                        <label class="form-label">Status</label>
                        <select class="form-select" data-hcm-field="status">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="declined">Declined</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Work date</label>
                        <input type="date" class="form-control" data-hcm-field="workDate">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Minutes</label>
                        <input type="number" class="form-control" data-hcm-field="minutes" min="1" max="1440">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Project</label>
                        <input type="text" class="form-control" data-hcm-field="projectName" maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Overtime type</label>
                        <select class="form-select" data-hcm-field="overtimeTypeId"></select>
                    </div>
                    <div class="mb-3" data-hcm-ot-admin-only style="display:none">
                        <label class="form-label">Policy type</label>
                        <select class="form-select" data-hcm-field="requestType">
                            <option value="employee_request">Employee request</option>
                            <option value="company_assignment">Company assignment (dadakan)</option>
                            <option value="missed_log_correction">Missed log correction (lupa catat)</option>
                        </select>
                    </div>
                    <div class="mb-3" data-hcm-ot-admin-only style="display:none">
                        <label class="form-label">Policy note</label>
                        <textarea class="form-control" rows="2" data-hcm-field="policyNote" maxlength="500"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" rows="2" data-hcm-field="notes" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
