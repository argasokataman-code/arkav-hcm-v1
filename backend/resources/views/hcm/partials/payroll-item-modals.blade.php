{{-- Modal tambah / ubah payroll item (halaman /payroll) --}}
<div class="modal fade" id="arcav_payroll_item_add" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form data-payroll-item-form="add">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah payroll item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Taut ke komponen gaji</label>
                        <select class="form-select" data-payroll-item-add-link>
                            <option value="">— Tanpa taut — isi manual di bawah</option>
                        </select>
                        <p class="text-muted small mt-1 mb-0">Jika dipilih, nama, jenis, dan kategori mengikuti komponen gaji di sistem. Satu komponen hanya boleh punya satu payroll item.</p>
                    </div>
                    <div data-payroll-item-add-custom>
                        <div class="mb-3">
                            <label class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" data-payroll-item-field="name" maxlength="200" placeholder="Nama di katalog payroll">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kode</label>
                            <input type="text" class="form-control" data-payroll-item-field="code" maxlength="64" pattern="[a-z0-9_\-]*" title="a-z, 0-9, _, -" placeholder="opsional, unik">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3" data-payroll-item-kind-only>
                                <label class="form-label">Jenis <span class="text-danger">*</span></label>
                                <select class="form-select" data-payroll-item-field="kind">
                                    <option value="addition">Pendapatan</option>
                                    <option value="deduction">Potongan</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" data-payroll-item-category-wrap>
                                <label class="form-label">Kategori <span class="text-danger">*</span></label>
                                <select class="form-select" data-payroll-item-field="category"></select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" rows="2" data-payroll-item-field="notes" maxlength="5000" placeholder="Opsional"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Urutan</label>
                            <input type="number" class="form-control" data-payroll-item-field="sortOrder" min="0" max="65535" value="0">
                        </div>
                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" data-payroll-item-field="isActive" checked id="pi_add_active">
                                <label class="form-check-label" for="pi_add_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" data-payroll-item-submit>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="arcav_payroll_item_edit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form data-payroll-item-form="edit">
                <input type="hidden" data-payroll-item-field="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Ubah payroll item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div data-payroll-item-edit-linked class="d-none">
                        <p class="text-muted small">Item yang tertaut ke komponen gaji: hanya catatan, urutan, dan status aktif yang bisa diubah. Pakai <strong>Lepas tautan</strong> untuk mengubah nama, jenis, atau kategori.</p>
                        <div class="mb-3">
                            <label class="form-label">Nama (baca)</label>
                            <input type="text" class="form-control" data-payroll-item-readonly="name" readonly>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kode (baca)</label>
                                <input type="text" class="form-control" data-payroll-item-readonly="code" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis / kategori (baca)</label>
                                <input type="text" class="form-control" data-payroll-item-readonly="kindcat" readonly>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-warning btn-sm mb-3" data-payroll-item-unlink-start>Lepas tautan (jadi tanpa taut komponen)</button>
                    </div>
                    <div data-payroll-item-edit-custom>
                        <div class="mb-3">
                            <label class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" data-payroll-item-field="name" maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kode</label>
                            <input type="text" class="form-control" data-payroll-item-field="code" maxlength="64" pattern="[a-z0-9_\-]*" title="a-z, 0-9, _, -">
                        </div>
                        <div class="mb-3 d-none" data-payroll-item-edit-link-wrap>
                            <label class="form-label">Taut ke komponen gaji</label>
                            <select class="form-select" data-payroll-item-edit-link></select>
                            <p class="text-muted small mt-1 mb-0">Mengganti tautan akan menyalin nama, jenis, dan kategori dari komponen yang dipilih.</p>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3" data-payroll-item-kind-only>
                                <label class="form-label">Jenis <span class="text-danger">*</span></label>
                                <select class="form-select" data-payroll-item-field="kind">
                                    <option value="addition">Pendapatan</option>
                                    <option value="deduction">Potongan</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" data-payroll-item-category-wrap>
                                <label class="form-label">Kategori <span class="text-danger">*</span></label>
                                <select class="form-select" data-payroll-item-field="category"></select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" rows="2" data-payroll-item-field="notes" maxlength="5000"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Urutan</label>
                            <input type="number" class="form-control" data-payroll-item-field="sortOrder" min="0" max="65535">
                        </div>
                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" data-payroll-item-field="isActive" id="pi_edit_active">
                                <label class="form-check-label" for="pi_edit_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" data-payroll-item-submit>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
