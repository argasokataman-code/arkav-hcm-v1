@if (Route::is(['attendance-admin']))
    <div class="modal fade" id="arcav_edit_attendance">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form data-attendance-admin-edit-form>
                    <div class="modal-header">
                        <h4 class="modal-title">Edit Attendance</h4>
                        <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                            <i class="ti ti-x"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <!-- GAP-C/E: correction context banner — shown when record has pending correction -->
                        <div class="alert alert-warning d-none mb-3" data-attendance-correction-context role="alert">
                            <i class="ti ti-alert-triangle me-1"></i>
                            <strong>Pending correction request</strong><br>
                            <span class="small">Karyawan mengajukan koreksi: &ldquo;<span data-attendance-correction-context-reason></span>&rdquo;</span><br>
                            <span class="text-muted small">Menyimpan perubahan ini akan otomatis menyetujui request tersebut.</span>
                        </div>
                        <p class="text-muted small mb-3" data-attendance-admin-edit-employee>—</p>
                        <input type="hidden" data-attendance-admin-field="userId" value="">
                        <input type="hidden" data-attendance-admin-field="workDate" value="">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Work date</label>
                                <input type="date" class="form-control" data-attendance-admin-field="workDateInput" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Check in</label>
                                <input type="time" class="form-control" data-attendance-admin-field="checkInTime" value="">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Check out</label>
                                <input type="time" class="form-control" data-attendance-admin-field="checkOutTime" value="">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Break (minutes)</label>
                                <input type="number" class="form-control" min="0" max="720" data-attendance-admin-field="breakMinutes" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Late (minutes)</label>
                                <input type="number" class="form-control" min="0" max="1440" data-attendance-admin-field="lateMinutes" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-danger me-auto" data-attendance-admin-delete-record>
                            <i class="ti ti-trash me-1"></i>Delete Record
                        </button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if (Route::is(['attendance-admin', 'attendance-employee']))
    <div class="modal fade" id="attendance_report">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Attendance report</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-0">Pratinjau laporan dengan data contoh template telah dihapus. Konten akan mengikuti data API ketika modul report diaktifkan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if (Route::is(['timesheets']))
    <!-- Add Timesheet -->
    <div class="modal fade" id="add_timesheet">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Todays Work</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('timesheets')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Project <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Office Management</option>
                                        <option>Project Management</option>
                                        <option>Hospital Administration</option>
                                    </select>
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Deadline <span class="text-danger"> *</span></label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Total  Hours <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Remaining Hours<span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date<span class="text-danger"> *</span></label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hours<span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Timesheet -->

    <!-- Edit Timesheet -->
    <div class="modal fade" id="edit_timesheet">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Todays Work</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('timesheets')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Project <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Office Management</option>
                                        <option>Project Management</option>
                                        <option>Hospital Administration</option>
                                    </select>
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Deadline <span class="text-danger"> *</span></label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy" value="14/01/2024">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Total  Hours <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control" value="32">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Remaining Hours<span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control" value="8">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date<span class="text-danger"> *</span></label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy" value="14/05/2024">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hours<span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" value="13">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Timesheet -->

    <!-- Delete Modal -->
    <div class="modal fade" id="delete_modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <span class="avatar avatar-xl bg-transparent-danger text-danger mb-3">
                        <i class="ti ti-trash-x fs-36"></i>
                    </span>
                    <h4 class="mb-1">Confirm Delete</h4>
                    <p class="mb-3">You want to delete all the marked items, this cant be undone once you delete.</p>
                    <div class="d-flex justify-content-center">
                        <a href="javascript:void(0);" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</a>
                        <a href="{{url('timesheets')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['overtime', 'overtime-employee']))
    <!-- Add Overtime -->
    <div class="modal fade" id="add_overtime">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Overtime</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('overtime')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Employee<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Anthony Lewis</option>
                                        <option>Brian Villalobos</option>
                                        <option>Harvey Smith</option>
                                    </select>
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Overtime date <span class="text-danger"> *</span></label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Overtime<span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Remaining Hours<span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Status<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Accepted</option>
                                        <option>Rejected</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Overtime</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Overtime -->

    <!-- Edit Overtime -->
    <div class="modal fade" id="edit_overtime">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Overtime</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('overtime')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Employee  * <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Anthony Lewis</option>
                                        <option>Brian Villalobos</option>
                                        <option>Harvey Smith</option>
                                    </select>
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Overtime date <span class="text-danger"> *</span></label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy" value="17-10-2024">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Overtime<span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control" value="8">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Remaining Hours<span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control" value="2">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Status<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Accepted</option>
                                        <option>Rejected</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Overtime</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Overtime -->

    <!-- Overtime Details -->
    <div class="modal fade" id="overtime_details">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"> Overtime Details</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('overtime')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <div class="p-3 mb-3 br-5 bg-transparent-light">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center file-name-icon">
                                                    <a href="#" class="avatar avatar-md border avatar-rounded">
                                                        <img src="{{ URL::asset('build/img/users/user-32.jpg') }}" class="img-fluid" alt="img">
                                                    </a>
                                                    <div class="ms-2">
                                                        <h6 class="fw-medium fs-14"><a href="#">Anthony Lewis</a></h6>
                                                        <span class="fs-12 fw-normal ">UI/UX Team</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div>
                                                    <p class="fs-14 fw-normal mb-1">Hours Worked</p>
                                                    <h6 class="fs-14 fw-medium">32</h6>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div>
                                                    <p class="fs-14 fw-normal mb-1">Date</p>
                                                    <h6 class="fs-14 fw-medium">15/05/2024</h6>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <h6 class="fs-14 fw-medium">Office Management</h6>
                                    <p class="fs-12 fw-normal">Worked on the Management design & Development</p>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Select Status <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Accepted</option>
                                        <option>Rejected</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Overtime Details -->

    <!-- Delete Modal -->
    <div class="modal fade" id="delete_modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <span class="avatar avatar-xl bg-transparent-danger text-danger mb-3">
                        <i class="ti ti-trash-x fs-36"></i>
                    </span>
                    <h4 class="mb-1">Confirm Delete</h4>
                    <p class="mb-3">You want to delete all the marked items, this cant be undone once you delete.</p>
                    <div class="d-flex justify-content-center">
                        <a href="javascript:void(0);" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</a>
                        <a href="{{url('overtime')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif
