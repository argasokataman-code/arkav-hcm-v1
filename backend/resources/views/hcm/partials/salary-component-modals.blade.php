{{-- Scroll: form wraps header+body+footer + flex chain below so .modal-body scrolls inside viewport. --}}
@once
    <style>
        .arcav-salary-master-modal.modal .modal-dialog-scrollable .modal-content {
            min-height: 0;
        }
        .arcav-salary-master-modal.modal .modal-content > form.arcav-salary-master-modal__form {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            max-height: 100%;
            overflow: hidden;
        }
        .arcav-salary-master-modal.modal .modal-content > form.arcav-salary-master-modal__form .modal-body {
            min-height: 0;
            overflow-y: auto;
        }
    </style>
@endonce

<div class="modal fade arcav-salary-master-modal" id="arcav_add_salary_component" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form class="arcav-salary-master-modal__form" data-hcm-salary-component-form="add">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah komponen gaji</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" data-hcm-field="name" required maxlength="200" placeholder="Contoh: Tunjangan shift malam">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" class="form-control" data-hcm-field="code" maxlength="64" pattern="[a-z0-9_\-]*" title="huruf kecil, angka, _, -" placeholder="Otomatis jika kosong (a-z, 0-9, _, -)">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jenis <span class="text-danger">*</span></label>
                            <select class="form-select" data-hcm-field="kind" required>
                                <option value="addition">Pendapatan (penambah)</option>
                                <option value="deduction">Potongan</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" data-hcm-field="category" required></select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" rows="2" data-hcm-field="description" maxlength="2000"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rujukan peraturan (ringkas)</label>
                        <input type="text" class="form-control" data-hcm-field="legalBasis" maxlength="500" placeholder="Mis. UU / PP / PMK yang relevan">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Catatan hukum / pajak</label>
                        <textarea class="form-control" rows="2" data-hcm-field="legalNotes" maxlength="5000"></textarea>
                    </div>

                    <hr class="text-muted opacity-25 my-4">

                    <h6 class="fw-semibold mb-1">Nilai default berbasis persen</h6>
                    <p class="text-muted small mb-3">Opsional — misalnya iuran BPJS. Kosongkan persen jika komponen selalu nominal per slip.</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Persen (%)</label>
                            <input type="number" class="form-control" data-hcm-field="defaultPercent" step="0.01" min="0" max="100" placeholder="Kosong = nominal per slip">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dasar perhitungan %</label>
                            <select class="form-select" data-hcm-field="percentBasis">
                                <option value="">— Tidak pakai % (nominal) —</option>
                                <option value="basic_wage">Upah / gaji pokok</option>
                                <option value="wage_bpjs_health">Dasar upah BPJS Kesehatan</option>
                                <option value="wage_bpjs_tk">Dasar upah BPJS Ketenagakerjaan</option>
                                <option value="gross_monthly_ter">Bruto bulanan (lapisan TER)</option>
                                <option value="thr_calculation_base">Basis THR (pokok + tunjangan tetap)</option>
                            </select>
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-1">Flag perhitungan</h6>
                    <p class="text-muted small mb-3">Digunakan saat mesin penggajian dihitung nanti.</p>
                    <div class="border rounded p-3 mb-3 bg-light">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="includeBpjsHealthWageBase" id="sc_add_bpjs_kes">
                                    <label class="form-check-label" for="sc_add_bpjs_kes">Masuk dasar upah (BPJS Kesehatan)</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="includeBpjsTkWageBase" id="sc_add_bpjs_tk">
                                    <label class="form-check-label" for="sc_add_bpjs_tk">Masuk dasar upah (BPJS TK)</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="includeThrCalculationBase" id="sc_add_thr">
                                    <label class="form-check-label" for="sc_add_thr">Masuk basis THR (pokok + tunjangan tetap)</label>
                                </div>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="subjectOvertimeRegulation" id="sc_add_ot">
                                    <label class="form-check-label" for="sc_add_ot">Terikat aturan lembur (UU Ketenagakerjaan)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="includePph21TerGross" id="sc_add_ter" checked>
                                    <label class="form-check-label" for="sc_add_ter">Masuk bruto bulanan (lapisan TER / PPh 21)</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="includePph21AnnualReconciliation" id="sc_add_recon">
                                    <label class="form-check-label" for="sc_add_recon">Relevansi rekonsiliasi tahunan / Desember</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="affectsNetPay" id="sc_add_net" checked>
                                    <label class="form-check-label" for="sc_add_net">Memengaruhi THP (take-home)</label>
                                </div>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="employerCostLine" id="sc_add_emp">
                                    <label class="form-check-label" for="sc_add_emp">Baris beban perusahaan (informasi slip)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row align-items-end">
                        <div class="col-md-6 mb-0">
                            <label class="form-label">Urutan</label>
                            <input type="number" class="form-control" data-hcm-field="sortOrder" min="0" max="65535" value="0">
                        </div>
                        <div class="col-md-6 mb-0">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" data-hcm-field="isActive" id="sc_add_active" checked>
                                <label class="form-check-label" for="sc_add_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade arcav-salary-master-modal" id="arcav_edit_salary_component" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form class="arcav-salary-master-modal__form" data-hcm-salary-component-form="edit">
                <div class="modal-header">
                    <h5 class="modal-title">Ubah komponen gaji</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <input type="hidden" data-hcm-field="id" value="">
                <div class="modal-body">
                    <p class="small text-warning d-none mb-3 pb-2 border-bottom" data-hcm-salary-component-locked-note>Komponen sistem: profil pajak/JS tetap terkunci; Anda dapat menyesuaikan nama, deskripsi, urutan, status, serta <strong>persen default &amp; dasar</strong> (tarif BPJS dll.).</p>
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" data-hcm-field="name" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kode <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" data-hcm-field="code" required maxlength="64" pattern="[a-z0-9_\-]+">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jenis <span class="text-danger">*</span></label>
                            <select class="form-select" data-hcm-field="kind" required>
                                <option value="addition">Pendapatan (penambah)</option>
                                <option value="deduction">Potongan</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" data-hcm-field="category" required></select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" rows="2" data-hcm-field="description" maxlength="2000"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rujukan peraturan (ringkas)</label>
                        <input type="text" class="form-control" data-hcm-field="legalBasis" maxlength="500">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Catatan hukum / pajak</label>
                        <textarea class="form-control" rows="2" data-hcm-field="legalNotes" maxlength="5000"></textarea>
                    </div>

                    <hr class="text-muted opacity-25 my-4">

                    <h6 class="fw-semibold mb-1">Nilai default berbasis persen</h6>
                    <p class="text-muted small mb-3">Opsional. Kosongkan persen jika komponen selalu nominal per slip.</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Persen (%)</label>
                            <input type="number" class="form-control" data-hcm-field="defaultPercent" step="0.01" min="0" max="100" placeholder="Kosong = nominal per slip">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dasar perhitungan %</label>
                            <select class="form-select" data-hcm-field="percentBasis">
                                <option value="">— Tidak pakai % (nominal) —</option>
                                <option value="basic_wage">Upah / gaji pokok</option>
                                <option value="wage_bpjs_health">Dasar upah BPJS Kesehatan</option>
                                <option value="wage_bpjs_tk">Dasar upah BPJS Ketenagakerjaan</option>
                                <option value="gross_monthly_ter">Bruto bulanan (lapisan TER)</option>
                                <option value="thr_calculation_base">Basis THR (pokok + tunjangan tetap)</option>
                            </select>
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-1">Flag perhitungan</h6>
                    <p class="text-muted small mb-3">Digunakan saat mesin penggajian dihitung nanti.</p>
                    <div class="border rounded p-3 mb-3 bg-light">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="includeBpjsHealthWageBase" id="sc_ed_bpjs_kes">
                                    <label class="form-check-label" for="sc_ed_bpjs_kes">Masuk dasar upah (BPJS Kesehatan)</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="includeBpjsTkWageBase" id="sc_ed_bpjs_tk">
                                    <label class="form-check-label" for="sc_ed_bpjs_tk">Masuk dasar upah (BPJS TK)</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="includeThrCalculationBase" id="sc_ed_thr">
                                    <label class="form-check-label" for="sc_ed_thr">Masuk basis THR</label>
                                </div>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="subjectOvertimeRegulation" id="sc_ed_ot">
                                    <label class="form-check-label" for="sc_ed_ot">Aturan lembur</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="includePph21TerGross" id="sc_ed_ter">
                                    <label class="form-check-label" for="sc_ed_ter">Masuk bruto (TER / PPh 21)</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="includePph21AnnualReconciliation" id="sc_ed_recon">
                                    <label class="form-check-label" for="sc_ed_recon">Rekonsiliasi tahunan</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="affectsNetPay" id="sc_ed_net">
                                    <label class="form-check-label" for="sc_ed_net">Memengaruhi THP</label>
                                </div>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" data-hcm-field="employerCostLine" id="sc_ed_emp">
                                    <label class="form-check-label" for="sc_ed_emp">Beban perusahaan (informasi)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row align-items-end">
                        <div class="col-md-6 mb-0">
                            <label class="form-label">Urutan</label>
                            <input type="number" class="form-control" data-hcm-field="sortOrder" min="0" max="65535" value="0">
                        </div>
                        <div class="col-md-6 mb-0">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" data-hcm-field="isActive" id="sc_ed_active">
                                <label class="form-check-label" for="sc_ed_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</div>
