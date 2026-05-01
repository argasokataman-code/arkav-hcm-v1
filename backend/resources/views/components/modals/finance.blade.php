@if (Route::is(['budget-revenues']))
    <!-- Add New Revenue -->
    <div class="modal fade" id="add_new_expense">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Budget Revenue</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('budget-revenues') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Revenue Name</label>
                                    <input type="text" class="form-control">
                                </div>	
                            </div>   
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category Name</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sub Category Name</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Amount</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expense Date </label>
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
                                    <div class="d-flex align-items-center justify-content-center border border-dashed rounded p-3 flex-column">
                                        <span class="avatar avatar-lg avatar-rounded bg-primary-transparent mb-2"><i class="ti ti-folder-open fs-24"></i></span>
                                        <p class="fs-14 text-center mb-2">Drag and drop your files</p>
                                        <div class="file-upload position-relative btn btn-sm btn-primary px-3 mb-2">
                                            <i class="ti ti-upload me-1"></i>Upload
                                            <input type="file" accept="video/image">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Budget Revenue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add New Revenue -->

    <!-- Edit New Expense -->
    <div class="modal fade" id="edit_new_expense">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Budget Revenue</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('budget-revenues') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Expense Name</label>
                                    <input type="text" class="form-control" value="Training Programs">
                                </div>	
                            </div>   
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category Name</label>
                                    <input type="text" class="form-control" value="Training">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sub Category Name</label>
                                    <input type="text" class="form-control" value="Employee Training">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Amount</label>
                                    <input type="text" class="form-control" value="20000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expense Date </label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy" value="14/01/2024">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>  
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <div class="d-flex align-items-center justify-content-center border border-dashed rounded p-3 flex-column">
                                        <span class="avatar avatar-lg avatar-rounded bg-primary-transparent mb-2"><i class="ti ti-folder-open fs-24"></i></span>
                                        <p class="fs-14 text-center mb-2">Drag and drop your files</p>
                                        <div class="file-upload position-relative btn btn-sm btn-primary px-3 mb-2">
                                            <i class="ti ti-upload me-1"></i>Upload
                                            <input type="file" accept="video/image">
                                        </div>
                                    </div>
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
    <!-- /Edit New Expense -->

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
                        <a href="{{url('budget-revenues') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['budget-expenses']))
    <!-- Add New Expense -->
    <div class="modal fade" id="add_new_expense">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Budget Expense</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('budget-expenses') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Expense Name</label>
                                    <input type="text" class="form-control">
                                </div>	
                            </div>   
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category Name</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sub Category Name</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Amount</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expense Date </label>
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
                                    <div class="d-flex align-items-center justify-content-center border border-dashed rounded p-3 flex-column">
                                        <span class="avatar avatar-lg avatar-rounded bg-primary-transparent mb-2"><i class="ti ti-folder-open fs-24"></i></span>
                                        <p class="fs-14 text-center mb-2">Drag and drop your files</p>
                                        <div class="file-upload position-relative btn btn-sm btn-primary px-3 mb-2">
                                            <i class="ti ti-upload me-1"></i>Upload
                                            <input type="file" accept="video/image">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Budget Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add New Expense -->

    <!-- Edit New Expense -->
    <div class="modal fade" id="edit_new_expense">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Budget Expense</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('budget-expenses') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Expense Name</label>
                                    <input type="text" class="form-control" value="Servers">
                                </div>	
                            </div>   
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category Name</label>
                                    <input type="text" class="form-control" value="Technology">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sub Category Name</label>
                                    <input type="text" class="form-control" value="Hardware Cost">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Amount</label>
                                    <input type="text" class="form-control" value="20000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expense Date </label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy" value="14/01/2024">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>  
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <div class="d-flex align-items-center justify-content-center border border-dashed rounded p-3 flex-column">
                                        <span class="avatar avatar-lg avatar-rounded bg-primary-transparent mb-2"><i class="ti ti-folder-open fs-24"></i></span>
                                        <p class="fs-14 text-center mb-2">Drag and drop your files</p>
                                        <div class="file-upload position-relative btn btn-sm btn-primary px-3 mb-2">
                                            <i class="ti ti-upload me-1"></i>Upload
                                            <input type="file" accept="video/image">
                                        </div>
                                    </div>
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
    <!-- /Edit New Expense -->

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
                        <a href="{{url('budget-expenses') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['budgets']))
    <!-- Add Budgets -->
    <div class="modal fade" id="add_budgets">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Budget</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('budgets') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Budget Title</label>
                                    <input type="text" class="form-control">
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Choose Budget respect type</label>
                                    <div class="d-flex align-items-center">
                                        <div class="form-check me-2">
                                            <input class="form-check-input" type="radio" name="flexRadio" id="budget">
                                            <label class="form-label" for="budget">
                                                Project
                                            </label>
                                        </div>		
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="flexRadio" id="budget1">
                                            <label class="form-label" for="budget1">
                                                Category
                                            </label>
                                        </div>
                                    </div>
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
                            <div class="mb-0">
                                <label class="form-label">Expected Revenues</label>
                            </div>	
                            <div class="revenues-content">
                                <div class="row align-items-end">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Revenue Title</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <div>
                                                <label class="form-label">Revenue Amount</label>
                                                <div class="d-flex align-items-center">
                                                    <input type="text" class="form-control">
                                                    <div class="ms-2">
                                                        <a href="javascript:void(0);" class="btn btn-icon add-revenue btn-sm btn-primary rounded-circle"><i class="ti ti-plus"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>		
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Overall Revenue (A)</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Expected Expenses</label>
                            </div>	
                            <div class="expenses-content">
                                <div class="row align-items-end">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Expenses Title</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <div>
                                                <label class="form-label">Expenses Amount</label>
                                                <div class="d-flex align-items-center">
                                                    <input type="text" class="form-control">
                                                    <div class="ms-2">
                                                        <a href="javascript:void(0);" class="btn btn-icon add-expenses btn-sm btn-primary rounded-circle"><i class="ti ti-plus"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>		
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Overall Expense (B)</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expected Profit (C=A-B) </label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tax (D) </label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>  
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Budget Amount (E=C-D)</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Budget</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Budgets -->

    <!-- Edit Budgets -->
    <div class="modal fade" id="edit_budgets">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Budget</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('budgets') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Budget Title</label>
                                    <input type="text" class="form-control" value="Office Supplies">
                                </div>	
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Choose Budget respect type</label>
                                    <div class="d-flex align-items-center">
                                        <div class="form-check me-2">
                                            <input class="form-check-input" type="radio" name="flexRadio" id="budget3">
                                            <label class="form-label" for="budget3">
                                                Project
                                            </label>
                                        </div>		
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="flexRadio" id="budget2" checked="">
                                            <label class="form-label" for="budget2">
                                                Category
                                            </label>
                                        </div>
                                    </div>
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
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy" value="13/11/2024">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>   
                            <div class="mb-0">
                                <label class="form-label">Expected Revenues</label>
                            </div>	
                            <div class="revenues-content">
                                <div class="row align-items-end">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Revenue Title</label>
                                            <input type="text" class="form-control" value="Office Supplies">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <div>
                                                <label class="form-label">Revenue Amount</label>
                                                <div class="d-flex align-items-center">
                                                    <input type="text" class="form-control" value="250000">
                                                    <div class="ms-2">
                                                        <a href="javascript:void(0);" class="btn btn-icon add-revenue btn-sm btn-primary rounded-circle"><i class="ti ti-plus"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>		
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Overall Revenue (A)</label>
                                    <input type="text" class="form-control" value="250000">
                                </div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Expected Expenses</label>
                            </div>	
                            <div class="expenses-content">
                                <div class="row align-items-end">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Expenses Title</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <div>
                                                <label class="form-label">Expenses Amount</label>
                                                <div class="d-flex align-items-center">
                                                    <input type="text" class="form-control">
                                                    <div class="ms-2">
                                                        <a href="javascript:void(0);" class="btn btn-icon add-expenses btn-sm btn-primary rounded-circle"><i class="ti ti-plus"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>		
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Overall Expense (B)</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expected Profit (C=A-B) </label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tax (D) </label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>  
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Budget Amount (E=C-D)</label>
                                    <input type="text" class="form-control">
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
    <!-- /Edit Budgets -->

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
                        <a href="{{url('budgets') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['categories']))
    <!-- Add Category -->
    <div class="modal fade" id="add_category">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Category</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('categories') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Category Name</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Sub Category Name</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Category -->

    <!-- Edit Category -->
    <div class="modal fade" id="edit_category">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Category</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('categories') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Category Name</label>
                                    <input type="text" class="form-control" value="Technology">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Sub Category Name</label>
                                    <input type="text" class="form-control" value="Hardware Cost">
                                </div>									
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Category -->

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
                        <a href="{{ url('categories') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['taxes']))
    <!-- Add Policy -->
    <div class="modal fade" id="add_tax">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Tax</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('taxes') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Tax Name</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Tax Percentage(%)</label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Tax</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Policy -->

    <!-- Edit  Policy -->
    <div class="modal fade" id="edit_tax">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Tax</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('taxes') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Tax Name</label>
                                    <input type="text" class="form-control" value="VAT">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Tax Percentage(%)</label>
                                    <input type="text" class="form-control" value="20%">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control">Comprehensive tax on the supply of goods and services.</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Tax</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit  Policy -->

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
                        <a href="{{ url('taxes') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['provident-fund']))
    <!-- Add Promotion -->
    <div class="modal fade" id="add_provident-fund">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Provident Fund</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('provident-fund') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                        
                            <div class="col-md-6">
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
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Provident Fund Type</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Employee Provident Fund</option>
                                        <option>Voluntary Provident Fund</option>
                                        <option>Employee Provident Fund</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee Share(%)</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Organization Share(%)</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                        
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee Share(Amount)</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Organization Share(Amount)</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                        
                        
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Provident Fund</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Promotion -->

    <!-- Edit Promotion -->
    <div class="modal fade" id="edit_provident-fund">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Provident Fund</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('provident-fund') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                        
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee Name</label>
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
                                    <label class="form-label">Provident Fund Type</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Employee Provident Fund</option>
                                        <option>Voluntary Provident Fund</option>
                                        <option>Employee Provident Fund</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee Share(%)</label>
                                    <input type="text" class="form-control" value="2%">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Organization Share(%)</label>
                                    <input type="text" class="form-control" value="2%">
                                </div>									
                            </div>
                        
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee Share(Amount)</label>
                                    <input type="text" class="form-control" value="2000">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Organization Share(Amount)</label>
                                    <input type="text" class="form-control" value="2000">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                        
                        
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Promotion -->

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
                        <a href="{{ url('provident-fund') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['expenses']))
    <!-- Add Promotion -->
    <div class="modal fade" id="add_expenses">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Expenses</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('expenses') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Expenses</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Date</label>
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
                                    <label class="form-label">Amount</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Sr Accountant</option>
                                        <option>Sr App Developer</option>
                                        <option>Sr SEO Analyst</option>
                                    </select>
                                </div>									
                            </div>
                            
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Expenses</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Promotion -->

    <!-- Add Promotion -->
    <div class="modal fade" id="edit_expenses">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Expenses</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{ url('expenses') }}">
                    <div class="modal-body pb-0">
                        <div class="row">
                        
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Expenses</label>
                                    <input type="text" value="Online Course" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Date</label>
                                    <div class="input-icon-end position-relative">
                                        <input type="text"  value="14/04/2024" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar text-gray-7"></i>
                                        </span>
                                    </div>
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Amount</label>
                                    <input type="text" value="$3000" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <select class="select">
                                        <option>Cash</option>
                                        <option>Sr Accountant</option>
                                        <option>Sr App Developer</option>
                                        <option>Sr SEO Analyst</option>
                                    </select>
                                </div>									
                            </div>
                        
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Chnages</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Promotion -->

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
                        <a href="{{ url('expenses') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['currencies']))
    <!-- Add New Currency -->
    <div class="modal fade" id="add_new_currency">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Currency</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('currencies')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Currency Name</label>
                                    <input type="text" class="form-control" placeholder="Enter Currency Name">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Currency Symbol</label>
                                    <input type="text" class="form-control" placeholder="Enter Currency Symbol">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Currency Code</label>
                                    <input type="text" class="form-control" placeholder="Enter Currency Code">
                                </div>
                                <div class="d-flex mb-3">
                                    <label class="form-label me-3">Currency Position</label>
                                    <div class="form-check me-3">
                                        <input class="form-check-input" type="radio" name="required" id="required1" checked>
                                        <label class="form-check-label" for="required1">Front</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" name="required" type="radio" id="required2">
                                        <label class="form-check-label" for="required2">Behind</label>
                                    </div>
                                </div>
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
                        <a href="#" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Tax Rate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add New Currency -->

    <!-- Edit New Currency -->
    <div class="modal fade" id="edit_new_currency">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit New Currency</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('currencies')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Currency Name</label>
                                    <input type="text" class="form-control" placeholder="Enter Currency Name" value="Dollar">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Currency Symbol</label>
                                    <input type="text" class="form-control" placeholder="Enter Currency Symbol" value="$">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Currency Code</label>
                                    <input type="text" class="form-control" placeholder="Enter Currency Code" value="INR">
                                </div>
                                <div class="d-flex mb-3">
                                    <label class="form-label me-3">Currency Position</label>
                                    <div class="form-check me-3">
                                        <input class="form-check-input" type="radio" name="required" id="required3" checked>
                                        <label class="form-check-label" for="required3">Front</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" name="required" type="radio" id="required4">
                                        <label class="form-check-label" for="required4">Behind</label>
                                    </div>
                                </div>
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
                        <a href="#" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit New Currency -->

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
                        <a href="{{url('currencies')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['payment-gateways']))
    <!-- Add Payment Gateway -->
    <div class="modal fade" id="connect_payment">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Paypal</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal"
                        aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('payment-gateways')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Api Client ID</label>
                                    <input type="text" class="form-control" placeholder="Enter Email Address">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Api Client Secret</label>
                                    <input type="text" class="form-control" placeholder="Enter API Key">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="#" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</a>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Payment Gateway -->
@endif
