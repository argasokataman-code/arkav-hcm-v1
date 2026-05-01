@if (Route::is(['todo']))
    <!-- Add Todo -->
    <div class="modal fade" id="add_todo">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Todo</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('todo')}}">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Todo Title</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Tag</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Internal</option>
                                        <option>Projects</option>
                                        <option>Meetings</option>
                                        <option>Reminder</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Medium</option>
                                        <option>High</option>
                                        <option>Low</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label class="form-label">Descriptions</label>
                                    <div class="summernote"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Add Assignee</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Sophie</option>
                                        <option>Cameron</option>
                                        <option>Doris</option>
                                        <option>Rufana</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-0">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Completed</option>
                                        <option>Pending</option>
                                        <option>Onhold</option>
                                        <option>Inprogress</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3 ">
                                <label class="form-label">Discount<span class="text-danger"> *</span></label>
                                <div class="pass-group">
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="mb-3">
                                <label class="form-label">Limitations Invoices</label>
                                <input type="text" class="form-control">
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="mb-3">
                                <label class="form-label">Max Customers</label>
                                <input type="text" class="form-control">
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="mb-3">
                                <label class="form-label">Product</label>
                                <input type="text" class="form-control">
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="mb-3">
                                <label class="form-label">Supplier</label>
                                <input type="text" class="form-control">
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6>Plan Modules</h6>
                                <div class="form-check d-flex align-items-center">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Select All
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Employees
                                    </label>
                                </div>										
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Invoices
                                    </label>
                                </div>										
                            </div>
                            <div class="col-lg-3 col-sm-6">	
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Reports
                                    </label>
                                </div>										
                            </div>
                            <div class="col-lg-3 col-sm-6">	
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Contacts
                                    </label>
                                </div>									
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Clients
                                    </label>
                                </div>								
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Estimates
                                    </label>
                                </div>										
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Goals
                                    </label>
                                </div>										
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Deals
                                    </label>
                                </div>									
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Projects
                                    </label>
                                </div>										
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Payments
                                    </label>
                                </div>											
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Assets
                                    </label>
                                </div>											
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Leads
                                    </label>
                                </div>											
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Tickets
                                    </label>
                                </div>											
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Taxes
                                    </label>
                                </div>											
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Activities
                                    </label>
                                </div>											
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="form-check d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 text-dark fw-medium">
                                        <input class="form-check-input" type="checkbox">
                                        Pipelines
                                    </label>
                                </div>											
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-3">
                                    <label class="form-check-label mt-0 me-2 text-dark fw-medium">										
                                        Access Trial
                                        </label>
                                    <div class="form-check form-switch me-2">
                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                    </div>
                                </div>									
                            </div>
                        </div>
                        <div class="row align-items-center gx-3">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="flex-fill">
                                        <label class="form-label">Trial Days</label>
                                        <input type="text" class="form-control">
                                    </div>	
                                        
                                </div>								
                            </div>
                            <div class="col-md-3">
                                <div class="d-block align-items-center ms-3">
                                    <label class="form-check-label mt-0 me-2 text-dark">										
                                        Is Recommended
                                        </label>
                                    <div class="form-check form-switch me-2">
                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="mb-3 ">
                                    <label class="form-label">Status<span class="text-danger"> *</span></label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add New Todo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Todo -->

    <!-- Edit Todo -->
    <div class="modal fade" id="edit_todo">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Todo</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('todo')}}">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Todo Title</label>
                                    <input type="text" class="form-control" value="Update calendar and schedule">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Tag</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Internal</option>
                                        <option>Projects</option>
                                        <option>Meetings</option>
                                        <option>Reminder</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>High</option>
                                        <option selected>Medium</option>
                                        <option>Low</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="mb-3">
                                    <label class="form-label">Descriptions</label>
                                    <div class="summernote"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Add Assignee</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Sophie</option>
                                        <option>Cameron</option>
                                        <option>Doris</option>
                                        <option>Rufana</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-0">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Completed</option>
                                        <option>Pending</option>
                                        <option>Onhold</option>
                                        <option>Inprogress</option>
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
    <!-- /Edit Todo -->

    <!-- Todo Details -->
    <div class="modal fade" id="view_todo">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark">
                    <h4 class="modal-title text-white">Respond to any pending messages</h4>
                    <span class="badge badge-danger d-inline-flex align-items-center"><i class="ti ti-square me-1"></i>Urgent</span>
                    <span><i class="ti ti-star-filled text-warning"></i></span>
                    <a href="#"><i class="ti ti-trash text-white"></i></a>
                    <button type="button" class="btn-close custom-btn-close bg-transparent fs-16 text-white position-static" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <h5 class="mb-2">Task Details</h5>
                    <div class="border rounded mb-3 p-2">
                        <div class="row row-gap-3">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <span class="d-block mb-1">Created On</span>
                                    <p class="text-dark">22 July 2025</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <span class="d-block mb-1">Due Date</span>
                                    <p class="text-dark">22 July 2025</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <span class="d-block mb-1">Status</span>
                                    <span class="badge badge-soft-success d-inline-flex align-items-center">
                                        <i class="fas fa-circle fs-6 me-1"></i>Completed
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h5 class="mb-2">Description</h5>
                        <p>Hiking is a long, vigorous walk, usually on trails or footpaths in 
                            the countryside. Walking for pleasure developed in Europe during the eighteenth century. 
                            Religious pilgrimages have existed much longer but they involve walking long distances for a 
                            spiritual purpose associated with specific 
                            religions and also we achieve inner peace while we hike at a local park.
                        </p>
                    </div>
                    <div class="mb-3">
                        <h5 class="mb-2">Tags</h5>
                        <div class="d-flex align-items-center">
                            <span class="badge badge-danger me-2">Internal</span>
                            <span class="badge badge-success me-2">Projects</span>
                            <span class="badge badge-secondary">Reminder</span>
                        </div>
                    </div>
                    <div>
                        <h5 class="mb-2">Assignee</h5>
                        <div class="avatar-list-stacked avatar-group-sm">
                            <span class="avatar avatar-rounded">
                                <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-23.jpg') }}" alt="img">
                            </span>
                            <span class="avatar avatar-rounded">
                                <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-24.jpg') }}" alt="img">
                            </span>
                            <span class="avatar avatar-rounded">
                                <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-25.jpg') }}" alt="img">
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Todo Details -->

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
                        <a href="{{url('todo')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['todo-list']))
    <!-- Add Todo -->
    <div class="modal fade" id="add_todo">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Todo</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('todo')}}">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Todo Title</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Tag</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Internal</option>
                                        <option>Projects</option>
                                        <option>Meetings</option>
                                        <option>Reminder</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Medium</option>
                                        <option>High</option>
                                        <option>Low</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="mb-3 summer-description-box notes-summernote">
                                    <label class="form-label">Descriptions</label>
                                    <div class="summernote"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Add Assignee</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Sophie</option>
                                        <option>Cameron</option>
                                        <option>Doris</option>
                                        <option>Rufana</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-0">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Completed</option>
                                        <option>Pending</option>
                                        <option>Onhold</option>
                                        <option>Inprogress</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add New Todo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Todo -->

    <!-- Edit Todo -->
    <div class="modal fade" id="edit_todo">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Todo</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('todo')}}">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Todo Title</label>
                                    <input type="text" class="form-control" value="Update calendar and schedule">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Tag</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Internal</option>
                                        <option>Projects</option>
                                        <option>Meetings</option>
                                        <option>Reminder</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Medium</option>
                                        <option selected>High</option>
                                        <option>Low</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="mb-3 summer-description-box notes-summernote">
                                    <label class="form-label">Descriptions</label>
                                    <div class="summernote"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Add Assignee</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Sophie</option>
                                        <option>Cameron</option>
                                        <option>Doris</option>
                                        <option>Rufana</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-0">
                                    <label class="form-label">Status</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Completed</option>
                                        <option>Pending</option>
                                        <option>Onhold</option>
                                        <option>Inprogress</option>
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
    <!-- /Edit Todo -->

    <!-- Todo Details -->
    <div class="modal fade" id="view_todo">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-dark">
                    <h4 class="modal-title text-white">Respond to any pending messages</h4>
                    <span class="badge badge-danger d-inline-flex align-items-center"><i class="ti ti-square me-1"></i>Urgent</span>
                    <span><i class="ti ti-star-filled text-warning"></i></span>
                    <a href="#"><i class="ti ti-trash text-white"></i></a>
                    <button type="button" class="btn-close custom-btn-close bg-transparent fs-16 text-white position-static" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <h5 class="mb-2">Task Details</h5>
                    <div class="border rounded mb-3 p-2">
                        <div class="row row-gap-3">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <span class="d-block mb-1">Created On</span>
                                    <p class="text-dark">22 July 2025</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <span class="d-block mb-1">Due Date</span>
                                    <p class="text-dark">22 July 2025</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <span class="d-block mb-1">Status</span>
                                    <span class="badge badge-soft-success align-items-center justify-content-center">
                                        <i class="fas fa-circle fs-6 me-1"></i>Completed
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h5 class="mb-2">Description</h5>
                        <p>Hiking is a long, vigorous walk, usually on trails or footpaths in 
                            the countryside. Walking for pleasure developed in Europe during the eighteenth century. 
                            Religious pilgrimages have existed much longer but they involve walking long distances for a 
                            spiritual purpose associated with specific 
                            religions and also we achieve inner peace while we hike at a local park.
                        </p>
                    </div>
                    <div class="mb-3">
                        <h5 class="mb-2">Tags</h5>
                        <div class="d-flex align-items-center">
                            <span class="badge badge-danger me-2">Internal</span>
                            <span class="badge badge-success me-2">Projects</span>
                            <span class="badge badge-secondary">Reminder</span>
                        </div>
                    </div>
                    <div>
                        <h5 class="mb-2">Assignee</h5>
                        <div class="avatar-list-stacked avatar-group-sm">
                            <span class="avatar avatar-rounded">
                                <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-23.jpg') }}" alt="img">
                            </span>
                            <span class="avatar avatar-rounded">
                                <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-24.jpg') }}" alt="img">
                            </span>
                            <span class="avatar avatar-rounded">
                                <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-25.jpg') }}" alt="img">
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Todo Details -->

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
                        <a href="{{url('todo-list')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif
