                <div class="col-xl-3 col-md-12 sidebars-right theiaStickySidebar section-bulk-widget">
                    <div class="border rounded-3 bg-white p-3">
                        {{-- Header --}}
                        <div class="mb-3 pb-3 border-bottom d-flex align-items-center justify-content-between">
                            <h4 class="d-flex align-items-center mb-0">
                                <i class="ti ti-notebook me-2 text-primary"></i>Notes
                            </h4>
                            <a href="#" class="btn btn-primary btn-sm d-flex align-items-center gap-1"
                               data-bs-toggle="modal" data-bs-target="#add_note">
                                <i class="ti ti-plus"></i> New
                            </a>
                        </div>

                        {{-- Nav --}}
                        <div class="border-bottom pb-3 mb-3">
                            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                                <button class="d-flex text-start align-items-center fw-medium fs-15 nav-link active mb-1 rounded-2"
                                    id="v-pills-profile-tab" data-bs-toggle="pill" data-bs-target="#v-pills-profile"
                                    type="button" role="tab" aria-controls="v-pills-profile" aria-selected="true">
                                    <i class="ti ti-inbox me-2"></i>All Notes
                                    <span class="ms-auto badge bg-light text-dark" id="notes-count-badge">0</span>
                                </button>
                                <button class="d-flex text-start align-items-center fw-medium fs-15 nav-link mb-1 rounded-2"
                                    id="v-pills-messages-tab" data-bs-toggle="pill"
                                    data-bs-target="#v-pills-messages" type="button" role="tab"
                                    aria-controls="v-pills-messages" aria-selected="false">
                                    <i class="ti ti-star me-2 text-warning"></i>Important
                                </button>
                                <button class="d-flex text-start align-items-center fw-medium fs-15 nav-link mb-0 rounded-2"
                                    id="v-pills-settings-tab" data-bs-toggle="pill"
                                    data-bs-target="#v-pills-settings" type="button" role="tab"
                                    aria-controls="v-pills-settings" aria-selected="false">
                                    <i class="ti ti-trash me-2 text-danger"></i>Trash
                                </button>
                            </div>
                        </div>

                        {{-- Tags --}}
                        <div class="border-bottom pb-3 mb-3">
                            <h6 class="text-muted text-uppercase fs-11 fw-semibold mb-2 px-1">Tags</h6>
                            <div class="d-flex flex-column gap-1">
                                <a href="javascript:void(0);" class="note-filter-tag d-flex align-items-center gap-2 px-2 py-1 rounded-2 text-info" data-tag="personal">
                                    <i class="fas fa-square square-rotate fs-10"></i>Personal
                                </a>
                                <a href="javascript:void(0);" class="note-filter-tag d-flex align-items-center gap-2 px-2 py-1 rounded-2 text-warning" data-tag="social">
                                    <i class="fas fa-square square-rotate fs-10"></i>Social
                                </a>
                                <a href="javascript:void(0);" class="note-filter-tag d-flex align-items-center gap-2 px-2 py-1 rounded-2 text-primary" data-tag="work">
                                    <i class="fas fa-square square-rotate fs-10"></i>Work
                                </a>
                                <a href="javascript:void(0);" class="note-filter-tag d-flex align-items-center gap-2 px-2 py-1 rounded-2 text-secondary" data-tag="others">
                                    <i class="fas fa-square square-rotate fs-10"></i>Others
                                </a>
                            </div>
                        </div>

                        {{-- Priority --}}
                        <div>
                            <h6 class="text-muted text-uppercase fs-11 fw-semibold mb-2 px-1">Priority</h6>
                            <div class="d-flex flex-column gap-1">
                                <a href="javascript:void(0);" class="note-filter-priority d-flex align-items-center gap-2 px-2 py-1 rounded-2 text-success" data-priority="high">
                                    <i class="fas fa-square square-rotate fs-10"></i>High
                                </a>
                                <a href="javascript:void(0);" class="note-filter-priority d-flex align-items-center gap-2 px-2 py-1 rounded-2 text-warning" data-priority="medium">
                                    <i class="fas fa-square square-rotate fs-10"></i>Medium
                                </a>
                                <a href="javascript:void(0);" class="note-filter-priority d-flex align-items-center gap-2 px-2 py-1 rounded-2 text-danger" data-priority="low">
                                    <i class="fas fa-square square-rotate fs-10"></i>Low
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
