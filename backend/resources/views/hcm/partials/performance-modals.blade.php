<!-- Performance (Phase 1) modals -->

<!-- Template modal -->
<div class="modal fade" id="arcav_perf_template_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-perf-template-form>
                <div class="modal-header">
                    <h5 class="modal-title" data-arcav-perf-template-modal-title>Add Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" data-arcav-perf-template-id>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Template name</label>
                            <input type="text" class="form-control" name="name" placeholder="Mis: Engineering - IC" required maxlength="200">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department (opsional)</label>
                            <select class="form-select" name="department" data-arcav-perf-template-department></select>
                            <div class="text-muted fs-12 mt-1">Kosongkan jika template berlaku untuk semua department.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Designation (opsional)</label>
                            <select class="form-select" name="designation" data-arcav-perf-template-designation></select>
                            <div class="text-muted fs-12 mt-1">Kosongkan jika template berlaku untuk semua designation.</div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center justify-content-between border rounded p-3">
                                <div>
                                    <div class="fw-medium">Active</div>
                                    <div class="text-muted fs-12">Template aktif bisa dipakai untuk cycle/review.</div>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="isActive" value="1" checked>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div data-arcav-perf-template-items-section>
                    <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                        <div>
                            <div class="fw-medium">Indicator items</div>
                            <div class="text-muted fs-12">Tambah KPI + Behavioral untuk template ini.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-white" data-arcav-perf-add-item data-section="kpi">
                                <i class="ti ti-circle-plus me-1"></i>Add KPI
                            </button>
                            <button type="button" class="btn btn-white" data-arcav-perf-add-item data-section="behavioral">
                                <i class="ti ti-circle-plus me-1"></i>Add Behavioral
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>Section</th>
                                    <th>Title</th>
                                    <th>Weight</th>
                                    <th>Scale</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-arcav-perf-items-tbody>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">Simpan template dulu untuk mulai tambah item.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="me-1"><i class="ti ti-device-floppy"></i></span>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Template detail modal (read-only) -->
<div class="modal fade" id="arcav_perf_template_detail_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Template detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="fw-medium" data-arcav-perf-template-detail-name>—</div>
                        <div class="text-muted fs-12">
                            Dept: <span data-arcav-perf-template-detail-department>—</span>
                            <span class="mx-2">•</span>
                            Designation: <span data-arcav-perf-template-detail-designation>—</span>
                            <span class="mx-2">•</span>
                            Status: <span data-arcav-perf-template-detail-status>—</span>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <div class="fw-medium mb-2">Indicator items</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Section</th>
                                <th>Title</th>
                                <th>Weight</th>
                                <th>Scale</th>
                            </tr>
                        </thead>
                        <tbody data-arcav-perf-template-detail-items-tbody>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Memuat items...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Item modal -->
<div class="modal fade" id="arcav_perf_item_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-perf-item-form>
                <div class="modal-header">
                    <h5 class="modal-title" data-arcav-perf-item-modal-title>Add Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="templateId" data-arcav-perf-item-template-id>
                    <input type="hidden" name="itemId" data-arcav-perf-item-id>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Section</label>
                            <select class="form-select" name="section" required>
                                <option value="kpi">KPI</option>
                                <option value="behavioral">Behavioral</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sort order</label>
                            <input type="number" class="form-control" name="sortOrder" min="0" max="100000" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">KPI weight (opsional)</label>
                            <input type="number" class="form-control" name="weight" min="0" max="1000" step="0.01" placeholder="Mis: 30">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" maxlength="255" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description (opsional)</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="5000"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Behavior scale min</label>
                            <input type="number" class="form-control" name="ratingScaleMin" min="1" max="10" value="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Behavior scale max</label>
                            <input type="number" class="form-control" name="ratingScaleMax" min="1" max="10" value="5">
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        Scoring: KPI dihitung sebagai persen 0–100 (weighted). Behavioral pakai rating 1–5 (atau sesuai scale) dan akan dikonversi ke 0–100 untuk total hybrid.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="me-1"><i class="ti ti-device-floppy"></i></span>Save Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cycle modal -->
<div class="modal fade" id="arcav_perf_cycle_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-perf-cycle-form>
                <div class="modal-header">
                    <h5 class="modal-title" data-arcav-perf-cycle-modal-title>Add Cycle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" data-arcav-perf-cycle-id>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Cycle name</label>
                            <input type="text" class="form-control" name="name" maxlength="200" required placeholder="Mis: 2026 H1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Period start</label>
                            <input type="date" class="form-control" name="periodStart" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Period end</label>
                            <input type="date" class="form-control" name="periodEnd" required>
                        </div>
                        <div class="col-12" data-arcav-perf-cycle-status-wrap style="display:none;">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="draft">draft</option>
                                <option value="active">active</option>
                                <option value="closed">closed</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        Tips: aktifkan 1 cycle untuk membuka submit self review.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="me-1"><i class="ti ti-device-floppy"></i></span>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create review modal (admin) -->
<div class="modal fade" id="arcav_perf_review_create_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form data-arcav-perf-review-create-form>
                <div class="modal-header">
                    <h5 class="modal-title">Create Performance Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Cycle</label>
                            <select class="form-select" name="cycleId" required data-arcav-perf-review-cycle></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Template</label>
                            <select class="form-select" name="templateId" required data-arcav-perf-review-template></select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Employee user ID</label>
                            <input type="number" class="form-control" name="userId" required min="1" placeholder="Masukkan user_id karyawan (Phase 1)">
                            <div class="text-muted fs-12 mt-1">Phase 1: input user_id manual. Nanti bisa kita upgrade ke employee picker.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="me-1"><i class="ti ti-circle-plus"></i></span>Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Usage guide (employee/manager/admin) -->
<div class="modal fade" id="arcav_perf_review_guide" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Panduan pemakaian Performance Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    Menu ini dipakai untuk workflow <strong>Employee → Manager → HCM Admin</strong> pada satu cycle aktif.
                </p>

                <div class="alert alert-light border mb-3">
                    <div class="fw-medium mb-1">Skor yang diisi</div>
                    <ul class="mb-0 ps-3">
                        <li><strong>KPI</strong>: skor <code>0–100</code> (akan ikut bobot item KPI)</li>
                        <li><strong>Behavioral</strong>: rating <code>1–5</code> (dikoversi ke 0–100 untuk total hybrid)</li>
                    </ul>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-2">Karyawan (Self review)</h6>
                            <ol class="mb-0 ps-3">
                                <li>Pilih review di tabel kiri</li>
                                <li>Isi catatan self + skor item</li>
                                <li>Klik <strong>Submit</strong> untuk mengirim ke manager</li>
                            </ol>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-2">Manager (Team review)</h6>
                            <ol class="mb-0 ps-3">
                                <li>Ubah scope ke <strong>Team</strong></li>
                                <li>Pilih review yang statusnya <em>submitted</em></li>
                                <li>Isi catatan manager + skor, lalu <strong>Complete</strong></li>
                            </ol>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-2">HCM Admin (Final)</h6>
                            <ol class="mb-0 ps-3">
                                <li>Ubah scope ke <strong>All</strong></li>
                                <li>Review yang sudah complete bisa di-finalize</li>
                                <li>Isi catatan final + skor final, lalu <strong>Finalize</strong></li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-muted small">
                    Tips: kalau tombol action masih disable, klik baris review di kiri sampai detail terbuka.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Mengerti</button>
            </div>
        </div>
    </div>
</div>

