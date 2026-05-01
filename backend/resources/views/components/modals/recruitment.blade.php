@if (Route::is(['job-grid', 'job-list']))
    <!-- Add Post -->
    <div class="modal fade" id="add_post">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Post Job</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('job-grid')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="contact-grids-tab pt-0">
                                <ul class="nav nav-underline" id="myTab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#basic-info" type="button" role="tab" aria-selected="true">Basic Information</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="address-tab" data-bs-toggle="tab" data-bs-target="#address" type="button" role="tab" aria-selected="false">Location</button>
                                    </li>
                                </ul>
                            </div>
                            <div class="tab-content" id="myTabContent">
                                <div class="tab-pane fade show active" id="basic-info" role="tabpanel" aria-labelledby="info-tab" tabindex="0">
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
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Job Title <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Job Description <span class="text-danger"> *</span></label>
                                                <textarea rows="3" class="form-control"></textarea>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Job Category <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>IOS</option>
                                                    <option>Web & Application</option>
                                                    <option>Networking</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Job Type <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>Full Time</option>
                                                    <option>Part Time</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Job Level <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>Team Lead</option>
                                                    <option>Manager</option>
                                                    <option>Senior</option>
                                                    <option>junior</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Experience <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>Entry Level</option>
                                                    <option>Mid Level</option>
                                                    <option>Expert</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Qualification <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>Bachelore Degree</option>
                                                    <option>Master Degree</option>
                                                    <option>Others</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Gender <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>Male</option>
                                                    <option>Female</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Min. Sallary <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>10k - 15k</option>
                                                    <option>15k -20k</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Max. Sallary <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>40k - 50k</option>
                                                    <option>50k - 60k</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Job Expired Date <span class="text-danger"> *</span></label>
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
                                                <label class="form-label">Required Skills</label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#success_modal">Save & Next</button>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="address" role="tabpanel" aria-labelledby="address-tab" tabindex="0">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Address <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">City <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>Male</option>
                                                    <option>Female</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">State <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>Male</option>
                                                    <option>Female</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Country <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>Male</option>
                                                    <option>Female</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Zip Code <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                        <div class="col-md-12">
                                            <div class="map-grid mb-3">
                                                <iframe src="about:blank" title="Map preview disabled" style="border:0;" loading="lazy" referrerpolicy="no-referrer" class="w-100"></iframe>
                                            </div>									
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#success_modal">Post</button>
                                    </div>
                                </div>
                            </div>								
                        </div>
                    </div>						
                </form>
            </div>
        </div>
    </div>
    <!-- /Post Job -->

    <!-- Add Job Success -->
    <div class="modal fade" id="success_modal" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-xm">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="text-center p-3">
                        <span class="avatar avatar-lg avatar-rounded bg-success mb-3"><i class="ti ti-check fs-24"></i></span>
                        <h5 class="mb-2">Job Posted Successfully</h5>
                        <div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <a href="{{url('job-grid')}}" class="btn btn-dark w-100">Back to List</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Add Client Success -->

    <!-- Edit Post -->
    <div class="modal fade" id="edit_post">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Job</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('job-list')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="contact-grids-tab pt-0">
                                <ul class="nav nav-underline" id="myTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#basic-infos" type="button" role="tab" aria-selected="true">Basic Information</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="address-tabs" data-bs-toggle="tab" data-bs-target="#addresss" type="button" role="tab" aria-selected="false">Location</button>
                                    </li>
                                </ul>
                            </div>
                            <div class="tab-content" id="myTabContents">
                                <div class="tab-pane fade show active" id="basic-infos" role="tabpanel" aria-labelledby="info-tab" tabindex="0">
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
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Job Title <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="Senior IOS Developer">
                                            </div>									
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Job Description <span class="text-danger"> *</span></label>
                                                <textarea rows="3" class="form-control"></textarea>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Job Category <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option selected>IOS</option>
                                                    <option>Web & Application</option>
                                                    <option>Networking</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Job Type <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>Full Time</option>
                                                    <option>Part Time</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Job Level <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option selected>Team Lead</option>
                                                    <option>Manager</option>
                                                    <option>Senior</option>
                                                    <option>junior</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Experience <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option selected>Entry Level</option>
                                                    <option>Mid Level</option>
                                                    <option>Expert</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Qualification <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option selected>Bachelore Degree</option>
                                                    <option>Master Degree</option>
                                                    <option>Others</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Gender <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>Male</option>
                                                    <option selected>Female</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Min. Sallary <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option selected>10k - 15k</option>
                                                    <option>15k -20k</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Max. Sallary <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option selected>40k - 50k</option>
                                                    <option>50k - 60k</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Job Expired Date <span class="text-danger"> *</span></label>
                                                <div class="input-icon-end position-relative">
                                                    <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy" value="29/08/2024">
                                                    <span class="input-icon-addon">
                                                        <i class="ti ti-calendar text-gray-7"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Required Skills</label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#success_modal">Save & Next</button>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="addresss" role="tabpanel" aria-labelledby="address-tab" tabindex="0">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Address <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">City <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option>Coventry</option>
                                                    <option selected>Bristol</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">State <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option selected>Lancaster</option>
                                                    <option>San Diego</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Country <span class="text-danger"> *</span></label>
                                                <select class="select">
                                                    <option>Select</option>
                                                    <option selected>UK</option>
                                                    <option>USA</option>
                                                </select>
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Zip Code <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                        <div class="col-md-12">
                                            <div class="map-grid mb-3">
                                                <iframe src="about:blank" title="Map preview disabled" style="border:0;" loading="lazy" referrerpolicy="no-referrer" class="w-100"></iframe>
                                            </div>									
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#success_modal">Post</button>
                                    </div>
                                </div>
                            </div>								
                        </div>
                    </div>						
                </form>
            </div>
        </div>
    </div>
    <!-- /Post Job -->

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
                        <a href="{{url('job-list')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['candidates-grid']))
    <!-- Candidate Details -->
    <div class="offcanvas offcanvas-end offcanvas-large" tabindex="-1" id="candidate_details">
        <div class="offcanvas-header border-bottom">
            <h4 class="d-flex align-items-center">Candidate Details 
                <span class="badge bg-primary-transparent fw-medium ms-2">Cand-001</span>
            </h4>
            <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="offcanvas" aria-label="Close">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <div class="offcanvas-body">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center flex-wrap flex-md-nowrap row-gap-3">
                        <span class="avatar avatar-xxxl candidate-img flex-shrink-0 me-3">
                            <img src="{{ URL::asset('build/img/users/user-03.jpg') }}" alt="Img">
                        </span>
                        <div class="flex-fill border rounded p-3 pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Candiate Name</p>
                                        <h6 class="fw-normal">Harold Gaynor</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Applied Role</p>
                                        <h6 class="fw-normal">Accountant</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Applied  Date</p>
                                        <h6 class="fw-normal">12/09/2024</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Email</p>
                                        <h6 class="fw-normal">harold@example.com</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Recruiter</p>
                                        <h6 class="fw-normal d-flex align-items-center">
                                            <span class="avatar avatar-xs avatar-rounded me-1">
                                                <img src="{{ URL::asset('build/img/users/user-01.jpg') }}" alt="Img">
                                            </span>
                                            Anthony Lewis
                                        </h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Recruiter</p>
                                        <span class="badge badge-purple d-inline-flex align-items-center"><i class="ti ti-point-filled me-1"></i>New</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="contact-grids-tab p-0 mb-3">
                <ul class="nav nav-underline" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active pt-0" id="info-tab" data-bs-toggle="tab" data-bs-target="#basic-info" type="button" role="tab" aria-selected="true">Profile</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link pt-0" id="address-tab" data-bs-toggle="tab" data-bs-target="#address" type="button" role="tab" aria-selected="false">Hiring Pipeline</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link pt-0" id="address-tab2" data-bs-toggle="tab" data-bs-target="#address2" type="button" role="tab" aria-selected="false">Notes</button>
                    </li>
                </ul>
            </div>
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="basic-info" role="tabpanel" aria-labelledby="info-tab" tabindex="0">
                    <div class="card">
                        <div class="card-header">
                            <h5>Personal Information</h5>
                        </div>
                        <div class="card-body pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Candiate Name</p>
                                        <h6 class="fw-normal">Harold Gaynor</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Phone</p>
                                        <h6 class="fw-normal">(146) 8964 278</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Gender</p>
                                        <h6 class="fw-normal">Male</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Date of Birth</p>
                                        <h6 class="fw-normal">23/10/2000</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Email</p>
                                        <h6 class="fw-normal">harold@example.com</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Nationality</p>
                                        <h6 class="fw-normal">Indian</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Religion</p>
                                        <h6 class="fw-normal">Christianity</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Marital status</p>
                                        <h6 class="fw-normal">No</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5>Address Information</h5>
                        </div>
                        <div class="card-body pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Address</p>
                                        <h6 class="fw-normal">1861 Bayonne Ave</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">City</p>
                                        <h6 class="fw-normal">New York</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">State</p>
                                        <h6 class="fw-normal">New York</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Country</p>
                                        <h6 class="fw-normal">United States Of America</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5>Resume</h5>
                        </div>
                        <div class="card-body pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <span class="avatar avatar-lg bg-light-500 border text-dark me-2"><i class="ti ti-file-description fs-24"></i></span>
                                        <div>
                                            <h6 class="fw-medium">Resume.doc</h6>
                                            <span>120 KB</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 text-md-end">
                                        <a href="#" class="btn btn-dark d-inline-flex align-items-center"><i class="ti ti-download me-1"></i>Download</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="address" role="tabpanel" aria-labelledby="address-tab" tabindex="0">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="fw-medium mb-2">Candidate Pipeline Stage</h5>
                            <div class="pipeline-list candidates border-0 mb-0">
                                <ul class="mb-0">
                                    <li><a href="javascript:void(0);" class="bg-purple">New</a></li>
                                    <li><a href="javascript:void(0);" class="bg-gray-100">Scheduled</a></li>
                                    <li><a href="javascript:void(0);" class="bg-grat-100">Interviewed</a></li>
                                    <li><a href="javascript:void(0);" class="bg-gray-100">Offered</a></li>
                                    <li><a href="javascript:void(0);" class="bg-gray-100">Hired / Rejected</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5>Details</h5>
                        </div>
                        <div class="card-body pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Current Status</p>
                                        <span class="badge badge-soft-purple d-inline-flex align-items-center"><i class="ti ti-point-filled me-1"></i>New</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Applied Role</p>
                                        <h6 class="fw-normal">Accountant</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Applied Date</p>
                                        <h6 class="fw-normal">12/09/2024</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Recruiter</p>
                                        <div class="d-flex align-items-center">
                                            <a href="#" class="avatar avatar-sm avatar-rounded me-2">
                                                <img src="{{ URL::asset('build/img/users/user-01.jpg') }}" alt="Img">
                                            </a>
                                            <h6><a href="#">Anthony Lewis</a></h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex align-items-center justify-content-end">
                                <a href="#" class="btn btn-dark me-3">Reject</a>
                                <a href="#" class="btn btn-primary">Move to Next Stage</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="address2" role="tabpanel" aria-labelledby="address-tab2" tabindex="0">
                    <div class="card">
                        <div class="card-header">
                            <h5>Notes</h5>
                        </div>
                        <div class="card-body">
                            <p>Harold Gaynor is a detail-oriented and highly motivated accountant with 4  years of experience in financial reporting, auditing, and tax preparation.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Candidate Details -->
@endif

@if (Route::is(['candidates']))
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
                        <a href="{{url('candidates')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['candidates-kanban']))
    <!-- Candidate Details -->
    <div class="offcanvas offcanvas-end offcanvas-large" tabindex="-1" id="candidate_details">
        <div class="offcanvas-header border-bottom">
            <h4 class="d-flex align-items-center">Candidate Details 
                <span class="badge bg-primary-transparent fw-medium ms-2">Cand-001</span>
            </h4>
            <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="offcanvas" aria-label="Close">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <div class="offcanvas-body">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center flex-wrap flex-md-nowrap row-gap-3">
                        <span class="avatar avatar-xxxl candidate-img flex-shrink-0 me-3">
                            <img src="{{ URL::asset('build/img/users/user-03.jpg') }}" alt="Img">
                        </span>
                        <div class="flex-fill border rounded p-3 pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Candiate Name</p>
                                        <h6 class="fw-normal">Harold Gaynor</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Applied Role</p>
                                        <h6 class="fw-normal">Accountant</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Applied  Date</p>
                                        <h6 class="fw-normal">12/09/2024</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Email</p>
                                        <h6 class="fw-normal">harold@example.com</h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Recruiter</p>
                                        <h6 class="fw-normal d-flex align-items-center">
                                            <span class="avatar avatar-xs avatar-rounded me-1">
                                                <img src="{{ URL::asset('build/img/users/user-01.jpg') }}" alt="Img">
                                            </span>
                                            Anthony Lewis
                                        </h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <p class="mb-1">Recruiter</p>
                                        <span class="badge badge-purple d-inline-flex align-items-center"><i class="ti ti-point-filled me-1"></i>New</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="contact-grids-tab p-0 mb-3">
                <ul class="nav nav-underline" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active pt-0" id="info-tab" data-bs-toggle="tab" data-bs-target="#basic-info" type="button" role="tab" aria-selected="true">Profile</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link pt-0" id="address-tab" data-bs-toggle="tab" data-bs-target="#address" type="button" role="tab" aria-selected="false">Hiring Pipeline</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link pt-0" id="address-tab2" data-bs-toggle="tab" data-bs-target="#address2" type="button" role="tab" aria-selected="false">Notes</button>
                    </li>
                </ul>
            </div>
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="basic-info" role="tabpanel" aria-labelledby="info-tab" tabindex="0">
                    <div class="card">
                        <div class="card-header">
                            <h5>Personal Information</h5>
                        </div>
                        <div class="card-body pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Candiate Name</p>
                                        <h6 class="fw-normal">Harold Gaynor</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Phone</p>
                                        <h6 class="fw-normal">(146) 8964 278</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Gender</p>
                                        <h6 class="fw-normal">Male</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Date of Birth</p>
                                        <h6 class="fw-normal">23/10/2000</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Email</p>
                                        <h6 class="fw-normal">harold@example.com</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Nationality</p>
                                        <h6 class="fw-normal">Indian</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Religion</p>
                                        <h6 class="fw-normal">Christianity</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Marital status</p>
                                        <h6 class="fw-normal">No</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5>Address Information</h5>
                        </div>
                        <div class="card-body pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Address</p>
                                        <h6 class="fw-normal">1861 Bayonne Ave</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">City</p>
                                        <h6 class="fw-normal">New York</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">State</p>
                                        <h6 class="fw-normal">New York</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Country</p>
                                        <h6 class="fw-normal">United States Of America</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5>Resume</h5>
                        </div>
                        <div class="card-body pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <span class="avatar avatar-lg bg-light-500 border text-dark me-2"><i class="ti ti-file-description fs-24"></i></span>
                                        <div>
                                            <h6 class="fw-medium">Resume.doc</h6>
                                            <span>120 KB</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 text-md-end">
                                        <a href="#" class="btn btn-dark d-inline-flex align-items-center"><i class="ti ti-download me-1"></i>Download</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="address" role="tabpanel" aria-labelledby="address-tab" tabindex="0">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="fw-medium mb-2">Candidate Pipeline Stage</h5>
                            <div class="pipeline-list candidates border-0 mb-0">
                                <ul class="mb-0">
                                    <li><a href="javascript:void(0);" class="bg-purple">New</a></li>
                                    <li><a href="javascript:void(0);" class="bg-gray-100">Scheduled</a></li>
                                    <li><a href="javascript:void(0);" class="bg-grat-100">Interviewed</a></li>
                                    <li><a href="javascript:void(0);" class="bg-gray-100">Offered</a></li>
                                    <li><a href="javascript:void(0);" class="bg-gray-100">Hired / Rejected</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5>Details</h5>
                        </div>
                        <div class="card-body pb-0">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Current Status</p>
                                        <span class="badge badge-soft-purple d-inline-flex align-items-center"><i class="ti ti-point-filled me-1"></i>New</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Applied Role</p>
                                        <h6 class="fw-normal">Accountant</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Applied Date</p>
                                        <h6 class="fw-normal">12/09/2024</h6>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <p class="mb-1">Recruiter</p>
                                        <div class="d-flex align-items-center">
                                            <a href="#" class="avatar avatar-sm avatar-rounded me-2">
                                                <img src="{{ URL::asset('build/img/users/user-01.jpg') }}" alt="Img">
                                            </a>
                                            <h6><a href="#">Anthony Lewis</a></h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex align-items-center justify-content-end">
                                <a href="#" class="btn btn-dark me-3">Reject</a>
                                <a href="#" class="btn btn-primary">Move to Next Stage</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="address2" role="tabpanel" aria-labelledby="address-tab2" tabindex="0">
                    <div class="card">
                        <div class="card-header">
                            <h5>Notes</h5>
                        </div>
                        <div class="card-body">
                            <p>Harold Gaynor is a detail-oriented and highly motivated accountant with 4  years of experience in financial reporting, auditing, and tax preparation.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Candidate Details -->

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
                        <a href="{{url('candidates-kanban')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['refferals']))
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
                        <a href="{{url('refferals')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['job-details']))
    <!-- Apply Job -->
    <div class="modal fade" id="apply_job">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4>Add Your Details</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('job-details')}}">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="text" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" rows="3"></textarea>
                        </div>
                        <div>
                            <label class="form-label">Upload your CV</label>
                            <input type="file" class="form-control" id="cv_upload">
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
    <!-- /Apply Job -->
@endif

@if (Route::is(['experience-level']))
    <!-- Add Experience -->
    <div class="modal fade" id="add_experience">
        <div class="modal-dialog modal-dialog-centered modal-mg w-100">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Experience Level</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('experience-level')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" class="form-control">
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
                        <button type="submit" class="btn btn-primary">Add Experience Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Experience -->

    <!-- Edit Experience -->
    <div class="modal fade" id="edit_experience">
        <div class="modal-dialog modal-dialog-centered modal-mg w-100">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Experience Level</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('experience-level')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" class="form-control" value="1-2">
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
                        <button type="submit" class="btn btn-primary">Add Experience Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Experience -->

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
                        <a href="{{url('experience-level')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif
