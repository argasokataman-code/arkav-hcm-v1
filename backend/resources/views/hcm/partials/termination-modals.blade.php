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
                            <select class="form-select bg-light" data-arcav-termination-status>
                                <option value="pending">pending</option>
                                <option value="approved">approved</option>
                                <option value="finalized">finalized</option>
                                <option value="cancelled">cancelled</option>
                            </select>
                            <small class="text-muted fs-12">Status diturunkan otomatis dari workflow stage compliance.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Workflow stage</label>
                            <select class="form-select" data-arcav-termination-workflow-stage>
                                <option value="draft_review">draft_review</option>
                                <option value="legal_review">legal_review</option>
                                <option value="approved_internal">approved_internal</option>
                                <option value="finalized_execution">finalized_execution</option>
                                <option value="cancelled">cancelled</option>
                            </select>
                            <small class="text-muted fs-12">Trail compliance: review internal, legal review, approval, lalu final execution.</small>
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
                            <label class="form-label">Termination reason code</label>
                            <select class="form-select" data-arcav-termination-reason-code>
                                <option value="">Select reason code…</option>
                                <option value="contract_end">contract_end</option>
                                <option value="retirement">retirement</option>
                                <option value="company_efficiency">company_efficiency</option>
                                <option value="misconduct">misconduct</option>
                                <option value="company_closure">company_closure</option>
                                <option value="force_majeure">force_majeure</option>
                                <option value="long_term_illness">long_term_illness</option>
                                <option value="court_order">court_order</option>
                                <option value="death">death</option>
                                <option value="other">other</option>
                            </select>
                            <small class="text-muted fs-12">Kode terstruktur untuk mapping policy hak PHK.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Legal basis code</label>
                            <select class="form-select" data-arcav-termination-legal-basis-code>
                                <option value="">Select legal basis…</option>
                                <option value="uu_ketenagakerjaan">uu_ketenagakerjaan</option>
                                <option value="uu_cipta_kerja">uu_cipta_kerja</option>
                                <option value="pp_35_2021">pp_35_2021</option>
                                <option value="pkwt_contract">pkwt_contract</option>
                                <option value="company_regulation">company_regulation</option>
                                <option value="collective_labor_agreement">collective_labor_agreement</option>
                                <option value="settlement_agreement">settlement_agreement</option>
                                <option value="court_decision">court_decision</option>
                                <option value="other">other</option>
                            </select>
                            <small class="text-muted fs-12">Dasar legal utama untuk audit trail termination.</small>
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
                        <div class="col-12 d-none" data-arcav-termination-finalization-fields>
                            <div class="border rounded-3 p-3 bg-light-subtle">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <h6 class="mb-0">Final settlement snapshot</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-arcav-termination-preview-settlement>
                                        Refresh from payroll &amp; assets
                                    </button>
                                </div>
                                <div class="alert alert-info d-none mb-3" role="alert" data-arcav-termination-preview-flash></div>
                                <div class="border rounded-3 bg-white p-3 mb-3 d-none" data-arcav-termination-preview-wrap>
                                    <div class="d-flex flex-wrap gap-3 small text-muted mb-2">
                                        <span data-arcav-termination-preview-period>Payroll period: —</span>
                                        <span data-arcav-termination-preview-source>Source: —</span>
                                        <span data-arcav-termination-preview-net>Net: —</span>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="fw-semibold small mb-2">Settlement breakdown</div>
                                            <div class="list-group list-group-flush border rounded-3" data-arcav-termination-preview-breakdown></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="fw-semibold small mb-2">Outstanding clearance items</div>
                                            <div class="list-group list-group-flush border rounded-3" data-arcav-termination-preview-clearance></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Payroll period</label>
                                        <input type="month" class="form-control" data-arcav-termination-settlement-payroll-period />
                                        <small class="text-muted fs-12">Periode payroll terdekat untuk settlement akhir.</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Final salary amount</label>
                                        <input type="number" min="0" step="0.01" class="form-control" data-arcav-termination-final-salary-amount />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Final allowance amount</label>
                                        <input type="number" min="0" step="0.01" class="form-control" data-arcav-termination-final-allowance-amount />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Final deduction amount</label>
                                        <input type="number" min="0" step="0.01" class="form-control" data-arcav-termination-final-deduction-amount />
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">Asset return notes</label>
                                        <textarea class="form-control" rows="2" maxlength="2000" data-arcav-termination-asset-return-notes></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Clearance notes</label>
                                        <textarea class="form-control" rows="3" maxlength="2000" data-arcav-termination-clearance-notes></textarea>
                                        <small class="text-muted fs-12">Wajib saat status finalized untuk menjelaskan keputusan settlement dan tindak lanjut clearance.</small>
                                    </div>
                                </div>
                            </div>
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
