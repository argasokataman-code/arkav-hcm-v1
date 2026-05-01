@if (Route::is(['clients-grid', 'clients']))
    <!-- Add Client -->
    <div class="modal fade" id="add_client">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Client</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('clients')}}">
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
                                                    <i class="ti ti-photo"></i>
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
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Last Name</label>
                                                <input type="email" class="form-control">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
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
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Company</label>
                                                <input type="text" class="form-control">
                                            </div>		
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Address <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control">
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
                            <div class="modal-body pb-0 ">	
                                <div class="card bg-light-500 shadow-none">
                                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                                        <h6>Enable Options</h6>
                                        <div class="d-flex align-items-center justify-content-end">
                                            <div class="form-check form-check-md form-switch me-2">
                                                <label class="form-check-label mt-0">
                                                <input class="form-check-input me-2" type="checkbox" role="switch">
                                                    Enable all Module
                                                </label>
                                            </div>
                                            <div class="form-check form-check-md d-flex align-items-center">
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
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch" checked>
                                                            Holidays
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Leaves
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Clients
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Projects
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Tasks
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Chats
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Assets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Timing Sheets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
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
    <!-- /Add Client -->

    <!-- Edit Client -->
    <div class="modal fade" id="edit_client">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Client</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('clients')}}">
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
                                                    <i class="ti ti-photo"></i>
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
                                                <input type="text" class="form-control" value="Michael">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Last Name</label>
                                                <input type="email" class="form-control" value="Walker">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="Michael Walker">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="text" class="form-control" value="michael@example.com">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Password <span class="text-danger"> *</span></label>
                                                <div class="pass-group">
                                                    <input type="password" class="pass-input form-control" value="1234">
                                                    <span class="ti toggle-password ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Confirm Password <span class="text-danger"> *</span></label>
                                                <div class="pass-group">
                                                    <input type="password" class="pass-inputs form-control" value="1234">
                                                    <span class="ti toggle-passwords ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="(163) 2459 315">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Company</label>
                                                <input type="text" class="form-control" value="BrightWave Innovations">
                                            </div>		
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Address <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="">
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
                            <div class="modal-body pb-0 ">	
                                <div class="card bg-light-500 shadow-none">
                                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                                        <h6>Enable Options</h6>
                                        <div class="d-flex align-items-center justify-content-end">
                                            <div class="form-check form-check-md form-switch me-2">
                                                <label class="form-check-label mt-0">
                                                <input class="form-check-input me-2" type="checkbox" role="switch">
                                                    Enable all Module
                                                </label>
                                            </div>
                                            <div class="form-check form-check-md d-flex align-items-center">
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
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch" checked>
                                                            Holidays
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Leaves
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Clients
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Projects
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Tasks
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Chats
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Assets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Timing Sheets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
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
                                <button type="submit" class="btn btn-primary">Save </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Client -->

    <!-- Add Client Success -->
    <div class="modal fade" id="success_modal" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="text-center p-3">
                        <span class="avatar avatar-lg avatar-rounded bg-success mb-3"><i class="ti ti-check fs-24"></i></span>
                        <h5 class="mb-2">Client Added Successfully</h5>
                        <p class="mb-3">Stephan Peralt has been added with Client ID : <span class="text-primary">#CLT - 0024</span>
                        </p>
                        <div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="{{url('clients') }}" class="btn btn-dark w-100">Back to List</a>
                                </div>
                                <div class="col-6">
                                    <a href="{{url('client-details') }}" class="btn btn-primary w-100">Detail Page</a>
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
                        <a href="{{url('clients-grid') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['client-details']))
    <!-- Edit Client -->
    <div class="modal fade" id="edit_client">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Client</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('client-details')}}">
                    <div class="contact-grids-tab">
                        <ul class="nav nav-underline" id="myTab" role="tablist">
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
                                                    <i class="ti ti-photo"></i>
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
                                                <input type="text" class="form-control" value="Michael">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Last Name</label>
                                                <input type="email" class="form-control" value="Walker">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="Michael Walker">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="text" class="form-control" value="michael@example.com">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Password <span class="text-danger"> *</span></label>
                                                <div class="pass-group">
                                                    <input type="password" class="pass-input form-control" value="1234">
                                                    <span class="ti toggle-password ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 ">
                                                <label class="form-label">Confirm Password <span class="text-danger"> *</span></label>
                                                <div class="pass-group">
                                                    <input type="password" class="pass-inputs form-control" value="1234">
                                                    <span class="ti toggle-passwords ti-eye-off"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="(163) 2459 315">
                                            </div>									
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Company</label>
                                                <input type="text" class="form-control" value="BrightWave Innovations">
                                            </div>		
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Address <span class="text-danger"> *</span></label>
                                                <input type="text" class="form-control" value="">
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
                            <div class="modal-body pb-0 ">	
                                <div class="card bg-light-500 shadow-none">
                                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                                        <h6>Enable Options</h6>
                                        <div class="d-flex align-items-center justify-content-end">
                                            <div class="form-check form-check-md form-switch me-2">
                                                <label class="form-check-label mt-0">
                                                <input class="form-check-input me-2" type="checkbox" role="switch">
                                                    Enable all Module
                                                </label>
                                            </div>
                                            <div class="form-check form-check-md d-flex align-items-center">
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
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch" checked>
                                                            Holidays
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox" checked="">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Leaves
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Clients
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Projects
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Tasks
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Chats
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Assets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Export
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="form-check form-check-md form-switch me-2">
                                                        <label class="form-check-label mt-0">
                                                        <input class="form-check-input me-2" type="checkbox" role="switch">
                                                        Timing Sheets
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Read
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Write
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Create
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Delete
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
                                                        <label class="form-check-label mt-0">
                                                            <input class="form-check-input" type="checkbox">
                                                            Import
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-md d-flex align-items-center">
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
                                <button type="submit" class="btn btn-primary">Save </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Client -->

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
                <form action="{{url('client-details')}}">
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
                        <a href="{{url('client-details')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif
