@if (Route::is(['users']))
    <!-- Add Users -->
    <div class="modal fade" id="add_users">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add User</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('users') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First  Name</label>
                                    <input type="text" class="form-control">
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control">
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">User  Name</label>
                                    <input type="text" class="form-control">
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="text" class="form-control">
                                </div>	
                            </div>
                            <!-- <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <div class="pass-group">
                                        <input type="password" class="pass-input form-control">
                                        <span class="ti toggle-password ti-eye-off"></span>
                                    </div>
                                </div>	
                            </div> -->
                            <!-- <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password</label>
                                    <div class="pass-group">
                                        <input type="password" class="pass-inputs form-control">
                                        <span class="ti toggle-passwords ti-eye-off"></span>
                                    </div>
                                </div>	
                            </div> -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control">
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Employee</option>
                                        <option>Client</option>
                                    </select>
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table ">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Module Permissions</th>
                                                        <th>Read</th>
                                                        <th>Write</th>
                                                        <th>Create</th>
                                                        <th>Delete</th>
                                                        <th>Import</th>
                                                        <th>Export</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>
                                                            <h6 class="fs-14 fw-normal text-gray-9">Employee</h6>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <h6 class="fs-14 fw-normal text-gray-9">Holidays</h6>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <h6 class="fs-14 fw-normal text-gray-9">Leaves</h6>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <h6 class="fs-14 fw-normal text-gray-9">Events</h6>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
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
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Users -->

    <!-- Edit  Users -->
    <div class="modal fade" id="edit_user">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit User</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('users') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First  Name</label>
                                    <input type="text" class="form-control" value="Anthony ">
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" value=" Lewis">
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">User  Name</label>
                                    <input type="text" class="form-control" value="anthony">
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="text" class="form-control" value="anthony@example.com">
                                </div>	
                            </div>
                            <!-- <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <div class="pass-group">
                                        <input type="password" class="pass-input form-control">
                                        <span class="ti toggle-password ti-eye-off"></span>
                                    </div>
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password</label>
                                    <div class="pass-group">
                                        <input type="password" class="pass-inputs form-control">
                                        <span class="ti toggle-passwords ti-eye-off"></span>
                                    </div>
                                </div>	
                            </div> -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" value="988765544">
                                </div>	
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Employee</option>
                                        <option>Client</option>
                                    </select>
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table ">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Module Permissions</th>
                                                        <th>Read</th>
                                                        <th>Write</th>
                                                        <th>Create</th>
                                                        <th>Delete</th>
                                                        <th>Import</th>
                                                        <th>Export</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>
                                                            <h6 class="fs-14 fw-normal text-gray-9">Employee</h6>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <h6 class="fs-14 fw-normal text-gray-9">Holidays</h6>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <h6 class="fs-14 fw-normal text-gray-9">Leaves</h6>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <h6 class="fs-14 fw-normal text-gray-9">Events</h6>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-check-md">
                                                                <input class="form-check-input" type="checkbox" checked>
                                                            </div>
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
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit  Users -->

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
                        <a href="{{ url('users') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif