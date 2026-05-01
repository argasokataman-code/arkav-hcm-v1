@if (Route::is(['performance-indicator']))
    <!-- Add Indicator -->
    <div class="modal fade" id="add_performance_indicator">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Indicator</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('performance-indicator')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Designation</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Web Designer</option>
                                        <option>Web Developer</option>
                                        <option>IOS Developer</option>
                                    </select>
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <h6 class="fw-medium">Technical</h6>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Customer Experience</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Marketing</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Expert/Leader</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Management</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Intermediate</option>
                                        <option>Medium</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Administration</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Presentation Skills</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>None</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Quality of Work</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>None</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Efficiency</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>None</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <h6 class="fw-medium">Organizational</h6>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Integrity</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>None</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Professionalism</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Team Work</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>None</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Critical Thinking</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Conflict Management</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Attendance</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Ability To Meet Deadline</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Active</option>
                                        <option>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Indicator</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Indicator -->

    <!-- Edit Indicator -->
    <div class="modal fade" id="edit_performance-indicator">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit New Indicator</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('performance-indicator')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Designation</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Web Designer</option>
                                        <option>Web Developer</option>
                                        <option>IOS Developer</option>
                                    </select>
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <h6 class="fw-medium">Technical</h6>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Customer Experience</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Marketing</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Expert/Leader</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Management</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Intermediate</option>
                                        <option>Medium</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Administration</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Presentation Skills</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>None</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Quality of Work</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>None</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Efficiency</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>None</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <h6 class="fw-medium">Organizational</h6>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Integrity</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>None</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Professionalism</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Team Work</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>None</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Critical Thinking</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Conflict Management</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Attendance</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Ability To Meet Deadline</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Advanced</option>
                                        <option>Intermediate</option>
                                        <option>Average</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Active</option>
                                        <option>Inactive</option>
                                    </select>
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
    <!-- /Edit Indicator -->

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
                        <a href="{{url('performance-indicator')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['performance-appraisal']))
    <!-- Add Appraisal -->
    <div class="modal fade" id="add_performance_appraisal">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Appraisal</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('performance-appraisal')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Anthony Lewis</option>
                                        <option>Brian Villalobos</option>
                                        <option>Harvey Smith</option>
                                    </select>
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Appraisal Date</label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <ul class="nav appraisal-tab nav-pills mb-3" id="pills-tab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link border   active" id="pills-home-tab" data-bs-toggle="pill" data-bs-target="#technical" type="button" role="tab" aria-selected="true">Technical</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link border" id="pills-profile-tab" data-bs-toggle="pill" data-bs-target="#organization" type="button" role="tab" aria-selected="false">Organizational</button>
                                    </li>
                                    </ul>
                            </div>
                            <div class="col-md-12">
                                <div class="tab-content appraisal-tab-content" id="pills-tabContent">
                                    <div class="tab-pane fade show active" id="technical" role="tabpanel" aria-labelledby="pills-home-tab" tabindex="0">
                                        <div class="card">
                                            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                                                <h5>Technical Competencies</h5>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table ">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Indicator</th>
                                                                <th>Expected Value</th>
                                                                <th>Set Value</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td>
                                                                    Customer Experience
                                                                </td>
                                                                <td>
                                                                    Intermediate
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Marketing
                                                                </td>
                                                                <td>
                                                                    Advanced
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Management
                                                                </td>
                                                                <td>
                                                                    Advanced
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Administration
                                                                </td>
                                                                <td>
                                                                    Advanced
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Presentation Skill
                                                                </td>
                                                                <td>
                                                                    Expert / Leader
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Quality Of Work
                                                                </td>
                                                                <td>
                                                                    Expert / Leader
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Efficiency
                                                                </td>
                                                                <td>
                                                                    Expert / Leader
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="organization" role="tabpanel" aria-labelledby="pills-profile-tab" tabindex="0">
                                        <div class="card">
                                            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                                                <h5>Organizational Competencies</h5>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table ">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Indicator</th>
                                                                <th>Expected Value</th>
                                                                <th>Set Value</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td>
                                                                    Integrity
                                                                </td>
                                                                <td>
                                                                    Beginner
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Professionalism
                                                                </td>
                                                                <td>
                                                                    Beginner
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Team Work
                                                                </td>
                                                                <td>
                                                                    Intermediate
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Critical Thinking
                                                                </td>
                                                                <td>
                                                                    Advanced
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Conflict Management
                                                                </td>
                                                                <td>
                                                                    Intermediate
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Attendance
                                                                </td>
                                                                <td>
                                                                    Intermediate
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Ability To Meet Deadline
                                                                </td>
                                                                <td>
                                                                    Advanced
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    </div>								
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Active</option>
                                        <option>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Appraisal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Appraisal -->

    <!-- Edit  Appraisal -->
    <div class="modal fade" id="edit_performance_appraisal">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit  Appraisal</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('performance-appraisal')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Anthony Lewis</option>
                                        <option>Brian Villalobos</option>
                                        <option>Harvey Smith</option>
                                    </select>
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Appraisal Date</label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <ul class="nav appraisal-tab nav-pills mb-3" id="pills-tab2" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link border   active" id="pills-home-tab2" data-bs-toggle="pill" data-bs-target="#edit_technical" type="button" role="tab" aria-selected="true">Technical</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link border" id="pills-profile-tab2" data-bs-toggle="pill" data-bs-target="#edit_organization" type="button" role="tab" aria-selected="false">Organizational</button>
                                    </li>
                                    </ul>
                            </div>
                            <div class="col-md-12">
                                <div class="tab-content appraisal-tab-content" id="pills-tabContent2">
                                    <div class="tab-pane fade show active" id="edit_technical" role="tabpanel" aria-labelledby="pills-home-tab2" tabindex="0">
                                        <div class="card">
                                            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                                                <h5>Technical Competencies</h5>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table ">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Indicator</th>
                                                                <th>Expected Value</th>
                                                                <th>Set Value</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td>
                                                                    Customer Experience
                                                                </td>
                                                                <td>
                                                                    Intermediate
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Marketing
                                                                </td>
                                                                <td>
                                                                    Advanced
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Management
                                                                </td>
                                                                <td>
                                                                    Advanced
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Administration
                                                                </td>
                                                                <td>
                                                                    Advanced
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Presentation Skill
                                                                </td>
                                                                <td>
                                                                    Expert / Leader
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Quality Of Work
                                                                </td>
                                                                <td>
                                                                    Expert / Leader
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Efficiency
                                                                </td>
                                                                <td>
                                                                    Expert / Leader
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="edit_organization" role="tabpanel" aria-labelledby="pills-profile-tab2" tabindex="0">
                                        <div class="card">
                                            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                                                <h5>Organizational Competencies</h5>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table ">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Indicator</th>
                                                                <th>Expected Value</th>
                                                                <th>Set Value</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td>
                                                                    Integrity
                                                                </td>
                                                                <td>
                                                                    Beginner
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Professionalism
                                                                </td>
                                                                <td>
                                                                    Beginner
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Team Work
                                                                </td>
                                                                <td>
                                                                    Intermediate
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Critical Thinking
                                                                </td>
                                                                <td>
                                                                    Advanced
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Conflict Management
                                                                </td>
                                                                <td>
                                                                    Intermediate
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Attendance
                                                                </td>
                                                                <td>
                                                                    Intermediate
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    Ability To Meet Deadline
                                                                </td>
                                                                <td>
                                                                    Advanced
                                                                </td>
                                                                <td>
                                                                    <select class="select">
                                                                        <option>None</option>
                                                                        <option> Beginner</option>
                                                                        <option> Intermediate</option>
                                                                        <option> Advanced</option>
                                                                        <option> Expert / Leader</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    </div>								
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Active</option>
                                        <option>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Appraisal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit  Appraisal -->

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
                        <a href="{{url('performance-appraisal')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['goal-tracking']))
    <!-- Add Goal Tracking -->
    <div class="modal fade" id="add_goal">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Goal Tracking</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('goal-tracking')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Goal Type</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Development Goals</option>
                                        <option>Project Goals</option>
                                        <option>Project Goals</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Subject </label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Target Achievement</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date </label>
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
                                    <label class="form-label">End Date </label>
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
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Active</option>
                                        <option>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Goal Tracking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Goal Tracking -->

    <!-- Edit Indicator -->
    <div class="modal fade" id="edit_goal">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Goal Tracking</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('goal-tracking')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Goal Type</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Development Goals</option>
                                        <option>Project Goals</option>
                                        <option>Project Goals</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Subject </label>
                                    <input type="text" class="form-control" value="Programming Skills">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Target Achievement</label>
                                    <input type="text" class="form-control" value="Complete a HTML course ">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date </label>
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
                                    <label class="form-label">End Date </label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy" value="13/03/2024">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control">Improve proficiency</textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Active</option>
                                        <option>Inactive</option>
                                    </select>
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
    <!-- /Edit Indicator -->

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
                        <a href="{{url('goal-tracking')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['goal-type']))
    <!-- Add Goal Type -->
    <div class="modal fade" id="add_goal_type">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Goal Type</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('goal-type')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Goal Type </label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description </label>
                                    <textarea class="form-control"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <div>
                                        <label class="form-label">Status</label>
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Active</option>
                                            <option>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Goal Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Goal Type -->

    <!-- Edit Goal Type -->
    <div class="modal fade" id="edit_goal_type">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Goal Type</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('goal-type')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Goal Type </label>
                                    <input type="text" class="form-control" value="Performance Goals">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description </label>
                                    <textarea class="form-control">Goals that focus on enhancing an employee's performance in their current role.</textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Active</option>
                                        <option>Inactive</option>
                                    </select>
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
    <!-- /Edit Goal Type -->

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
                        <a href="{{url('goal-type')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif
