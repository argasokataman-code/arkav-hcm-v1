@if (Route::is(['deals-grid', 'deals']))
    <!-- Add Deals -->
    <div class="modal fade" id="add_deals">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Deals</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('deals-grid')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Deal Name <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Collins</option>
                                        <option>Konopelski</option>
                                        <option>Adams</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="input-block mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label">Pipeline <span class="text-danger"> *</span></label>
                                        <a href="#" class="add-new text-primary" data-bs-target="#add_pipeline" data-bs-toggle="modal"><i class="ti ti-plus text-primary me-1"></i>Add New</a>
                                    </div>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Sales</option>
                                        <option>Marketing</option>
                                        <option>Calls</option>
                                    </select>
                                </div>		
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Open</option>
                                        <option>Won</option>
                                        <option>Lost</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Deal Value  <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Currency<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>USD</option>
                                        <option>Euro</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Period <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Period Value  <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Contact <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Darlee Robertson</option>
                                        <option>Sharon Roy</option>
                                        <option>Vaughan Lewis</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Project <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Office Management App</option>
                                        <option>Clinic Management </option>
                                        <option>Educational Platform</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Due Date <span class="text-danger"> *</span> </label>
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
                                    <label class="form-label">Expected Closing  Date <span class="text-danger"> *</span> </label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3 ">
                                    <label class="form-label">Assignee <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Active</option>
                                        <option>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Tags  <span class="text-danger"> *</span></label>
                                    <input class="input-tags form-control" placeholder="Add new" type="text" data-role="tagsinput"  name="Label" value="Collab,Rated">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Followup Date   <span class="text-danger"> *</span></label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Source  <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Google</option>
                                        <option>Social Media</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Priority   <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>High</option>
                                        <option>Low</option>
                                        <option>Medium</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3 ">
                                    <label class="form-label">Description    <span class="text-danger"> *</span></label>
                                    <textarea class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Deal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Deals -->

    <!-- Edit Deals -->
    <div class="modal fade" id="edit_deals">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Deals</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('deals-grid')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Deal Name <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Collins</option>
                                        <option>Konopelski</option>
                                        <option>Adams</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="input-block mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label">Pipeline <span class="text-danger"> *</span></label>
                                        <a href="#" class="add-new text-primary" data-bs-target="#add_pipeline" data-bs-toggle="modal"><i class="ti ti-plus text-primary me-1"></i>Add New</a>
                                    </div>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Sales</option>
                                        <option>Marketing</option>
                                        <option>Calls</option>
                                    </select>
                                </div>		
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Open</option>
                                        <option>Won</option>
                                        <option>Lost</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Deal Value  <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Currency<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Dollar</option>
                                        <option>Euro</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Period <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Period Value  <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Contact <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Darlee Robertson</option>
                                        <option>Sharon Roy</option>
                                        <option>Vaughan Lewis</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Project * <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Office Management App</option>
                                        <option selected>Clinic Management </option>
                                        <option>Educational Platform</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Due Date <span class="text-danger"> *</span> </label>
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
                                    <label class="form-label">Expected Closing  Date <span class="text-danger"> *</span> </label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3 ">
                                    <label class="form-label">Assignee <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Active</option>
                                        <option>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Tags  <span class="text-danger"> *</span></label>
                                    <input class="input-tags form-control" placeholder="Add new" type="text" data-role="tagsinput"  name="Label" value="Collab,Rated">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Followup Date   <span class="text-danger"> *</span></label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Source  <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Google</option>
                                        <option>Social Media</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Priority   <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>High</option>
                                        <option>Low</option>
                                        <option>Medium</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3 ">
                                    <label class="form-label">Description    <span class="text-danger"> *</span></label>
                                    <textarea class="form-control"></textarea>
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
    <!-- /Edit Deals -->

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
                        <a href="{{url('deals-grid')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->

    <!-- Add Pipeline -->
    <div class="modal fade" id="add_pipeline">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Pipeline</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('pipeline')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Pipeline Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="input-block mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label">Pipeline Stages   <span class="text-danger"> *</span></label>
                                        <a href="#" class="add-new text-primary" data-bs-toggle="modal" data-bs-target="#add_stage"><i class="ti ti-plus text-primary me-1"></i>Add New</a>
                                    </div>
                                    <div class="p-3 border border-gray br-5 mb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><i class="ti ti-grip-vertical"></i></span>
                                                <h6 class="fs-14 fw-normal">Inpipline</h6>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="text-default"><span class="me-2"><i class="ti ti-edit"></i></span></a>
                                                <a href="#"  class="text-default"><span><i class="ti ti-trash"></i></span></a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 border border-gray br-5 mb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><i class="ti ti-grip-vertical"></i></span>
                                                <h6 class="fs-14 fw-normal">Follow Up</h6>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="text-default"><span class="me-2"><i class="ti ti-edit"></i></span></a>
                                                <a href="#"  class="text-default"><span><i class="ti ti-trash"></i></span></a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 border border-gray br-5">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><i class="ti ti-grip-vertical"></i></span>
                                                <h6 class="fs-14 fw-normal">Schedule Service</h6>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="text-default"><span class="me-2"><i class="ti ti-edit"></i></span></a>
                                                <a href="#"  class="text-default"><span><i class="ti ti-trash"></i></span></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Access</label>
                                    <div class="d-flex  access-item nav">
                                        <div class="d-flex align-items-center">
                                            <div class="radio-btn d-flex align-items-center " data-bs-toggle="tab" data-bs-target="#all">
                                                <input type="radio" class="status-radio me-2" id="all" name="status" checked >
                                                <label for="all">All</label>
                                            </div>
                                            <div class="radio-btn d-flex align-items-center " data-bs-toggle="tab" data-bs-target="#select-person">
                                                <input type="radio" class="status-radio me-2" id="select" name="status">
                                                <label for="select">Select Person</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade" id="select-person">
                                            <div class="access-wrapper">	
                                                <div class="p-3 border border-gray br-5 mb-2">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center file-name-icon">
                                                            <a href="#" class="avatar avatar-md border avatar-rounded">
                                                                <img src="{{ URL::asset('build/img/profiles/avatar-20.jpg') }}" class="img-fluid" alt="img">
                                                            </a>
                                                            <div class="ms-2">
                                                                <h6 class="fw-medium"><a href="#">Sharon Roy</a></h6>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex align-items-center">
                                                            <a href="#" class="text-danger">Remove</a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-3 border border-gray br-5 mb-2">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center file-name-icon">
                                                            <a href="#" class="avatar avatar-md border avatar-rounded">
                                                                <img src="{{ URL::asset('build/img/profiles/avatar-21.jpg') }}" class="img-fluid" alt="img">
                                                            </a>
                                                            <div class="ms-2">
                                                                <h6 class="fw-medium"><a href="#">Sharon Roy</a></h6>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex align-items-center">
                                                            <a href="#" class="text-danger">Remove</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pipeline-access">Add Pipeline</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Pipeline -->

    <!-- Add New Stage -->
    <div class="modal fade" id="add_stage">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Stage</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('pipeline')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Stage Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>	
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Stage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add New Stage -->
@endif

@if (Route::is(['deals-details']))
    <!-- Add Company -->
    <div class="modal fade" id="add_company">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Company</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('companies')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                    <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded-circle border border-dashed me-2 flex-shrink-0 text-dark frames">
                                        <img src="{{ URL::asset('build/img/profiles/avatar-30.jpg') }}" alt="img" class="rounded-circle">
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
                                    <label class="form-label">Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Account URL</label>
                                    <input type="text" class="form-control">
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
                                    <label class="form-label">Website</label>
                                    <input type="text" class="form-control">
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
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Name <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Advanced</option>
                                        <option>Basic</option>
                                        <option>Enterprise</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Type <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Monthly</option>
                                        <option>Yearly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 ">
                                    <label class="form-label">Currency <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>USD</option>
                                        <option>Euro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 ">
                                    <label class="form-label">Language <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>English</option>
                                        <option>Chinese</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 ">
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
                        <button type="submit" class="btn btn-primary">Add Company</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Company -->

    <!-- Edit Company -->
    <div class="modal fade" id="edit_company">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Company</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('companies')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                    <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded-circle border border-dashed me-2 flex-shrink-0 text-dark frames">
                                        <img src="{{ URL::asset('build/img/profiles/avatar-30.jpg') }}" alt="img" class="rounded-circle">
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
                                    <label class="form-label">Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control" value="Stellar Dynamics">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" value="sophie@example.com">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Account URL</label>
                                    <input type="text" class="form-control" value="sd.example.com">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone Number <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control" value="+1 895455450">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Website</label>
                                    <input type="text" class="form-control" value="Admin Website">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Password <span class="text-danger"> *</span></label>
                                    <div class="pass-group">
                                        <input type="password" class="pass-input form-control" value="123">
                                        <span class="ti toggle-password ti-eye-off"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Confirm Password <span class="text-danger"> *</span></label>
                                    <div class="pass-group">
                                        <input type="password" class="pass-inputs form-control" value="123">
                                        <span class="ti toggle-passwords ti-eye-off"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Name <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Advanced</option>
                                        <option>Basic</option>
                                        <option>Enterprise</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Type <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Monthly</option>
                                        <option>Yearly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 ">
                                    <label class="form-label">Currency <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Dollar</option>
                                        <option>Euro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 ">
                                    <label class="form-label">Language <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>English</option>
                                        <option>Chinese</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 ">
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
    <!-- /Edit Company -->

    <!-- Add Note -->
    <div class="modal fade" id="add_notes" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header header-border align-items-center justify-content-between">
                    <h5 class="modal-title">Add New Note</h5>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('company-details')}}">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger"> *</span></label>
                            <input class="form-control" type="text">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Note <span class="text-danger"> *</span></label>
                            <textarea class="form-control" rows="4"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Attachment <span class="text-danger"> *</span></label>
                            <div class="d-flex align-items-center justify-content-center border border-dashed rounded p-3 flex-column">
                                <span class="avatar avatar-lg avatar-rounded bg-primary-transparent mb-2"><i class="ti ti-folder-open fs-24"></i></span>
                                <p class="fs-14 text-center mb-2">Drag and drop your files</p>
                                <div class="file-upload position-relative btn btn-sm btn-primary px-3 mb-2">
                                    <i class="ti ti-upload me-1"></i>Upload
                                    <input type="file" accept="video/image">
                                </div>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Uploaded Files</label>
                            <div class="border bg-light-500 rounded mb-3 p-3">
                                <h6 class="fw-medium mb-1">Projectneonals teyys.xls</h6>
                                <p class="mb-2">4.25 MB</p>
                                <div class="progress progress-xs mb-2">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 45%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <p>45%</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between bg-light-500 rounded p-3">
                                <div>
                                    <h6 class="fw-medium mb-1">tes.txt</h6>
                                    <p>1.2 MB</p>
                                </div>
                                <a href="javascript:void(0);" class="btn btn-sm btn-icon text-danger"><i class="ti ti-trash fs-20"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="d-flex align-items-center justify-content-end m-0">
                            <button type="button" class="btn btn-outline-light border me-2">Cancel</button>
                            <button class="btn btn-primary" type="submit">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Note -->

    <!-- Add Call -->
    <div class="modal fade" id="add_call" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header header-border align-items-center justify-content-between">
                    <h5 class="modal-title">Create Call Log</h5>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('company-details')}}">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Busy</option>
                                        <option>Unavailable</option>
                                        <option>No Answer</option>
                                        <option>Wrong Number</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Followup Date <span class="text-danger"> *</span></label>
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
                                    <label class="form-label">Note <span class="text-danger"> *</span></label>
                                    <textarea class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="check">
                                    <label class="form-check-label" for="check">
                                        Create a follow up task
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="d-flex align-items-center justify-content-end m-0">
                            <button type="button" class="btn btn-outline-light border me-2">Cancel</button>
                            <button class="btn btn-primary" type="submit">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Call -->

    <!-- Create File -->
    <div class="modal fade" id="create_file" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header header-border align-items-center justify-content-between">
                    <h5 class="modal-title">Create  New File</h5>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="add-info-fieldset">
                    <div class="add-details-wizard p-3 pb-0">
                        <ul class="progress-bar-wizard d-flex align-items-center border-bottom">
                            <li class="active p-2 pt-0">
                                <h6 class="fw-medium">Basic Information</h6>
                            </li>
                            <li class="p-2 pt-0">									
                                <h6 class="fw-medium">Add Recipient</h6>
                            </li>
                        </ul>
                    </div>
                    <fieldset id="first-field-file">
                        <form action="{{url('company-details')}}">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Choose Deal <span class="text-danger"> *</span></label>
                                            <select class="select">
                                                <option>Select</option>
                                                <option>Collins</option>
                                                <option>Wisozk</option>
                                                <option>Walter</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Document Type <span class="text-danger"> *</span></label>
                                            <select class="select">
                                                <option>Select</option>
                                                <option>Contract</option>
                                                <option>Proposal</option>
                                                <option>Quote</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Owner <span class="text-danger"> *</span></label>
                                            <select class="select">
                                                <option>Select</option>
                                                <option>Admin</option>
                                                <option>Jackson Daniel</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Title <span class="text-danger"> *</span></label>
                                            <input class="form-control" type="text" placeholder="Enter Name">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="input-block mb-3">
                                            <label class="form-label">Locale <span class="text-danger"> *</span></label>
                                            <select class="select">
                                                <option>Select</option>
                                                <option>en</option>
                                                <option>es</option>
                                                <option>ru</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Username <span class="text-danger"> *</span></label>
                                            <input class="form-control" type="text">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email <span class="text-danger"> *</span></label>
                                            <input class="form-control" type="text">
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
                                            <input class="form-control" type="text">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Company</label>
                                            <input class="form-control" type="text">
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="bg-light-500 rounded p-3 pb-0 mb-3">
                                            <h4>Signature</h4>
                                            <ul class="nav">
                                                <li class="nav-item form-check form-check-md mb-2">
                                                    <span data-bs-toggle="tab" data-bs-target="#nosign">
                                                    <input type="radio" class="form-check-input mt-2" id="sign1" name="email">
                                                    <label for="sign1" class="form-check-label"><span class="d-block fw-medium text-dark mb-1">No Signature</span>This document does not require a signature before acceptance.</label>
                                                </span>
                                                </li>
                                                <li class="nav-item form-check form-check-md mb-3">
                                                    <span class="active mb-0" data-bs-toggle="tab" data-bs-target="#use-esign">		
                                                        <input type="radio" class="form-check-input mt-2" id="sign2" name="email" checked>
                                                        <label for="sign2" class="form-check-label"><span class="d-block fw-medium text-dark mb-1">Use e-signature</span>This document require e-signature before acceptance.</label>
                                                    </span>
                                                </li>
                                            </ul>
                                            <div class="tab-content">
                                                <div class="tab-pane fade" id="nosign"></div>
                                                <div class="tab-pane show active" id="use-esign">
                                                    <div class="mb-0">
                                                        <label class="form-label">Document Signers <span class="text-danger"> *</span></label>
                                                    </div>
                                                    <div class="sign-content">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <input class="form-control" type="text">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="d-flex align-items-center mb-3">
                                                                    <div class="flex-fill me-2">
                                                                        <input class="form-control" type="text">
                                                                    </div>
                                                                    <div>
                                                                        <a href="javascript:void(0);" class="btn btn-icon btn-sm add-sign text-primary"><i class="ti ti-circle-plus"></i></a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div>
                                            <label class="form-label">Content <span class="text-danger"> *</span></label>
                                            <textarea class="form-control" rows="3" placeholder="Add Content"></textarea>
                                        </div>
                                    </div>
                                </div>									
                            </div>
                            <div class="modal-footer">
                                <div class="d-flex align-items-center justify-content-end">
                                    <button type="button" class="btn btn-outline-light border me-2">Cancel</button>
                                    <button class="btn btn-primary wizard-next-btn" type="button">Next</button>
                                </div>
                            </div>
                        </form>
                    </fieldset>
                    <fieldset>
                        <form action="{{url('company-details')}}">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="bg-light-500 rounded p-3 pb-0 mb-3">
                                            <h4>Signature</h4>
                                            <ul class="nav">
                                                <li class="nav-item form-check form-check-md mb-2">
                                                    <span data-bs-toggle="tab" data-bs-target="#nosign2">
                                                    <input type="radio" class="form-check-input mt-2" id="sign3" name="email">
                                                    <label for="sign3" class="form-check-label"><span class="d-block fw-medium text-dark mb-1">No Signature</span>This document does not require a signature before acceptance.</label>
                                                </span>
                                                </li>
                                                <li class="nav-item form-check form-check-md mb-3">
                                                    <span class="active mb-0" data-bs-toggle="tab" data-bs-target="#use-esign2">		
                                                        <input type="radio" class="form-check-input mt-2" id="sign4" name="email" checked>
                                                        <label for="sign4" class="form-check-label"><span class="d-block fw-medium text-dark mb-1">Use e-signature</span>This document require e-signature before acceptance.</label>
                                                    </span>
                                                </li>
                                            </ul>
                                            <div class="tab-content">
                                                <div class="tab-pane fade" id="nosign2"></div>
                                                <div class="tab-pane show active" id="use-esign2">
                                                    <div class="mb-0">
                                                        <label class="form-label">Document Signers <span class="text-danger"> *</span></label>
                                                    </div>
                                                    <div class="sign-content">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <input class="form-control" type="text">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="d-flex align-items-center mb-3">
                                                                    <div class="flex-fill me-2">
                                                                        <input class="form-control" type="text">
                                                                    </div>
                                                                    <div>
                                                                        <a href="javascript:void(0);" class="btn btn-icon btn-sm add-sign text-primary"><i class="ti ti-circle-plus"></i></a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="mb-3">
                                            <label class="form-label">Message Subject <span class="text-danger"> *</span></label>
                                            <input class="form-control" type="text">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Message Text <span class="text-danger"> *</span></label>
                                            <textarea class="form-control" rows="3"></textarea>
                                        </div>
                                        <button type="button" class="btn btn-dark mb-3">Send Now</button>
                                        <div class="border border-success bg-success-transparent p-3 rounded">
                                            <p class="d-flex align-items-center"><i class="ti ti-circle-check fs-24 me-2"></i> Document Sent successfully to the Selected Recipients</p>
                                        </div>
                                    </div>
                                </div>									
                            </div>
                            <div class="modal-footer">
                                <div class="d-flex align-items-center justify-content-end">
                                    <button type="button" class="btn btn-outline-light border me-2">Cancel</button>
                                    <button class="btn btn-primary" type="button" data-bs-dismiss="modal">Save & Next</button>
                                </div>
                            </div>
                        </form>
                    </fieldset>
                </div>
            </div>
        </div>
    </div>
    <!-- /Create File -->

    <!-- Connect Account -->
    <div class="modal fade" id="connect_account" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header header-border align-items-center justify-content-between">
                    <h5 class="modal-title">Connect Account</h5>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('company-details')}}">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Account Type <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Gmail</option>
                                        <option>Outlook</option>
                                        <option>Imap</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div>
                                    <p class="text-dark fw-medium mb-2">Sync emails from</p>
                                    <div class="form-check form-check-md mb-2">
                                        <input type="radio" class="form-check-input" id="email_1" name="email">
                                        <label for="email_1" class="form-check-label text-dark">Now</label>
                                    </div>
                                    <div class="form-check form-check-md mb-2">
                                        <input type="radio" class="form-check-input" id="email_2" name="email">
                                        <label for="email_2" class="form-check-label text-dark">1 month ago</label>
                                    </div>
                                    <div class="form-check form-check-md mb-2">
                                        <input type="radio" class="form-check-input" id="email_3" name="email">
                                        <label for="email_3" class="form-check-label text-dark">3 months ago</label>
                                    </div>
                                    <div class="form-check form-check-md">
                                        <input type="radio" class="form-check-input" id="email_4" name="email">
                                        <label for="email_4" class="form-check-label text-dark">6 months ago</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="d-flex align-items-center justify-content-end m-0">
                            <button class="btn btn-outline-light border me-2" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#success_modal">Connect Account</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Connect Account -->

    <!-- Connect Account Success -->
    <div class="modal fade" id="success_modal" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="text-center p-3">
                        <span class="avatar avatar-lg avatar-rounded bg-success mb-3"><i class="ti ti-check fs-24"></i></span>
                        <h5 class="mb-2">Email Connected Successfully!!!</h5>
                        <p class="mb-3">Email Account is configured with <span class="d-block text-dark">“example@example.com”. </span>
                            Now you can access email.
                        </p>
                        <a href="{{url('email')}}" class="btn btn-primary w-100">Go to Email</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Connect Account Success -->
