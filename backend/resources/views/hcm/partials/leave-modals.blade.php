@php $arcavLeaveAdmin = $arcavLeaveAdmin ?? false; @endphp
<div class="modal fade" id="arcav_add_leave" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ $arcavLeaveAdmin ? 'Add leave (admin)' : 'Request leave' }}</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
            </div>
            <form data-hcm-leave-form="add">
                <div class="modal-body pb-0">
                    <!-- Error Alert -->
                    <div class="alert alert-danger d-none mb-3" data-hcm-leave-error-add role="alert">
                        <div class="d-flex align-items-start">
                            <i class="ti ti-alert-circle me-2 mt-1 flex-shrink-0"></i>
                            <div>
                                <h6 class="alert-heading mb-1" data-hcm-error-title>Error</h6>
                                <p class="mb-0 small" data-hcm-error-message></p>
                            </div>
                        </div>
                    </div>
                    @if($arcavLeaveAdmin)
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        
                            <div class="invalid-feedback">Please select an option.</div><select class="form-select" data-hcm-field="userId" required></select>
                    </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label">Leave type</label>
                        
                            <div class="invalid-feedback">Please select an option.</div><select class="form-select" data-hcm-field="leaveType" required>
                            <option value="">Memuat jenis cuti…</option>
                        </select>
                        <span class="text-muted fs-12" data-hcm-leave-type-hint>Info potong saldo akan tampil setelah jenis dipilih.</span>
                    </div>
                    <!-- Balance Display -->
                    <div class="alert alert-info d-none mb-3" data-hcm-leave-balance-card>
                        <div class="d-flex align-items-start">
                            <i class="ti ti-info-circle me-2 mt-1"></i>
                            <div>
                                <small class="fw-semibold">Saldo Cuti Tersedia:</small>
                                <div class="mt-1">
                                    <span data-hcm-leave-balance-value>--</span>
                                    <span class="text-muted">/</span>
                                    <span data-hcm-leave-balance-total>--</span> hari
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">From</label>
                            
                                <div class="invalid-feedback">This field is required.</div><input type="text" class="form-control" data-hcm-field="dateFrom" 
                                   placeholder="YYYY-MM-DD" autocomplete="off" required
                                   pattern="\d{4}-\d{2}-\d{2}" 
                                   title="Format: YYYY-MM-DD">

    <div class="invalid-feedback">This field is required.</div>
                            <small class="text-muted d-block mt-1" data-hcm-date-from-hint>Contoh: 2026-04-20</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">To</label>
                            
                                <div class="invalid-feedback">This field is required.</div><input type="text" class="form-control" data-hcm-field="dateTo" 
                                   placeholder="YYYY-MM-DD" autocomplete="off" required
                                   pattern="\d{4}-\d{2}-\d{2}" 
                                   title="Format: YYYY-MM-DD">

    <div class="invalid-feedback">This field is required.</div>
                            <small class="text-muted d-block mt-1" data-hcm-date-to-hint>Contoh: 2026-04-22</small>
                        </div>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted fs-12" data-hcm-leave-date-hint>Pilih rentang tanggal. Hari libur/weekend akan ditampilkan otomatis.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Days (optional)</label>
                        <input type="number" class="form-control" data-hcm-field="days" step="0.5" min="0.5" placeholder="Auto from range if empty">
                        <span class="text-muted fs-12" data-hcm-leave-days-estimate>Estimasi hari kerja terpotong akan dihitung otomatis.</span>
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

<div class="modal fade" id="arcav_edit_leave" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Leave detail</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
            </div>
            <form data-hcm-leave-form="edit">
                <div class="modal-body pb-0">
                    <!-- Error Alert -->
                    <div class="alert alert-danger d-none mb-3" data-hcm-leave-error-edit role="alert">
                        <div class="d-flex align-items-start">
                            <i class="ti ti-alert-circle me-2 mt-1 flex-shrink-0"></i>
                            <div>
                                <h6 class="alert-heading mb-1" data-hcm-error-title>Error</h6>
                                <p class="mb-0 small" data-hcm-error-message></p>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" data-hcm-field="id" value="">
                    <input type="hidden" data-hcm-field="ownerUserId" value="">
                    <div class="mb-3" data-hcm-leave-admin-only style="{{ $arcavLeaveAdmin ? '' : 'display:none' }}">
                        <label class="form-label">Status</label>
                        <select class="form-select" data-hcm-field="status">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="declined">Declined</option>
                        </select>
                        <span class="text-muted fs-12">Catatan: perubahan status request akan otomatis menyesuaikan ledger/saldo cuti karyawan.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Leave type</label>
                        <select class="form-select" data-hcm-field="leaveType">
                            <option value="">Memuat jenis cuti…</option>
                        </select>
                        <span class="text-muted fs-12" data-hcm-leave-type-hint>Info potong saldo akan tampil setelah jenis dipilih.</span>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">From</label>
                            <input type="text" class="form-control" data-hcm-field="dateFrom" placeholder="YYYY-MM-DD" autocomplete="off">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">To</label>
                            <input type="text" class="form-control" data-hcm-field="dateTo" placeholder="YYYY-MM-DD" autocomplete="off">
                        </div>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted fs-12" data-hcm-leave-date-hint>Pilih rentang tanggal. Hari libur/weekend akan ditampilkan otomatis.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Days</label>
                        <input type="number" class="form-control" data-hcm-field="days" step="0.5" min="0.5">
                        <span class="text-muted fs-12" data-hcm-leave-days-estimate>Estimasi hari kerja terpotong akan dihitung otomatis.</span>
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
