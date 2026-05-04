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
                        <label class="form-label">Jenis kontrak</label>
                        <input type="hidden" data-hcm-field="contractType" value="permanent">
                        <div class="form-control bg-light text-muted d-flex align-items-center" data-hcm-field="contractType-display" style="cursor:default;">—</div>
                        <div class="form-text"><i class="ti ti-lock me-1"></i>Jenis kontrak diatur di profil karyawan dan tidak bisa diubah di sini.</div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label mb-0">Custom payroll item per karyawan</label>
                            <small class="text-muted">Contoh: tunjangan manager, tunjangan jabatan, potongan khusus</small>
                        </div>
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-7">
                                <label class="form-label">Payroll item</label>
                                <select class="form-select" data-hcm-assignment-item>
                                    <option value="">Pilih payroll item aktif</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nominal (Rp)</label>
                                <input type="number" class="form-control" data-hcm-assignment-amount min="0.01" step="0.01" max="999999999999.99" placeholder="0">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="button" class="btn btn-outline-primary" data-hcm-assignment-add>Tambah</button>
                            </div>
                        </div>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-nowrap mb-0 align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Komponen</th>
                                        <th>Jenis</th>
                                        <th class="text-end">Nominal</th>
                                        <th>Status</th>
                                        <th style="width: 190px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody data-hcm-assignment-body>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">Pilih karyawan untuk memuat assignment.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <p class="text-muted small mb-0 mt-3">Halaman ini hanya untuk update gaji pokok. Flow kompensasi PKWT diproses dari menu Contract Compensation.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" data-hcm-employee-salary-submit>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
