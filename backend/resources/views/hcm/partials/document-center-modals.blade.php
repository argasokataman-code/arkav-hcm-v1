<!-- Document Center Modals -->

<!-- Upload Document Modal -->
<div class="modal fade" id="arcav_doc_upload_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="arcavDocUploadForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="arcavDocUploadModalTitle">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="documentId" id="arcavDocId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" name="employeeProfileId" id="arcavDocEmployee" required>
                                <option value="">Pilih employee...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="categoryId" id="arcavDocCategory">
                                <option value="">Pilih category (opsional)...</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" maxlength="255" required
                                placeholder="Mis: Kontrak Kerja 2025">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" maxlength="5000"
                                placeholder="Catatan tambahan (opsional)"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Visibility</label>
                            <select class="form-select" name="visibility">
                                <option value="hr_only">HR Only</option>
                                <option value="employee_visible">Employee Visible</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expires At</label>
                            <input type="date" class="form-control" name="expiresAt">
                        </div>
                        <div class="col-12" id="arcavDocFileField">
                            <label class="form-label">File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="file"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.zip,.txt,.csv">
                            <div class="form-text">Max 20 MB. Format: PDF, Word, Excel, gambar, ZIP, TXT, CSV.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="arcavDocUploadSubmit">
                        <span class="me-1"><i class="ti ti-upload"></i></span>Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Document Modal (metadata only, no file re-upload) -->
<div class="modal fade" id="arcav_doc_edit_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="arcavDocEditForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="documentId" id="arcavDocEditId">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="arcavDocEditTitle"
                                maxlength="255" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="categoryId" id="arcavDocEditCategory">
                                <option value="">Tanpa category</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Visibility</label>
                            <select class="form-select" name="visibility" id="arcavDocEditVisibility">
                                <option value="hr_only">HR Only</option>
                                <option value="employee_visible">Employee Visible</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expires At</label>
                            <input type="date" class="form-control" name="expiresAt" id="arcavDocEditExpiresAt">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="arcavDocEditDescription"
                                rows="2" maxlength="5000"></textarea>
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

<!-- Categories Management Modal -->
<div class="modal fade" id="arcav_doc_category_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Document Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="arcavDocCategoryForm" class="mb-3">
                    <input type="hidden" name="catId" id="arcavDocCatId">
                    <div class="row g-2 align-items-end">
                        <div class="col">
                            <label class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="arcavDocCatName"
                                maxlength="200" required placeholder="Mis: KTP, Kontrak, Sertifikat">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary" id="arcavDocCatSubmit">
                                <i class="ti ti-plus"></i> Add
                            </button>
                            <button type="button" class="btn btn-light ms-1" id="arcavDocCatCancelEdit"
                                style="display:none">Cancel</button>
                        </div>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Active</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="arcavDocCatTbody">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">Memuat...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="arcav_doc_delete_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Dokumen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Yakin ingin menghapus dokumen <strong id="arcavDocDeleteTitle"></strong>?
                    File fisik juga akan dihapus dari server.</p>
                <input type="hidden" id="arcavDocDeleteId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="arcavDocDeleteConfirm">
                    <i class="ti ti-trash me-1"></i>Hapus
                </button>
            </div>
        </div>
    </div>
</div>
