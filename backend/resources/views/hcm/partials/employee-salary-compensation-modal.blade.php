<div class="modal fade" id="arcav_employee_salary_compensation_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" data-hcm-employee-salary-modal-title>Edit gaji bulanan</h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <form data-hcm-employee-salary-form>
                <div class="modal-body">
                    <input type="hidden" data-hcm-field="userId" value="">
                    <div class="mb-3" data-hcm-employee-salary-name-wrap>
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" data-hcm-field="fullNameDisplay" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gaji pokok (Rp)</label>
                        <input type="number" class="form-control" data-hcm-field="baseSalary" min="0" step="0.01" max="999999999999.99" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tunjangan tetap (Rp)</label>
                        <input type="number" class="form-control" data-hcm-field="fixedAllowance" min="0" step="0.01" max="999999999999.99" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis kontrak</label>
                        <input type="hidden" data-hcm-field="contractType" value="permanent">
                        <div class="form-control bg-light text-muted d-flex align-items-center" data-hcm-field="contractType-display" style="cursor:default;">—</div>
                        <div class="form-text"><i class="ti ti-lock me-1"></i>Jenis kontrak diatur di profil karyawan dan tidak bisa diubah di sini.</div>
                    </div>
                    <p class="text-muted small mb-0 mt-3">Halaman ini hanya untuk update gaji pokok dan tunjangan tetap. Flow kompensasi PKWT diproses dari menu Contract Compensation.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" data-hcm-employee-salary-submit>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
