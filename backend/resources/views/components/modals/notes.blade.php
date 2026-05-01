@if (Route::is(['notes']))
    <!-- Add Note -->
    <div class="modal fade" id="add_note">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"><i class="ti ti-notes me-2 text-primary"></i>Add Note</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Note Title <span class="text-danger">*</span></label>
                            <input type="text" id="note-add-title" class="form-control" placeholder="Enter note title…">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tag</label>
                            <select id="note-add-tag" class="form-select">
                                <option value="personal">Personal</option>
                                <option value="social">Social</option>
                                <option value="work">Work</option>
                                <option value="others">Others</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Priority</label>
                            <select id="note-add-priority" class="form-select">
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Content</label>
                            <textarea id="note-add-content" class="form-control" rows="4" placeholder="Write your note here…"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="note-add-submit" class="btn btn-primary"><i class="ti ti-circle-plus me-1"></i>Add Note</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /Add Note -->

    <!-- Edit Note -->
    <div class="modal fade" id="edit-note-units">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"><i class="ti ti-edit me-2 text-primary"></i>Edit Note</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Note Title <span class="text-danger">*</span></label>
                            <input type="text" id="note-edit-title" class="form-control" placeholder="Enter note title…">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tag</label>
                            <select id="note-edit-tag" class="form-select">
                                <option value="personal">Personal</option>
                                <option value="social">Social</option>
                                <option value="work">Work</option>
                                <option value="others">Others</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Priority</label>
                            <select id="note-edit-priority" class="form-select">
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Content</label>
                            <textarea id="note-edit-content" class="form-control" rows="4" placeholder="Write your note here…"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="note-edit-submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /Edit Note -->

    <!-- Delete Note Modal -->
    <div class="modal fade" id="delete_modal">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <span class="avatar avatar-xl bg-transparent-danger text-danger mb-3 mx-auto d-flex align-items-center justify-content-center rounded-circle" style="width:64px;height:64px;">
                        <i class="ti ti-trash-x fs-36"></i>
                    </span>
                    <h4 class="mb-1">Delete Note?</h4>
                    <p class="text-muted mb-4">This note will be permanently removed and cannot be undone.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="note-delete-confirm" class="btn btn-danger flex-fill"><i class="ti ti-trash me-1"></i>Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Note Modal -->

    <!-- View Note Modal -->
    <div class="modal fade" id="view-note-units">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header align-items-start">
                    <div>
                        <h4 id="note-view-title" class="modal-title mb-1">Note</h4>
                        <span id="note-view-tag" class="text-info fs-13"></span>
                    </div>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="note-view-content" class="mb-4 text-wrap" style="white-space:pre-wrap;min-height:60px;"></p>
                    <div class="border-top pt-3">
                        <span id="note-view-priority" class="badge bg-outline-warning d-inline-flex align-items-center">
                            <i class="fas fa-circle fs-6 me-1"></i>Medium
                        </span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /View Note Modal -->
@endif
