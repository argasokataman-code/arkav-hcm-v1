<!-- Promotion read-only detail (shared: /promotion + /employee-details) -->
<div class="modal fade" id="arcav_promotion_detail_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Promotion detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" role="alert" data-arcav-promotion-detail-error></div>
                <div data-arcav-promotion-detail-body class="d-none">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted">Employee</dt>
                        <dd class="col-sm-8" data-arcav-promotion-detail-employee>—</dd>
                        <dt class="col-sm-4 text-muted">Email</dt>
                        <dd class="col-sm-8 text-break" data-arcav-promotion-detail-email>—</dd>
                        <dt class="col-sm-4 text-muted">Department</dt>
                        <dd class="col-sm-8" data-arcav-promotion-detail-department>—</dd>
                        <dt class="col-sm-4 text-muted">Designation from</dt>
                        <dd class="col-sm-8" data-arcav-promotion-detail-from>—</dd>
                        <dt class="col-sm-4 text-muted">Designation to</dt>
                        <dd class="col-sm-8" data-arcav-promotion-detail-to>—</dd>
                        <dt class="col-sm-4 text-muted">Promotion date</dt>
                        <dd class="col-sm-8" data-arcav-promotion-detail-date>—</dd>
                        <dt class="col-sm-4 text-muted">Notes</dt>
                        <dd class="col-sm-8 text-break" data-arcav-promotion-detail-notes>—</dd>
                        <dt class="col-sm-4 text-muted">Recorded</dt>
                        <dd class="col-sm-8 text-muted small" data-arcav-promotion-detail-created>—</dd>
                    </dl>
                    <div class="mt-3">
                        <a href="{{ url('employee-details') }}" class="btn btn-light border d-inline-flex align-items-center" data-arcav-promotion-detail-profile>
                            <i class="ti ti-user me-2"></i> Lihat profil karyawan
                        </a>
                    </div>
                </div>
                <p class="text-muted mb-0 d-none" data-arcav-promotion-detail-loading>Memuat…</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
