<!-- Termination read-only detail (shared: /termination + /employee-details) -->
<div class="modal fade" id="arcav_termination_detail_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Termination detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" role="alert" data-arcav-termination-detail-error></div>
                <div data-arcav-termination-detail-body class="d-none">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted">Employee</dt>
                        <dd class="col-sm-8" data-arcav-termination-detail-employee>—</dd>
                        <dt class="col-sm-4 text-muted">Email</dt>
                        <dd class="col-sm-8 text-break" data-arcav-termination-detail-email>—</dd>
                        <dt class="col-sm-4 text-muted">Department</dt>
                        <dd class="col-sm-8" data-arcav-termination-detail-department>—</dd>
                        <dt class="col-sm-4 text-muted">Termination type</dt>
                        <dd class="col-sm-8" data-arcav-termination-detail-type>—</dd>
                        <dt class="col-sm-4 text-muted">Status</dt>
                        <dd class="col-sm-8" data-arcav-termination-detail-status>—</dd>
                        <dt class="col-sm-4 text-muted">Notice date</dt>
                        <dd class="col-sm-8" data-arcav-termination-detail-notice-date>—</dd>
                        <dt class="col-sm-4 text-muted">Termination date</dt>
                        <dd class="col-sm-8" data-arcav-termination-detail-termination-date>—</dd>
                        <dt class="col-sm-4 text-muted">Reason</dt>
                        <dd class="col-sm-8 text-break" data-arcav-termination-detail-reason>—</dd>
                        <dt class="col-sm-4 text-muted">Notes</dt>
                        <dd class="col-sm-8 text-break" data-arcav-termination-detail-notes>—</dd>
                        <div class="d-none" data-arcav-termination-detail-settlement-wrap>
                            <dt class="col-sm-4 text-muted">Settlement payroll period</dt>
                            <dd class="col-sm-8" data-arcav-termination-detail-settlement-period>—</dd>
                            <dt class="col-sm-4 text-muted">Final salary</dt>
                            <dd class="col-sm-8" data-arcav-termination-detail-final-salary>—</dd>
                            <dt class="col-sm-4 text-muted">Final allowance</dt>
                            <dd class="col-sm-8" data-arcav-termination-detail-final-allowance>—</dd>
                            <dt class="col-sm-4 text-muted">Final deduction</dt>
                            <dd class="col-sm-8" data-arcav-termination-detail-final-deduction>—</dd>
                            <dt class="col-sm-4 text-muted">Net payable</dt>
                            <dd class="col-sm-8" data-arcav-termination-detail-final-net>—</dd>
                            <dt class="col-sm-4 text-muted">Asset return notes</dt>
                            <dd class="col-sm-8 text-break" data-arcav-termination-detail-asset-return-notes>—</dd>
                            <dt class="col-sm-4 text-muted">Clearance notes</dt>
                            <dd class="col-sm-8 text-break" data-arcav-termination-detail-clearance-notes>—</dd>
                            <dt class="col-sm-4 text-muted">Settlement breakdown</dt>
                            <dd class="col-sm-8">
                                <div class="list-group list-group-flush border rounded-3" data-arcav-termination-detail-breakdown></div>
                            </dd>
                            <dt class="col-sm-4 text-muted">Outstanding clearance</dt>
                            <dd class="col-sm-8">
                                <div class="list-group list-group-flush border rounded-3" data-arcav-termination-detail-clearance-items></div>
                            </dd>
                        </div>
                        <dt class="col-sm-4 text-muted">Recorded</dt>
                        <dd class="col-sm-8 text-muted small" data-arcav-termination-detail-created>—</dd>
                    </dl>
                    <div class="mt-3">
                        <a href="{{ url('employee-details') }}" class="btn btn-light border d-inline-flex align-items-center" data-arcav-termination-detail-profile>
                            <i class="ti ti-user me-2"></i> Lihat profil karyawan
                        </a>
                    </div>
                </div>
                <p class="text-muted mb-0 d-none" data-arcav-termination-detail-loading>Memuat…</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