@endif

@if (Route::is(['pipeline']))
    <!-- Add Pipeline -->
    <div class="modal fade" id="add_pipeline">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Pipeline</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('pipeline')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Pipeline Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="input-block mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label">Pipeline Stages   <span class="text-danger"> *</span></label>
                                        <a href="#" class="add-new text-primary" data-bs-toggle="modal" data-bs-target="#add_stage"><i class="ti ti-plus text-primary me-1"></i>Add New</a>
                                    </div>
                                    <div class="p-3 border border-gray br-5 mb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><i class="ti ti-grip-vertical"></i></span>
                                                <h6 class="fs-14 fw-normal">Inpipline</h6>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="text-default" data-bs-toggle="modal" data-bs-target="#edit_stage"><span class="me-2"><i class="ti ti-edit"></i></span></a>
                                                <a href="#"  class="text-default" data-bs-toggle="modal" data-bs-target="#delete_modal"><span><i class="ti ti-trash"></i></span></a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 border border-gray br-5 mb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><i class="ti ti-grip-vertical"></i></span>
                                                <h6 class="fs-14 fw-normal">Follow Up</h6>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="text-default" data-bs-toggle="modal" data-bs-target="#edit_stage"><span class="me-2"><i class="ti ti-edit"></i></span></a>
                                                <a href="#"  class="text-default" data-bs-toggle="modal" data-bs-target="#delete_modal"><span><i class="ti ti-trash"></i></span></a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 border border-gray br-5">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><i class="ti ti-grip-vertical"></i></span>
                                                <h6 class="fs-14 fw-normal">Schedule Service</h6>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="text-default" data-bs-toggle="modal" data-bs-target="#edit_stage"><span class="me-2"><i class="ti ti-edit"></i></span></a>
                                                <a href="#"  class="text-default"><span><i class="ti ti-trash" data-bs-toggle="modal" data-bs-target="#delete_modal"></i></span></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Access</label>
                                    <div class="d-flex  access-item nav">
                                        <div class="d-flex align-items-center">
                                            <div class="radio-btn d-flex align-items-center " data-bs-toggle="tab" data-bs-target="#all">
                                                <input type="radio" class="status-radio me-2" id="all" name="status" checked >
                                                <label for="all">All</label>
                                            </div>
                                            <div class="radio-btn d-flex align-items-center " data-bs-toggle="tab" data-bs-target="#select-person">
                                                <input type="radio" class="status-radio me-2" id="select" name="status">
                                                <label for="select">Select Person</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade" id="select-person">
                                            <div class="access-wrapper">	
                                                <div class="p-3 border border-gray br-5 mb-2">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center file-name-icon">
                                                            <a href="#" class="avatar avatar-md border avatar-rounded">
                                                                <img src="{{ URL::asset('build/img/profiles/avatar-20.jpg') }}" class="img-fluid" alt="img">
                                                            </a>
                                                            <div class="ms-2">
                                                                <h6 class="fw-medium"><a href="#">Sharon Roy</a></h6>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex align-items-center">
                                                            <a href="#" class="text-danger">Remove</a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-3 border border-gray br-5 mb-2">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center file-name-icon">
                                                            <a href="#" class="avatar avatar-md border avatar-rounded">
                                                                <img src="{{ URL::asset('build/img/profiles/avatar-21.jpg') }}" class="img-fluid" alt="img">
                                                            </a>
                                                            <div class="ms-2">
                                                                <h6 class="fw-medium"><a href="#">Sharon Roy</a></h6>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex align-items-center">
                                                            <a href="#" class="text-danger">Remove</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pipeline-access">Add Pipeline</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Pipeline -->

    <!-- Edit Pipeline -->
    <div class="modal fade" id="edit_pipeline">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Pipeline</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('pipeline')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Pipeline Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control" value="Marketing">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="input-block mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label">Pipeline Stages   <span class="text-danger"> *</span></label>
                                        <a href="#" class="add-new text-primary" data-bs-toggle="modal" data-bs-target="#add_stage"><i class="ti ti-plus text-primary me-1"></i>Add New</a>
                                    </div>
                                    <div class="p-3 border border-gray br-5 mb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><i class="ti ti-grip-vertical"></i></span>
                                                <h6 class="fs-14 fw-normal">Inpipline</h6>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="text-default"><span class="me-2"><i class="ti ti-edit"></i></span></a>
                                                <a href="#"  class="text-default"><span><i class="ti ti-trash"></i></span></a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 border border-gray br-5 mb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><i class="ti ti-grip-vertical"></i></span>
                                                <h6 class="fs-14 fw-normal">Follow Up</h6>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="text-default"><span class="me-2"><i class="ti ti-edit"></i></span></a>
                                                <a href="#"  class="text-default"><span><i class="ti ti-trash"></i></span></a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 border border-gray br-5">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <span class="me-2"><i class="ti ti-grip-vertical"></i></span>
                                                <h6 class="fs-14 fw-normal">Schedule Service</h6>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <a href="#" class="text-default"><span class="me-2"><i class="ti ti-edit"></i></span></a>
                                                <a href="#"  class="text-default"><span><i class="ti ti-trash"></i></span></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Access</label>
                                    <div class="d-flex  access-item nav">
                                        <div class="d-flex align-items-center">
                                            <div class="radio-btn d-flex align-items-center " data-bs-toggle="tab" data-bs-target="#all2">
                                                <input type="radio" class="status-radio me-2" id="all2" name="status" checked >
                                                <label for="all2">All</label>
                                            </div>
                                            <div class="radio-btn d-flex align-items-center " data-bs-toggle="tab" data-bs-target="#select-person2">
                                                <input type="radio" class="status-radio me-2" id="select2" name="status">
                                                <label for="select2">Select Person</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-content">
                                        <div class="tab-pane fade" id="select-person2">
                                            <div class="access-wrapper">	
                                                <div class="p-3 border border-gray br-5 mb-2">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center file-name-icon">
                                                            <a href="#" class="avatar avatar-md border avatar-rounded">
                                                                <img src="{{ URL::asset('build/img/profiles/avatar-20.jpg') }}" class="img-fluid" alt="img">
                                                            </a>
                                                            <div class="ms-2">
                                                                <h6 class="fw-medium"><a href="#">Sharon Roy</a></h6>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex align-items-center">
                                                            <a href="#" class="text-danger">Remove</a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-3 border border-gray br-5 mb-2">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center file-name-icon">
                                                            <a href="#" class="avatar avatar-md border avatar-rounded">
                                                                <img src="{{ URL::asset('build/img/profiles/avatar-21.jpg') }}" class="img-fluid" alt="img">
                                                            </a>
                                                            <div class="ms-2">
                                                                <h6 class="fw-medium"><a href="#">Sharon Roy</a></h6>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex align-items-center">
                                                            <a href="#" class="text-danger">Remove</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pipeline-access">Add Pipeline</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Pipeline -->

    <!-- Pipeline Access -->
    <div class="modal fade" id="pipeline-access">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Pipeline Access</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('pipeline')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control" placeholder="Search">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-search text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <div class="p-2 border br-5">
                                        <div class="pipeline-access-items">
                                            <div class="d-flex  align-items-center p-2">
                                                <div class="form-check  form-check-md me-2">
                                                    <input class="form-check-input" type="checkbox">
                                                </div>
                                                <div class="d-flex align-items-center file-name-icon">
                                                    <a href="#" class="avatar avatar-md border avatar-rounded">
                                                        <img src="{{ URL::asset('build/img/profiles/avatar-19.jpg') }}" class="img-fluid" alt="img">
                                                    </a>
                                                    <div class="ms-2">
                                                        <h6 class="fw-medium fs-12"><a href="#">Darlee Robertson</a></h6>
                                                        <span class="fs-10 fw-normal">Darlee Robertson</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center p-2">
                                                <div class="form-check form-check-md me-2">
                                                    <input class="form-check-input" type="checkbox">
                                                </div>
                                                <div class="d-flex align-items-center file-name-icon">
                                                    <a href="#" class="avatar avatar-md border avatar-rounded">
                                                        <img src="{{ URL::asset('build/img/profiles/avatar-20.jpg') }}" class="img-fluid" alt="img">
                                                    </a>
                                                    <div class="ms-2">
                                                        <h6 class="fw-medium fs-12"><a href="#">Sharon Roy</a></h6>
                                                        <span class="fs-10 fw-normal">Installer</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center p-2">
                                                <div class="form-check form-check-md me-2">
                                                    <input class="form-check-input" type="checkbox">
                                                </div>
                                                <div class="d-flex align-items-center file-name-icon">
                                                    <a href="#" class="avatar avatar-md border avatar-rounded">
                                                        <img src="{{ URL::asset('build/img/profiles/avatar-21.jpg') }}" class="img-fluid" alt="img">
                                                    </a>
                                                    <div class="ms-2">
                                                        <h6 class="fw-medium fs-12"><a href="#">Vaughan Lewis</a></h6>
                                                        <span class="fs-10 fw-normal">Senior  Manager</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center p-2">
                                                <div class="form-check form-check-md me-2">
                                                    <input class="form-check-input" type="checkbox">
                                                </div>
                                                <div class="d-flex align-items-center file-name-icon">
                                                    <a href="#" class="avatar avatar-md border avatar-rounded">
                                                        <img src="{{ URL::asset('build/img/users/user-33.jpg') }}" class="img-fluid" alt="img">
                                                    </a>
                                                    <div class="ms-2">
                                                        <h6 class="fw-medium fs-12"><a href="#">Jessica Louise</a></h6>
                                                        <span class="fs-10 fw-normal">Test Engineer</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center p-2">
                                                <div class="form-check form-check-md me-2">
                                                    <input class="form-check-input" type="checkbox">
                                                </div>
                                                <div class="d-flex align-items-center file-name-icon">
                                                    <a href="#" class="avatar avatar-md border avatar-rounded">
                                                        <img src="{{ URL::asset('build/img/users/user-34.jpg') }}" class="img-fluid" alt="img">
                                                    </a>
                                                    <div class="ms-2">
                                                        <h6 class="fw-medium fs-12"><a href="#">Test Engineer</a></h6>
                                                        <span class="fs-10 fw-normal">UI /UX Designer</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Pipeline Access -->

    <!-- Add New Stage -->
    <div class="modal fade" id="add_stage">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Stage</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('pipeline')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Stage Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>	
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Stage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add New Stage -->

    <!-- Edit Stage -->
    <div class="modal fade" id="edit_stage">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Stage</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('pipeline')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Edit Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control" value="Inpipeline">
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
    <!-- /Edit Stage -->

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
                        <a href="{{url('pipeline')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif
