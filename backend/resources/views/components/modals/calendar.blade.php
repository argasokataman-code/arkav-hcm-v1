@if (Route::is(['calendar']))
    <!-- Add New Event -->
    <div class="modal fade" id="add_event">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="calendar-event-modal-title">Add New Event</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="calendar-event-id">
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Event Name <span class="text-danger">*</span></label>
                                <input type="text" id="calendar-event-title" class="form-control" placeholder="Input event name">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Event Date <span class="text-danger">*</span></label>
                                <input type="date" id="calendar-event-date" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="time" id="calendar-event-start-time" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" id="calendar-event-end-time" class="form-control">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Event Location</label>
                                <input type="text" id="calendar-event-location" class="form-control" placeholder="Input location (optional)">
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Descriptions</label>
                                <textarea id="calendar-event-description" class="form-control" rows="3" placeholder="Input event description (optional)"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="calendar-event-submit" class="btn btn-primary">Save Event</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /Add New Event -->

    <!-- Event -->
    <div class="modal fade" id="event_modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark modal-bg">
                    <div class="modal-title text-white"><span id="eventTitle"></span></div>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="calendar-event-view-date" class="d-flex align-items-center fw-medium text-black mb-3"><i class="ti ti-calendar-check text-default me-2"></i>-</p>
                    <p id="calendar-event-view-time" class="d-flex align-items-center fw-medium text-black mb-3"><i class="ti ti-clock text-default me-2"></i>-</p>
                    <p id="calendar-event-view-location" class="d-flex align-items-center fw-medium text-black mb-3"><i class="ti ti-map-pin-bolt text-default me-2"></i>-</p>
                    <p id="calendar-event-view-description" class="d-flex align-items-center fw-medium text-black mb-0"><i class="ti ti-notes text-default me-2"></i>-</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="calendar-event-edit-btn" class="btn btn-primary">Edit</button>
                    <button type="button" id="calendar-event-delete-btn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /Event -->
@endif
