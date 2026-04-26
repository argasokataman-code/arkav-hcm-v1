<div class="modal fade" id="arcav_add_ot_type" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add overtime type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form data-hcm-ot-type-form="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" data-hcm-field="name" required maxlength="200" placeholder="e.g. Holiday overtime">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" class="form-control" data-hcm-field="code" maxlength="64" placeholder="auto if empty (a-z, 0-9, _, -)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment multiplier <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" data-hcm-field="paymentMultiplier" required min="0.01" max="99.99" step="0.01" value="1.00">
                        <span class="text-muted small">Hint payroll: 1.00 = regular, 1.50 = setengah kali lipat, dst.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="2" data-hcm-field="description" maxlength="500"></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Sort order</label>
                        <input type="number" class="form-control" data-hcm-field="sortOrder" min="0" max="65535" value="0">
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" value="1" data-hcm-field="isActive" id="arcav_ot_type_add_active" checked>
                        <label class="form-check-label" for="arcav_ot_type_add_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="arcav_edit_ot_type" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit overtime type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form data-hcm-ot-type-form="edit">
                <input type="hidden" data-hcm-field="id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" data-hcm-field="name" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" data-hcm-field="code" required maxlength="64">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment multiplier <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" data-hcm-field="paymentMultiplier" required min="0.01" max="99.99" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="2" data-hcm-field="description" maxlength="500"></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Sort order</label>
                        <input type="number" class="form-control" data-hcm-field="sortOrder" min="0" max="65535" value="0">
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" value="1" data-hcm-field="isActive" id="arcav_ot_type_edit_active">
                        <label class="form-check-label" for="arcav_ot_type_edit_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="arcav_ot_calc_guide" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Panduan perhitungan overtime</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3 text-muted">
                    Acuan umum perhitungan lembur mengacu pada PP No. 35 Tahun 2021. Nilai final tetap mengikuti kebijakan perusahaan.
                </p>
                <div class="alert alert-light border mb-3">
                    <p class="mb-1 fw-medium">Rumus dasar upah per jam</p>
                    <p class="mb-0"><code>(Gaji pokok + tunjangan tetap) / 173</code></p>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-2">Hari kerja</h6>
                            <ul class="mb-0 ps-3">
                                <li>Jam pertama: <strong>1.5x</strong> upah per jam</li>
                                <li>Jam berikutnya: <strong>2x</strong> upah per jam</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-2">Hari libur / istirahat mingguan</h6>
                            <ul class="mb-0 ps-3">
                                <li>Menggunakan pengali bertingkat (lebih tinggi)</li>
                                <li>Skema 5/6 hari kerja mengikuti matrix regulasi</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <h6 class="mb-2">Contoh cepat</h6>
                    <p class="mb-1">Gaji + tunjangan tetap: <strong>Rp5.000.000</strong></p>
                    <p class="mb-1">Upah per jam: <strong>Rp5.000.000 / 173 = Rp28.901,73</strong></p>
                    <p class="mb-0">Lembur 2 jam hari kerja: <strong>(1.5 x 28.901,73) + (2 x 28.901,73) = Rp101.156,06</strong></p>
                </div>
                <hr class="my-3">
                <div>
                    <h6 class="mb-2">Simulasi kalkulator (UI only)</h6>
                    <p class="text-muted small mb-3">Simulasi ini tidak menyimpan data ke database dan hanya membantu estimasi cepat HR.</p>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Gaji pokok</label>
                            <input type="number" class="form-control" data-ot-guide-field="baseSalary" min="0" step="1000" value="5000000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tunjangan tetap</label>
                            <input type="number" class="form-control" data-ot-guide-field="fixedAllowance" min="0" step="1000" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Durasi lembur (menit)</label>
                            <input type="number" class="form-control" data-ot-guide-field="minutes" min="1" step="1" value="120">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis hari</label>
                            <select class="form-select" data-ot-guide-field="dayType">
                                <option value="workday" selected>Hari kerja</option>
                                <option value="public_holiday">Hari libur nasional/tanggal merah</option>
                                <option value="weekly_rest_day">Hari istirahat mingguan</option>
                                <option value="weekly_rest_day_short">Istirahat mingguan (hari kerja terpendek, 6-hari)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Skema hari kerja per minggu</label>
                            <select class="form-select" data-ot-guide-field="weeklyWorkDays">
                                <option value="5" selected>5 hari kerja</option>
                                <option value="6">6 hari kerja</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-light border mt-3 mb-0">
                        <p class="mb-1">Upah per jam: <strong data-ot-guide-result="hourlyWage">Rp0</strong></p>
                        <p class="mb-1">Durasi: <strong data-ot-guide-result="hours">0 jam</strong></p>
                        <p class="mb-0">Estimasi total lembur: <strong data-ot-guide-result="totalPay">Rp0</strong></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Mengerti</button>
            </div>
        </div>
    </div>
</div>
