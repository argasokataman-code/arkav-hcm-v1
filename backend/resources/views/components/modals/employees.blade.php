@if (Route::is(['employee-dashboard']))
    <!-- Add Leaves -->
    <div class="modal fade" id="add_leaves">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Leave</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('employee-dashboard')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Employee Name</label>
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
                                    <label class="form-label">Leave Type</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Medical Leave</option>
                                        <option>Casual Leave</option>
                                        <option>Annual Leave</option>
                                    </select>
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">From </label>
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
                                    <label class="form-label">To </label>
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
                                    <label class="form-label">No of Days</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Remaining Days</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Reason</label>
                                    <textarea class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Leaves</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Leaves -->

    <!-- Edit Task -->
    <div class="modal fade" id="edit_task">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Task  </h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('employee-dashboard')}}">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Todo Title</label>
                                    <input type="text" class="form-control" value="Patient appointment booking">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Due Date</label>
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
                                    <label class="form-label">Project</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Office Management</option>
                                        <option>Clinic Management </option>
                                        <option>Educational Platform</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label me-2">Team Members</label>
                                    <input class="input-tags form-control" placeholder="Add new" type="text" data-role="tagsinput"  name="Label" value="Jerald,Andrew,Philip,Davis">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tag</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Internal</option>
                                        <option selected>Projects</option>
                                        <option>Meetings</option>
                                        <option>Reminder</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Inprogress</option>
                                        <option>Completed</option>
                                        <option>Pending</option>
                                        <option>Onhold</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Medium</option>
                                        <option>High</option>
                                        <option>Low</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Who Can See this Task?</label>
                                <div class="d-flex align-items-center">
                                    <div class="form-check me-3">
                                        <input class="form-check-input" type="radio" name="flexRadioDefault" id="flexRadioDefault4">
                                        <label class="form-check-label text-dark" for="flexRadioDefault4">
                                            Public
                                        </label>
                                    </div>
                                    <div class="form-check me-3">
                                        <input class="form-check-input" type="radio" name="flexRadioDefault" id="flexRadioDefault5" checked="">
                                        <label class="form-check-label text-dark" for="flexRadioDefault5">
                                            Private
                                        </label>
                                    </div>
                                    <div class="form-check ">
                                        <input class="form-check-input" type="radio" name="flexRadioDefault" id="flexRadioDefault6">
                                        <label class="form-check-label text-dark" for="flexRadioDefault6">
                                            Admin Only
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label class="form-label">Descriptions</label>
                                    <div class="summernote"></div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Upload Attachment</label>
                                <div class="bg-light rounded p-2">
                                    <div class="profile-uploader border-bottom mb-2 pb-2">
                                        <div class="drag-upload-btn btn btn-sm btn-white border px-3">
                                            Select File
                                            <input type="file" class="form-control image-sign" multiple="">
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between border-bottom mb-2 pb-2">
                                        <div class="d-flex align-items-center">
                                            <h6 class="fs-12 fw-medium me-1">Logo.zip</h6>
                                            <span class="badge badge-soft-info">21MB </span>
                                        </div>
                                        <a href="#" class="btn btn-sm btn-icon"><i class="ti ti-trash"></i></a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <h6 class="fs-12 fw-medium me-1">Files.zip</h6>
                                            <span class="badge badge-soft-info">25MB </span>
                                        </div>
                                        <a href="#" class="btn btn-sm btn-icon"><i class="ti ti-trash"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Task -->

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
                        <a href="{{url('employee-dashboard')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['employees', 'employees-grid']) || request()->is('employees') || request()->is('employees-grid'))
    @include('hcm.partials.employee-stepper-modal', ['mode' => 'add'])
    {{-- Legacy add employee modal kept temporarily for reference.
    <div class="modal fade" id="add_employee">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center">
                        <h4 class="modal-title me-2">Add New Employee</h4><span>Employee  ID : EMP -0024</span>
                    </div>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url(Route::is('employees-grid') ? 'employees-grid' : 'employees') }}" data-employee-add-form>
                    <div class="contact-grids-tab">
                        <ul class="nav nav-underline" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#basic-info" type="button" role="tab" aria-selected="true">Basic Information</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="address-tab" data-bs-toggle="tab" data-bs-target="#address" type="button" role="tab" aria-selected="false">Permissions</button>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="basic-info" role="tabpanel" aria-labelledby="info-tab" tabindex="0">
                                <div class="modal-body pb-0 ">	
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                                <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded-circle border border-dashed me-2 flex-shrink-0 text-dark frames">
                                                    <img src="{{ URL::asset('build/img/users/user-13.jpg') }}" alt="img" class="rounded-circle">
                                                </div>                                              
                                                <div class="profile-upload">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1">Upload Profile Image</h6>
                                                        <p class="fs-12">Image should be below 1 mb</p>
                                                    </div>
                                                    <div class="profile-uploader d-flex align-items-center">
                                                        <div class="drag-upload-btn btn btn-sm btn-primary me-2">
                                                            Upload
                                                            <input type="file" class="form-control image-sign" multiple="">
                                                        </div>
                                                        <a href="javascript:void(0);" class="btn btn-light btn-sm">Cancel</a>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">First Name <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" data-employee-add-field="name" placeholder="Nama lengkap">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Last Name</label>
                                                <input type="text" class="form-control" data-employee-add-field="team" placeholder="Team">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Employee ID <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Joining Date <span class="text-danger"> *</span></label>
                                                <div class="input-icon-end position-relative">
                                                    <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                                    <span class="input-icon-addon">
                                                        <i class="ti ti-calendar text-gray-7"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div> -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email <span class="text-danger"> *</span></label>
                                                <input type="email" class="form-control" data-employee-add-field="email" placeholder="email@company.com">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Password <span class="text-danger"> *</span></label>
                                                <div class="pass-group">
                                                    <input type="password" class="pass-input form-control" data-employee-add-field="password" autocomplete="new-password">
                                                    <span class="ti toggle-password ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Confirm Password <span class="text-danger"> *</span></label>
                                                <div class="pass-group">
                                                    <input type="password" class="pass-inputs form-control" data-employee-add-field="confirmPassword" autocomplete="new-password">
                                                    <span class="ti toggle-passwords ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Company<span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                        @include('hcm.partials.employee-modal-org-fields')
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="select" data-employee-add-field="employmentStatus">
                                                    <option value="active" selected>Active</option>
                                                    <option value="probation">Probation</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>		
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Base Salary (IDR)</label>
                                                <input type="number" min="0" step="0.01" class="form-control" data-employee-add-field="baseSalary" placeholder="0">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Fixed Allowance (IDR)</label>
                                                <input type="number" min="0" step="0.01" class="form-control" data-employee-add-field="fixedAllowance" placeholder="0">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">About <span class="text-danger"> *</span></label>
                                                <textarea class="form-control" rows="3"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-light border me-2" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save </button>
                                </div>
                        </div>
                        <div class="tab-pane fade" id="address" role="tabpanel" aria-labelledby="address-tab" tabindex="0">
                            <div class="modal-body">	
                                <div class="card bg-light-500 shadow-none">
                                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                                        <h6>Enable Options</h6>
                                        <div class="d-flex align-items-center justify-content-end">
                                            <div class="form-check form-switch me-2">
                                                <label class="form-check-label mt-0">
                                                <input class="form-check-input me-2" type="checkbox" role="switch">
                                                    Enable all Module
                                                </label>
                                            </div>
                                            <div class="form-check d-flex align-items-center">
                                                <label class="form-check-label mt-0">
                                                    <input class="form-check-input" type="checkbox" checked="">
                                                    Select All
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive border rounded">
                                    <table class="table">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch" checked>
                                                            Holidays
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Leaves
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Clients
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Projects
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Tasks
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Chats
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch" checked>
                                                        Assets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Timing Sheets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-light border me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#success_modal">Save </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    --}}
    <!-- /Add Employee -->

    @include('hcm.partials.employee-stepper-modal', ['mode' => 'edit'])
    {{-- Legacy edit employee modal kept temporarily for reference.
    <div class="modal fade" id="edit_employee">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center">
                        <h4 class="modal-title me-2">Edit Employee</h4><span>Employee  ID : EMP -0024</span>
                    </div>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url(Route::is('employees-grid') ? 'employees-grid' : 'employees') }}" data-employee-edit-form>
                    <div class="contact-grids-tab">
                        <ul class="nav nav-underline" id="myTab2" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="info-tab2" data-bs-toggle="tab" data-bs-target="#basic-info2" type="button" role="tab" aria-selected="true">Basic Information</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="address-tab2" data-bs-toggle="tab" data-bs-target="#address2" type="button" role="tab" aria-selected="false">Permissions</button>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content" id="myTabContent2">
                        <div class="tab-pane fade show active" id="basic-info2" role="tabpanel" aria-labelledby="info-tab2" tabindex="0">
                                <div class="modal-body pb-0 ">	
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                                <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded-circle border border-dashed me-2 flex-shrink-0 text-dark frames">
                                                    <img src="{{ URL::asset('build/img/users/user-13.jpg') }}" alt="img" class="rounded-circle">
                                                </div>                                              
                                                <div class="profile-upload">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1">Upload Profile Image</h6>
                                                        <p class="fs-12">Image should be below 4 mb</p>
                                                    </div>
                                                    <div class="profile-uploader d-flex align-items-center">
                                                        <div class="drag-upload-btn btn btn-sm btn-primary me-2">
                                                            Upload
                                                            <input type="file" class="form-control image-sign" multiple="">
                                                        </div>
                                                        <a href="javascript:void(0);" class="btn btn-light btn-sm">Cancel</a>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">First Name <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="Anthony" data-employee-edit-field="name" placeholder="Nama lengkap">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Last Name</label>
                                                <input type="text" class="form-control" value="Lewis" data-employee-edit-field="team" placeholder="Team">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Employee ID <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="Emp-001">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Joining Date <span class="text-danger"> *</span></label>
                                                <div class="input-icon-end position-relative">
                                                    <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy" value="17-10-2022">
                                                    <span class="input-icon-addon">
                                                        <i class="ti ti-calendar text-gray-7"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="Anthony">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email <span class="text-danger"> *</span></label>
                                                <input type="email" class="form-control" value="anthony@example.com	" data-employee-edit-field="email" placeholder="email@company.com">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Password <span class="text-danger"> *</span></label>
                                                <div class="pass-group">
                                                    <input type="password" class="pass-input form-control">
                                                    <span class="ti toggle-password ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Confirm Password <span class="text-danger"> *</span></label>
                                                <div class="pass-group">
                                                    <input type="password" class="pass-inputs form-control">
                                                    <span class="ti toggle-passwords ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="(123) 4567 890">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Company<span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="Abac Company">
                                            </div>									
                                        </div>
                                        @include('hcm.partials.employee-modal-org-fields')
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Employment Status</label>
                                                <select class="select" data-employee-edit-field="employmentStatus">
                                                    <option value="active" selected>Active</option>
                                                    <option value="probation">Probation</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Base Salary (IDR)</label>
                                                <input type="number" min="0" step="0.01" class="form-control" data-employee-edit-field="baseSalary" placeholder="0">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Fixed Allowance (IDR)</label>
                                                <input type="number" min="0" step="0.01" class="form-control" data-employee-edit-field="fixedAllowance" placeholder="0">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">About <span class="text-danger"> *</span></label>
                                                <textarea class="form-control" rows="3"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-light border me-2" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save </button>
                                </div>
                        </div>
                        <div class="tab-pane fade" id="address2" role="tabpanel" aria-labelledby="address-tab2" tabindex="0">
                            <div class="modal-body">	
                                <div class="card bg-light-500 shadow-none">
                                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                                        <h6>Enable Options</h6>
                                        <div class="d-flex align-items-center justify-content-end">
                                            <div class="form-check form-switch me-2">
                                                <label class="form-check-label mt-0">
                                                <input class="form-check-input me-2" type="checkbox" role="switch">
                                                    Enable all Module
                                                </label>
                                            </div>
                                            <div class="form-check d-flex align-items-center">
                                                <label class="form-check-label mt-0">
                                                    <input class="form-check-input" type="checkbox" checked="">
                                                    Select All
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive border rounded">
                                    <table class="table">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch" checked>
                                                            Holidays
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Leaves
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Clients
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Projects
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Tasks
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Chats
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch" checked>
                                                        Assets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Timing Sheets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-light border me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#success_modal">Save </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    --}}
    <!-- /Edit Employee -->

    <!-- Add Employee Success -->
    <div class="modal fade" id="success_modal" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="text-center p-3">
                        <span class="avatar avatar-lg avatar-rounded bg-success mb-3"><i class="ti ti-check fs-24"></i></span>
                        <h5 class="mb-2">Employee Added Successfully</h5>
                        <p class="mb-3">Stephan Peralt has been added with Client ID : <span class="text-primary">#EMP - 0001</span>
                        </p>
                        <div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="{{ url(Route::is('employees-grid') ? 'employees-grid' : 'employees') }}" class="btn btn-dark w-100">Back to List</a>
                                </div>
                                <div class="col-6">
                                    <a href="{{url('employee-details')}}" class="btn btn-primary w-100">Detail Page</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Add Client Success -->

    <!-- Delete Modal -->
    <div class="modal fade" id="delete_modal">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <span class="avatar avatar-xl bg-transparent-danger text-danger mb-3">
                        <i class="ti ti-trash-x fs-36"></i>
                    </span>
                    <h4 class="mb-1">Confirm Delete</h4>
                    <p class="mb-3">You want to delete all the marked items, this cant be undone once you delete.</p>
                    <div class="d-flex justify-content-center">
                        <a href="javascript:void(0);" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</a>
                        <a href="{{ url(Route::is('employees-grid') ? 'employees-grid' : 'employees') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['employee-details']))
    <!-- Edit Employee -->
    <div class="modal fade" id="edit_employee">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center">
                        <h4 class="modal-title me-2">Edit Employee</h4><span>Employee  ID : EMP -0024</span>
                    </div>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('employees-grid')}}">
                    <div class="contact-grids-tab">
                        <ul class="nav nav-underline" id="myTab2" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="info-tab3" data-bs-toggle="tab" data-bs-target="#basic-info3" type="button" role="tab" aria-selected="true">Basic Information</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="address-tab3" data-bs-toggle="tab" data-bs-target="#address3" type="button" role="tab" aria-selected="false">Permissions</button>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content" id="myTabContent2">
                        <div class="tab-pane fade show active" id="basic-info3" role="tabpanel" aria-labelledby="info-tab3" tabindex="0">
                                <div class="modal-body pb-0 ">	
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                                <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded-circle border border-dashed me-2 flex-shrink-0 text-dark frames">
                                                    <img src="{{ URL::asset('build/img/users/user-13.jpg') }}" alt="img" class="rounded-circle">
                                                </div>                                              
                                                <div class="profile-upload">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1">Upload Profile Image</h6>
                                                        <p class="fs-12">Image should be below 4 mb</p>
                                                    </div>
                                                    <div class="profile-uploader d-flex align-items-center">
                                                        <div class="drag-upload-btn btn btn-sm btn-primary me-2">
                                                            Upload
                                                            <input type="file" class="form-control image-sign" multiple="">
                                                        </div>
                                                        <a href="javascript:void(0);" class="btn btn-light btn-sm">Cancel</a>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">First Name <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="Anthony">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Last Name</label>
                                                <input type="email" class="form-control" value="Lewis">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Employee ID <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="Emp-001">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Joining Date <span class="text-danger"> *</span></label>
                                                <div class="input-icon-end position-relative">
                                                    <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy" value="17-10-2022">
                                                    <span class="input-icon-addon">
                                                        <i class="ti ti-calendar text-gray-7"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="Anthony">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email <span class="text-danger"> *</span></label>
                                                <input type="email" class="form-control" value="anthony@example.com	">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Password <span class="text-danger"> *</span></label>
                                                <div class="pass-group">
                                                    <input type="password" class="pass-input form-control">
                                                    <span class="ti toggle-password ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Confirm Password <span class="text-danger"> *</span></label>
                                                <div class="pass-group">
                                                    <input type="password" class="pass-inputs form-control">
                                                    <span class="ti toggle-passwords ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="(123) 4567 890">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Company<span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="Abac Company">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Department</label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>All Department</option>
                                                    <option selected>Finance</option>
                                                    <option>Developer</option>
                                                    <option>Executive</option>
                                                </select>
                                            </div>		
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Designation</label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option selected>Finance</option>
                                                    <option>Developer</option>
                                                    <option>Executive</option>
                                                </select>
                                            </div>		
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">About <span class="text-danger"> *</span></label>
                                                <textarea class="form-control" rows="3">As an award winning designer, I deliver exceptional quality work and bring value to your brand! With 10 years of experience and 350+ projects completed worldwide with satisfied customers, I developed the 360° brand approach, which helped me to create numerous brands that are relevant, meaningful and loved.
                                                </textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-light border me-2" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save </button>
                                </div>
                        </div>
                        <div class="tab-pane fade" id="address3" role="tabpanel" aria-labelledby="address-tab3" tabindex="0">
                            <div class="modal-body">	
                                <div class="card bg-light-500 shadow-none">
                                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                                        <h6>Enable Options</h6>
                                        <div class="d-flex align-items-center justify-content-end">
                                            <div class="form-check form-switch me-2">
                                                <label class="form-check-label mt-0">
                                                <input class="form-check-input me-2" type="checkbox" role="switch">
                                                    Enable all Module
                                                </label>
                                            </div>
                                            <div class="form-check d-flex align-items-center">
                                                <label class="form-check-label mt-0">
                                                    <input class="form-check-input" type="checkbox" checked="">
                                                    Select All
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive border rounded">
                                    <table class="table">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch" checked>
                                                            Holidays
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Leaves
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Clients
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Projects
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Tasks
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Chats
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch" checked>
                                                        Assets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Timing Sheets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-light border me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#success_modal">Save </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Employee -->

    <!-- Edit Personal -->
    <div class="modal fade" id="edit_personal">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Personal Info</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('employee-details')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Passport No <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Passport Expiry Date <span class="text-danger"> *</span></label>
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
                                    <label class="form-label">Nationality <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Religion</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Marital status <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Yes</option>
                                        <option>Nos</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employment spouse</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">No. of children</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Personal -->

    <!-- Edit Emergency Contact -->
    <div class="modal fade" id="edit_emergency">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Emergency Contact Details</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('employee-details')}}">
                    <div class="modal-body pb-0">
                        <div class="border-bottom mb-3 ">
                            <div class="row">
                                <h5 class="mb-3">Secondary Contact Details</h5>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Name <span class="text-danger"> *</span></label>
                                        <input type="text" class="form-control">
                                    </div>									
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Relationship </label>
                                        <input type="text" class="form-control">
                                    </div>									
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone No 1 <span class="text-danger"> *</span></label>
                                        <input type="text" class="form-control">
                                    </div>									
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone No 2 </label>
                                        <input type="text" class="form-control">
                                    </div>									
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <h5 class="mb-3">Secondary Contact Details</h5>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Relationship </label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone No 1 <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone No 2 </label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Emergency Contact -->

    <!-- Edit Bank -->
    <div class="modal fade" id="edit_bank">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Bank Details</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('employee-details')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Bank Details <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Bank account No </label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">IFSC Code</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Branch Address</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Bank -->
    
    <!-- Add Family -->
    <div class="modal fade" id="edit_familyinformation">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Family Information</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('employee-details')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Relationship </label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Phone </label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Passport Expiry Date <span class="text-danger"> *</span></label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Family -->

    <!-- Add Education -->
    <div class="modal fade" id="edit_education">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Education Information</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('employee-details')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Institution Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Course <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date <span class="text-danger"> *</span></label>
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
                                    <label class="form-label">End Date <span class="text-danger"> *</span></label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Education -->

    <!-- Add Experience -->
    <div class="modal fade" id="edit_experience">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Company Information</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('employee-details')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Previous Company Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Designation <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date <span class="text-danger"> *</span></label>
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
                                    <label class="form-label">End Date <span class="text-danger"> *</span></label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-check-label d-flex align-items-center mt-0">
                                        <input class="form-check-input mt-0 me-2" type="checkbox" checked="">
                                        <span class="text-dark">Check if you working present</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Experience -->

    <!-- Add Employee Success -->
    <div class="modal fade" id="success_modal" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="text-center p-3">
                        <span class="avatar avatar-lg avatar-rounded bg-success mb-3"><i class="ti ti-check fs-24"></i></span>
                        <h5 class="mb-2">Employee Added Successfully</h5>
                        <p class="mb-3">Stephan Peralt has been added with Client ID : <span class="text-primary">#EMP - 0001</span>
                        </p>
                        <div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="{{url('employees')}}" class="btn btn-dark w-100">Back to List</a>
                                </div>
                                <div class="col-6">
                                    <a href="{{url('employee-details')}}" class="btn btn-primary w-100">Detail Page</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Add Client Success -->

    <!-- Add Statuorty -->
    <div class="modal fade" id="add_bank_satutory">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Bank & Statutory</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('employee-details')}}">
                    <div class="modal-body pb-0">
                        <div class="border-bottom mb-4">
                            <h5 class="mb-3">Basic Salary Information</h5>
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Salary basis <span class="text-danger"> *</span></label>
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Weekly</option>
                                            <option>Monthly</option>
                                            <option>Annualy</option>
                                        </select>
                                    </div>									
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Salary basis</label>
                                        <input type="text" class="form-control" value="$">
                                    </div>									
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Payment type</label>
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Cash</option>
                                            <option>Debit Card</option>
                                            <option>Mobile Payment</option>
                                        </select>
                                    </div>	
                                </div>
                            </div>
                        </div>
                        <div class="border-bottom mb-4">
                            <h5 class="mb-3">PF Information</h5>
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">PF contribution <span class="text-danger"> *</span></label>
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Employee Contribution</option>
                                            <option>Employer Contribution</option>
                                            <option>Provident Fund Interest</option>
                                        </select>
                                    </div>									
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">PF No</label>
                                        <input type="text" class="form-control">
                                    </div>									
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Employee PF rate</label>
                                        <input type="text" class="form-control">
                                    </div>	
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Additional rate</label>
                                        <select class="select">
                                            <option>Select</option>
                                            <option>ESI</option>
                                            <option>EPS</option>
                                            <option>EPF</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Total rate</label>
                                        <input type="text" class="form-control">
                                    </div>	
                                </div>
                            </div>
                        </div>
                        <h5 class="mb-3">ESI Information</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">ESI contribution<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Employee Contribution</option>
                                        <option>Employer Contribution</option>
                                        <option>Maternity Benefit </option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">ESI Number</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Employee ESI rate<span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Additional rate</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>ESI</option>
                                        <option>EPS</option>
                                        <option>EPF</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Total rate</label>
                                    <input type="text" class="form-control">
                                </div>	
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Statuorty -->

    <!-- Asset Information -->
    <div class="modal fade" id="asset_info">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Asset Information</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="bg-light p-3 rounded d-flex align-items-center mb-3">
                        <span class="avatar avatar-lg flex-shrink-0 me-2">
                            <img src="{{ URL::asset('build/img/laptop.jpg') }}" alt="img" class="ig-fluid rounded-circle">
                        </span>
                        <div>
                            <h6>Dell Laptop - #343556656</h6>
                            <p class="fs-13"><span class="text-primary">AST - 001 </span><i class="ti ti-point-filled text-primary"></i> Assigned on 22 Nov, 2022 10:32AM</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="fs-13 mb-0">Type</p>
                                <p class="text-gray-9">Laptop</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="fs-13 mb-0">Brand</p>
                                <p class="text-gray-9">Dell</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="fs-13 mb-0">Category</p>
                                <p class="text-gray-9">Computer</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="fs-13 mb-0">Serial No</p>
                                <p class="text-gray-9">3647952145678</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="fs-13 mb-0">Cost</p>
                                <p class="text-gray-9">$800</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="fs-13 mb-0">Vendor</p>
                                <p class="text-gray-9">Compusoft Systems Ltd.,</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="fs-13 mb-0">Warranty</p>
                                <p class="text-gray-9">12 Jan 2022 - 12 Jan 2026</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <p class="fs-13 mb-0">Location</p>
                                <p class="text-gray-9">46 Laurel Lane, TX 79701</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <p class="fs-13 mb-2">Asset Images</p>
                        <div class="d-flex align-items-center">
                            <img src="{{ URL::asset('build/img/laptop-01.jpg') }}" alt="img" class="img-fluid rounded me-2">
                            <img src="{{ URL::asset('build/img/laptop-2.jpg') }}" alt="img" class="img-fluid rounded me-2">
                            <img src="{{ URL::asset('build/img/laptop-3.jpg') }}" alt="img" class="img-fluid rounded">
                        </div>
                    </div> 
                </div>
            </div>
        </div>
    </div>
    <!-- /Asset Information -->

    <!-- Refuse -->
    <div class="modal fade" id="refuse_msg">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Raise Issue</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('employee-details')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description<span class="text-danger"> *</span></label>
                                    <textarea class="form-control" rows="4"></textarea>
                                </div>									
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Refuse -->

    <!-- Delete Modal -->
    <div class="modal fade" id="delete_modal">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <span class="avatar avatar-xl bg-transparent-danger text-danger mb-3">
                        <i class="ti ti-trash-x fs-36"></i>
                    </span>
                    <h4 class="mb-1">Confirm Delete</h4>
                    <p class="mb-3">You want to delete all the marked items, this cant be undone once you delete.</p>
                    <div class="d-flex justify-content-center">
                        <a href="javascript:void(0);" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</a>
                        <a href="{{url('employee-details')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif
