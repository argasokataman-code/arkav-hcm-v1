@if (Route::is(['cities']))
    <!-- Add City -->
    <div class="modal fade" id="add_cities">
        <div class="modal-dialog modal-dialog-centered modal-mg w-100">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add City</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('cities') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">City Name</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">	
                                <div class="mb-3">
                                    <label class="form-label">State Name</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>California</option>
                                        <option>New York</option>
                                        <option>Texas</option>
                                    </select>
                                </div>								
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Country Name</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>United States</option>
                                        <option>Germany</option>
                                        <option>Canada</option>
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
                        <button type="submit" class="btn btn-primary">Add City</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add City -->

    <!-- Edit City -->
    <div class="modal fade" id="edit_cities">
        <div class="modal-dialog modal-dialog-centered modal-mg w-100">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit City</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('cities') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">City Name</label>
                                    <input type="text" class="form-control" value="Los Angeles">
                                </div>									
                            </div>
                            <div class="col-md-12">	
                                <div class="mb-3">
                                    <label class="form-label">State Name</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>California</option>
                                        <option>New York</option>
                                        <option>Texas</option>
                                    </select>
                                </div>								
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Country Name</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>United States</option>
                                        <option>Germany</option>
                                        <option>Canada</option>
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
    <!-- /Edit City -->

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
                        <a href="{{ url('cities') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif