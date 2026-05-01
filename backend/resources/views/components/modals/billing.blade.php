@if (Route::is(['pricing']))
    <!-- Add Plan -->
    <div class="modal fade" id="add_plans">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Plan</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('pricing')}}">
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
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Position<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>1</option>
                                        <option>2</option>
                                        <option>3</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Currency<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>USD</option>
                                        <option>EURO</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <label class="form-label">Plan Currency<span class="text-danger"> *</span></label>
                                        <span class="text-primary"><i class="fa-solid fa-circle-exclamation me-2"></i>Set 0 for free</span>
                                    </div>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Fixed</option>
                                        <option>Percentage</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3 ">
                                    <label class="form-label">Discount Type<span class="text-danger"> *</span></label>
                                    <div class="pass-group">
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Fixed</option>
                                            <option>Percentage</option>
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
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Active</option>
                                            <option>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>								
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Plan -->
@endif

@if (Route::is(['invoices']))
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
                        <a href="{{url('invoice')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['add-invoices']))
    <!-- Invoice Preview -->
    <div class="modal fade" id="invoice_preview">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body p-4">
                    <div class="invoice-content">		
                        <!-- Invoices -->
                        <div class="d-flex justify-content-center align-items-center">
                            <div class="flex-fill">
                                <div class="row justify-content-between align-items-center border-bottom mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <img src="{{ URL::asset('build/img/image111.png') }}" class="img-fluid" alt="logo">
                                        </div>
                                        <p>3099 Kennedy Court Framingham, MA 01702</p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class=" text-end mb-3">
                                            <h5 class="text-gray mb-1">Invoice No <span class="text-primary">#INV0001</span></h5>
                                            <p class="mb-1 fw-medium">Created Date : <span class="text-dark">Sep 24, 2023</span> </p>
                                            <p class="fw-medium">Due Date : <span class="text-dark">Sep 30, 2023</span> </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="row border-bottom mb-3">
                                    <div class="col-md-5">
                                        <p class="text-dark mb-2 fw-semibold">From</p>
                                        <div>
                                            <h4 class="mb-1">Thomas Lawler</h4>
                                            <p class="mb-1">2077 Chicago Avenue Orosi, CA 93647</p>
                                            <p class="mb-1">Email : <span class="text-dark">Tarala2445@example.com</span></p>
                                            <p>Phone : <span class="text-dark">+1 987 654 3210</span></p>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <p class="text-dark mb-2 fw-semibold">To</p>
                                        <div>
                                            <h4 class="mb-1">Sara Inc,.</h4>
                                            <p class="mb-1">3103 Trainer Avenue Peoria, IL 61602</p>
                                            <p class="mb-1">Email : <span class="text-dark">Sara_inc34@example.com</span></p>
                                            <p>Phone : <span class="text-dark">+1 987 471 6589</span></p>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <p class="text-title mb-2 fw-medium">Payment Status </p>
                                            <span class="badge badge-danger align-items-center mb-3"><i class="ti ti-point-filled "></i>Due in 10 Days</span>
                                            <div>
                                                <img src="{{ URL::asset('build/img/qr.svg') }}" class="img-fluid" alt="QR">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <p class="fw-medium">Invoice For : <span class="text-dark fw-medium">Design & development of Website</span></p>
                                    <div class="table-responsive mb-3">
                                        <table class="table">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Job Description</th>
                                                    <th class="text-end">Qty</th>
                                                    <th class="text-end">Cost</th>
                                                    <th class="text-end">Discount</th>
                                                    <th class="text-end">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><h6>UX Strategy</h6></td>
                                                    <td class="text-gray-9 fw-medium text-end">1</td>
                                                    <td class="text-gray-9 fw-medium text-end">$500</td>
                                                    <td class="text-gray-9 fw-medium text-end">$100</td>
                                                    <td class="text-gray-9 fw-medium text-end">$500</td>
                                                </tr>
                                                <tr>
                                                    <td><h6>Design System</h6></td>
                                                    <td class="text-gray-9 fw-medium text-end">1</td>
                                                    <td class="text-gray-9 fw-medium text-end">$5000</td>
                                                    <td class="text-gray-9 fw-medium text-end">$100</td>
                                                    <td class="text-gray-9 fw-medium text-end">$5000</td>
                                                </tr>
                                                <tr>
                                                    <td><h6>Brand Guidellines</h6></td>
                                                    <td class="text-gray-9 fw-medium text-end">1</td>
                                                    <td class="text-gray-9 fw-medium text-end">$5000</td>
                                                    <td class="text-gray-9 fw-medium text-end">$100</td>
                                                    <td class="text-gray-9 fw-medium text-end">$5000</td>
                                                </tr>
                                                <tr>
                                                    <td><h6>Social Media Template</h6></td>
                                                    <td class="text-gray-9 fw-medium text-end">1</td>
                                                    <td class="text-gray-9 fw-medium text-end">$5000</td>
                                                    <td class="text-gray-9 fw-medium text-end">$100</td>
                                                    <td class="text-gray-9 fw-medium text-end">$5000</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="row border-bottom mb-3">
                                    <div class="col-md-7">
                                        <div class="py-4">
                                            <div class="mb-3">
                                                <h6 class="mb-1">Terms and Conditions</h6>
                                                <p>Please pay within 15 days from the date of invoice, overdue interest @ 14% will be charged on delayed payments.</p>
                                            </div>
                                            <div class="mb-3">
                                                <h6 class="mb-1">Notes</h6>
                                                <p>Please quote invoice number when remitting funds.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="d-flex justify-content-between align-items-center border-bottom mb-2 pe-3">
                                            <p class="mb-0">Sub Total</p>
                                            <p class="text-dark fw-medium mb-2">$5500</p>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center border-bottom mb-2 pe-3">
                                            <p class="mb-0">Discount(0%)</p>
                                            <p class="text-dark fw-medium mb-2">$400</p>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2 pe-3">
                                            <p class="mb-0">VAT(5%)</p>
                                            <p class="text-dark fw-medium mb-2">$54</p>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2 pe-3">
                                            <h5>Total Amount</h5>
                                            <h5>$5775</h5>
                                        </div>
                                        <p class="fs-12">
                                            Amount in Words : Dollar Five thousand Seven Seventy Five
                                        </p>
                                    </div>
                                </div>
                                <div class="row justify-content-end align-items-end text-end border-bottom mb-3">
                                    <div class="col-md-3">
                                        <div class="text-end">
                                            <img src="{{ URL::asset('build/img/sign.svg') }}" class="img-fluid" alt="sign">
                                        </div>
                                        <div class="text-end mb-3">
                                            <h6 class="fs-14 fw-medium pe-3">Ted M. Davis</h6>
                                            <p>Assistant Manager</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="mb-3">
                                        <img src="{{ URL::asset('build/img/image111.png') }}" class="img-fluid" alt="logo">
                                    </div>
                                    <p class="text-dark mb-1">Payment Made Via bank transfer / Cheque in the name of Thomas Lawler</p>
                                    <div class="d-flex justify-content-center align-items-center">
                                        <p class="fs-12 mb-0 me-3">Bank Name : <span class="text-dark">HDFC Bank</span></p>
                                        <p class="fs-12 mb-0 me-3">Account Number : <span class="text-dark">45366287987</span></p>
                                        <p class="fs-12">IFSC : <span class="text-dark">HDFC0018159</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /Invoices -->
                    </div>
                </div>
                    
            </div>
        </div>
    </div>
    <!-- /Invoice Preview -->

    <!-- Add Customer -->
    <div class="modal fade" id="add_customer">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Customer</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('add-invoices')}}">
                    <div class="modal-body">
                        <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                            <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded-circle border border-dashed me-2 flex-shrink-0 text-dark frames">
                                <i class="ti ti-photo-plus fs-16"></i>
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
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">User Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger"> *</span></label>
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
                                    <label class="form-label">Company <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-0">
                                    <label class="form-label">Address <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="d-flex align-items-center justify-content-end m-0">
                            <button class="btn btn-outline border me-2" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Customer -->
@endif

@if (Route::is(['edit-invoices']))
    <!-- Invoice Preview -->
    <div class="modal fade" id="invoice_preview">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body p-4">
                    <div class="invoice-content">		
                        <!-- Invoices -->
                        <div class="d-flex justify-content-center align-items-center">
                            <div class="flex-fill">
                                <div class="row justify-content-between align-items-center border-bottom mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <img src="{{ URL::asset('build/img/image111.png') }}" class="img-fluid" alt="logo">
                                        </div>
                                        <p>3099 Kennedy Court Framingham, MA 01702</p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class=" text-end mb-3">
                                            <h5 class="text-gray mb-1">Invoice No <span class="text-primary">#INV0001</span></h5>
                                            <p class="mb-1">Created Date : <span class="text-dark">Sep 24, 2023</span> </p>
                                            <p>Due Date : <span class="text-dark">Sep 30, 2023</span> </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="row border-bottom row-gap-3 mb-3">
                                    <div class="col-md-6">
                                        <p class="text-dark mb-2 fw-medium">From</p>
                                        <div>
                                            <h6 class="mb-1">Thomas Lawler</h6>
                                            <p class="mb-1">2077 Chicago Avenue Orosi, CA 93647</p>
                                            <p class="mb-1">Email : <span class="text-dark">Tarala2445@example.com</span></p>
                                            <p>Phone : <span class="text-dark">+1 987 654 3210</span></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <p class="text-dark mb-2 fw-medium">To</p>
                                            <div>
                                                <h6 class="mb-1">Sara Inc,.</h6>
                                                <p class="mb-1">3103 Trainer Avenue Peoria, IL 61602</p>
                                                <p class="mb-1">Email : <span class="text-dark">Sara_inc34@example.com</span></p>
                                                <p>Phone : <span class="text-dark">+1 987 471 6589</span></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="border-bottom mb-3">
                                        <p class="mb-3">Invoice For : <span class="text-dark fw-medium">Design & development of Website</span></p>
                                    </div>
                                    <div class="table-responsive mb-3">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Job Description</th>
                                                    <th class="text-end">Qty</th>
                                                    <th class="text-end">Cost</th>
                                                    <th class="text-end">Discount</th>
                                                    <th class="text-end">Total</th>
                                                </tr>													
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>UX Strategy</td>
                                                    <td class="text-end">1</td>
                                                    <td class="text-end">$500</td>
                                                    <td class="text-end">$100</td>
                                                    <td class="text-end">$500</td>
                                                </tr>
                                                <tr>
                                                    <td>Design System</td>
                                                    <td class="text-end">1</td>
                                                    <td class="text-end">$5000</td>
                                                    <td class="text-end">$100</td>
                                                    <td class="text-end">$5000</td>
                                                </tr>
                                                <tr>
                                                    <td>Brand Guidellines</td>
                                                    <td class="text-end">1</td>
                                                    <td class="text-end">$5000</td>
                                                    <td class="text-end">$100</td>
                                                    <td class="text-end">$5000</td>
                                                </tr>
                                                <tr>
                                                    <td>Social Media Template</td>
                                                    <td class="text-end">1</td>
                                                    <td class="text-end">$5000</td>
                                                    <td class="text-end">$100</td>
                                                    <td class="text-end">$5000</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="row border-bottom mb-3">
                                    <div class="col-md-12">
                                        <div class="d-flex justify-content-between align-items-center border-bottom mb-2 pe-3">
                                            <p class="mb-0">Sub Total</p>
                                            <p class="text-dark fw-medium mb-2">$5500</p>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center border-bottom mb-2 pe-3">
                                            <p class="mb-0">Discount(0%)</p>
                                            <p class="text-dark fw-medium mb-2">$400</p>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-3 pe-3">
                                            <p class="mb-0">VAT(5%)</p>
                                            <p class="text-dark fw-medium mb-2">$54</p>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-3 pe-3">
                                            <h5>Total Amount</h5>
                                            <h5>$5775</h5>
                                        </div>
                                        <p class="fs-12 mb-3">
                                            Amount in Words : Dollar Five thousand Seven Seventy Five
                                        </p>
                                    </div>
                                </div>
                                <div class="row justify-content-end align-items-end text-end">
                                    <div class="col-md-3">
                                        <div class="text-end">
                                            <img src="{{ URL::asset('build/img/sign.svg') }}" class="img-fluid" alt="sign">
                                        </div>
                                        <div class="text-end">
                                            <h6 class="fs-14 fw-medium mb-1">Ted M. Davis</h6>
                                            <p>Assistant Manager</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /Invoices -->
                    </div>
                </div>
                    
            </div>
        </div>
    </div>
    <!-- /Invoice Preview -->

    <!-- Add Customer -->
    <div class="modal fade" id="add_customer">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Customer</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('add-invoices')}}">
                    <div class="modal-body">
                        <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                            <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded-circle border border-dashed me-2 flex-shrink-0 text-dark frames">
                                <i class="ti ti-photo-plus fs-16"></i>
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
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">User Name <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger"> *</span></label>
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
                                    <label class="form-label">Company <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-0">
                                    <label class="form-label">Address <span class="text-danger"> *</span></label>
                                    <input type="text" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="d-flex align-items-center justify-content-end m-0">
                            <button class="btn btn-outline border me-2" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Customer -->
@endif

@if (Route::is(['subscription']))
    <!-- View Invoice -->
    <div class="modal fade" id="view_invoice">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body p-5">
                    <div class="row justify-content-between align-items-center mb-3">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <img src="{{ URL::asset('build/img/image111.png') }}" class="img-fluid" alt="logo">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class=" text-end mb-3">
                                <h5 class="text-dark mb-1">Invoice</h5>
                                <p class="mb-1 fw-normal"><i class="ti ti-file-invoice me-1"></i>INV0287</p>
                                <p class="mb-1 fw-normal"><i class="ti ti-calendar me-1"></i>Issue date : 12/09/2024 </p>
                                <p class="fw-normal"><i class="ti ti-calendar me-1"></i>Due date : 12/10/2024 </p>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3 d-flex justify-content-between">
                        <div class="col-md-7">
                            <p class="text-dark mb-2 fw-medium fs-16">Invoice From :</p>
                            <div>
                                <p class="mb-1">SmartHR</p>
                                <p class="mb-1">367 Hillcrest Lane, Irvine, California, 
                                    United States</p>
                                <p class="mb-1">smarthr@example.com</p>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <p class="text-dark mb-2 fw-medium fs-16">Invoice To :</p>
                            <div>
                                <p class="mb-1">BrightWave Innovations</p>
                                <p class="mb-1">367 Hillcrest Lane, Irvine, California, 
                                    United States</p>
                                <p class="mb-1">michael@example.com</p>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="table-responsive mb-3">
                            <table class="table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Plan</th>
                                        <th>Billing Cycle</th>
                                        <th>Created Date</th>
                                        <th>Expiring On</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Advanced (Monthly)</td>
                                        <td>30 Days</td>
                                        <td>12/09/2024</td>
                                        <td>12/10/2024</td>
                                        <td>$200</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="row mb-3 d-flex justify-content-between">
                        <div class="col-md-4">
                            <div>
                                <h6 class="mb-4">Payment info:</h6>
                                <p class="mb-0">Credit Card - 123***********789</p>
                                <div class="d-flex justify-content-between align-items-center mb-2 pe-3">
                                    <p class="mb-0">Amount</p>
                                    <p class="text-dark fw-medium mb-2">$200.00</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center pe-3">
                                <p class="text-dark fw-medium mb-0">Sub Total</p>
                                <p class="mb-2">$200.00</p>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pe-3">
                                <p class="text-dark fw-medium mb-0">Tax </p>
                                <p class="mb-2">$0.00</p>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pe-3">
                                <p class="text-dark fw-medium mb-0">Total</p>
                                <p class="text-dark fw-medium mb-2">$200.00</p>
                            </div>
                        </div>
                    </div>
                    <div class="card border mb-0">
                        <div class="card-body">
                            <p class="text-dark fw-medium mb-2">Terms & Conditions:</p>
                            <p class="fs-12 fw-normal d-flex align-items-baseline mb-2"><i class="ti ti-point-filled text-primary me-1"></i>All payments must be made according to the agreed schedule. Late payments may incur additional fees.</p>
                            <p class="fs-12 fw-normal d-flex align-items-baseline"><i class="ti ti-point-filled text-primary me-1"></i>We are not liable for any indirect, incidental, or consequential damages, including loss of profits, revenue, or data.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /View Invoice -->

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
                        <a href="{{url('subscription')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['packages']))
    <!-- Add Plan -->
    <div class="modal fade" id="add_plans">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Plan</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('packages')}}">
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
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Name<span class="text-danger"> *</span></label>
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
                                    <label class="form-label">Plan Type<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Monthly</option>
                                        <option>Yearly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Position<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>1</option>
                                        <option>2</option>
                                        <option>3</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Currency<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>USD</option>
                                        <option>EURO</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <label class="form-label">Plan Currency<span class="text-danger"> *</span></label>
                                        <span class="text-primary"><i class="fa-solid fa-circle-exclamation me-2"></i>Set 0 for free</span>
                                    </div>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Fixed</option>
                                        <option>Percentage</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3 ">
                                    <label class="form-label">Discount Type<span class="text-danger"> *</span></label>
                                    <div class="pass-group">
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Fixed</option>
                                            <option>Percentage</option>
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
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Active</option>
                                            <option>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>								
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Plan -->

    <!-- Edit Plan -->
    <div class="modal fade" id="edit_plans">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Plan</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('packages')}}">
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
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Name<span class="text-danger"> *</span></label>
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
                                    <label class="form-label">Plan Type<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Monthly</option>
                                        <option>Yearly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Position<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>1</option>
                                        <option>2</option>
                                        <option>3</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Currency<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>USD</option>
                                        <option>EURO</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <label class="form-label">Plan Currency<span class="text-danger"> *</span></label>
                                        <span class="text-primary"><i class="fa-solid fa-circle-exclamation me-2"></i>Set 0 for free</span>
                                    </div>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Fixed</option>
                                        <option>Percentage</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3 ">
                                    <label class="form-label">Discount Type<span class="text-danger"> *</span></label>
                                    <div class="pass-group">
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Fixed</option>
                                            <option>Percentage</option>
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
                                        <label class="form-check-label mt-0 me-2  text-dark">										
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
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Active</option>
                                            <option>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>								
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" ></textarea>
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
    <!-- /Edit Plan -->

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
                        <a href="{{url('packages')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['packages-grid']))
    <!-- Add Plan -->
    <div class="modal fade" id="add_plans">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Plan</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('packages-grid')}}">
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
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Name<span class="text-danger"> *</span></label>
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
                                    <label class="form-label">Plan Type<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Monthly</option>
                                        <option>Yearly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Position<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>1</option>
                                        <option>2</option>
                                        <option>3</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Currency<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>USD</option>
                                        <option>EURO</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <label class="form-label">Plan Currency <span class="text-danger"> *</span></label>
                                        <span class="text-primary"><i class="fa-solid fa-circle-exclamation me-2"></i>Set 0 for free</span>
                                    </div>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Fixed</option>
                                        <option>Percentage</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3 ">
                                    <label class="form-label">Discount Type<span class="text-danger"> *</span></label>
                                    <div class="pass-group">
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Fixed</option>
                                            <option>Percentage</option>
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
                                    <div class="form-check form-check-md d-flex align-items-center">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Employees
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Invoices
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">	
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Reports
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">	
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Contacts
                                        </label>
                                    </div>									
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Clients
                                        </label>
                                    </div>								
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Estimates
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Goals
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Deals
                                        </label>
                                    </div>									
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Projects
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Payments
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Assets
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Leads
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Tickets
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Taxes
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Activities
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
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
                                        <div class="form-check form-check-md form-switch me-2">
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
                                        <div class="form-check form-check-md form-switch me-2">
                                            <input class="form-check-input me-2" type="checkbox" role="switch">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="mb-3 ">
                                        <label class="form-label">Status<span class="text-danger"> *</span></label>
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Active</option>
                                            <option>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>								
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Plan -->

    <!-- Edit Plan -->
    <div class="modal fade" id="edit_plans">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Plan</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('packages-grid')}}">
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
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Name<span class="text-danger"> *</span></label>
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
                                    <label class="form-label">Plan Type<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Monthly</option>
                                        <option>Yearly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Position<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>1</option>
                                        <option>2</option>
                                        <option>3</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 ">
                                    <label class="form-label">Plan Currency<span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>USD</option>
                                        <option>EURO</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <label class="form-label">Plan Currency<span class="text-danger"> *</span></label>
                                        <span class="text-primary"><i class="fa-solid fa-circle-exclamation me-2"></i>Set 0 for free</span>
                                    </div>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Fixed</option>
                                        <option>Percentage</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3 ">
                                    <label class="form-label">Discount Type<span class="text-danger"> *</span></label>
                                    <div class="pass-group">
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Fixed</option>
                                            <option>Percentage</option>
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
                                    <div class="form-check form-check-md d-flex align-items-center">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Employees
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Invoices
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">	
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Reports
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">	
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Contacts
                                        </label>
                                    </div>									
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Clients
                                        </label>
                                    </div>								
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Estimates
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Goals
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Deals
                                        </label>
                                    </div>									
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Projects
                                        </label>
                                    </div>										
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Payments
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Assets
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Leads
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Tickets
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Taxes
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
                                        <label class="form-check-label mt-0 text-dark fw-medium">
                                            <input class="form-check-input" type="checkbox">
                                            Activities
                                        </label>
                                    </div>											
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <div class="form-check form-check-md d-flex align-items-center mb-3">
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
                                        <div class="form-check form-check-md form-switch me-2">
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
                                        <div class="form-check form-check-md form-switch me-2">
                                            <input class="form-check-input me-2" type="checkbox" role="switch">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="mb-3 ">
                                        <label class="form-label">Status<span class="text-danger"> *</span></label>
                                        <select class="select">
                                            <option>Select</option>
                                            <option>Active</option>
                                            <option>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>								
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" ></textarea>
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
    <!-- /Edit Plan -->
@endif

@if (Route::is(['purchase-transaction']))
    <!-- Invoices -->
    <div class="modal fade" id="view_invoice">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body p-5">
                    <div class="row justify-content-between align-items-center mb-3">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <img src="{{ URL::asset('build/img/image111.png') }}" class="img-fluid" alt="logo">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class=" text-end mb-3">
                                <h5 class="text-dark mb-1">Invoice</h5>
                                <p class="mb-1 fw-normal"><i class="ti ti-file-invoice me-1"></i>INV0287</p>
                                <p class="mb-1 fw-normal"><i class="ti ti-calendar me-1"></i>Issue date : 12/09/2024 </p>
                                <p class="fw-normal"><i class="ti ti-calendar me-1"></i>Due date : 12/10/2024 </p>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3 d-flex justify-content-between">
                        <div class="col-md-7">
                            <p class="text-dark mb-2 fw-medium fs-16">Invoice From :</p>
                            <div>
                                <p class="mb-1">SmartHR</p>
                                <p class="mb-1">367 Hillcrest Lane, Irvine, California, 
                                    United States</p>
                                <p class="mb-1">smarthr@example.com</p>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <p class="text-dark mb-2 fw-medium fs-16 text-end">Invoice To :</p>
                            <div>
                                <p class="mb-1 text-end">BrightWave Innovations</p>
                                <p class="mb-1 text-end">367 Hillcrest Lane, Irvine, California, 
                                    United States</p>
                                <p class="mb-1 text-end">michael@example.com</p>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="table-responsive mb-3">
                            <table class="table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Plan</th>
                                        <th class="text-end">Billing Cycle</th>
                                        <th class="text-end">Created Date</th>
                                        <th class="text-end">Expiring On</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Advanced (Monthly)</td>
                                        <td class="text-end">30 Days</td>
                                        <td class="text-end">12/09/2024</td>
                                        <td class="text-end">12/10/2024</td>
                                        <td class="text-end">$200</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="row mb-3 d-flex justify-content-between">
                        <div class="col-md-4">
                            <div>
                                <h6 class="mb-4">Payment info:</h6>
                                <p class="mb-0">Credit Card - 123***********789</p>
                                <div class="d-flex justify-content-between align-items-center mb-2 pe-3">
                                    <p class="mb-0">Amount</p>
                                    <p class="text-dark fw-medium mb-2">$200.00</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center pe-3">
                                <p class="text-dark fw-medium mb-0">Sub Total</p>
                                <p class="mb-2">$200.00</p>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pe-3">
                                <p class="text-dark fw-medium mb-0">Tax </p>
                                <p class="mb-2">$0.00</p>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pe-3">
                                <p class="text-dark fw-medium mb-0">Total</p>
                                <p class="text-dark fw-medium mb-2">$200.00</p>
                            </div>
                        </div>
                    </div>
                    <div class="card border mb-0">
                        <div class="card-body">
                            <p class="text-dark fw-medium mb-2">Terms & Conditions:</p>
                            <p class="fs-12 fw-normal d-flex align-items-baseline mb-2"><i class="ti ti-point-filled text-primary me-1"></i>All payments must be made according to the agreed schedule. Late payments may incur additional fees.</p>
                            <p class="fs-12 fw-normal d-flex align-items-baseline"><i class="ti ti-point-filled text-primary me-1"></i>We are not liable for any indirect, incidental, or consequential damages, including loss of profits, revenue, or data.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Invoices -->

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
                        <a href="{{url('purchase-transaction') }}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['estimates']))
    <!-- Add Estimate  -->
    <div class="modal fade" id="add_estimate">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Estimate</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('estimates')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Client <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Michael Walker</option>
                                        <option>Sophie Headrick</option>
                                        <option>Cameron Drake</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Project <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>Project Management</option>
                                        <option>Office Management</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger"> *</span></label>
                                    <input type="email" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tax <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option>VAT</option>
                                        <option>GST</option>
                                        <option>No Tax</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Client Address</label>
                                    <textarea class="form-control" rows="3"></textarea>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Biling Address</label>
                                    <textarea class="form-control" rows="3"></textarea>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Estimate Date</label>
                                    <div class="input-icon position-relative w-100 me-2">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <div class="input-icon position-relative w-100 me-2">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h4 class="mb-2">Add Items</h4>
                            <div class="border rounded p-3 mb-3">
                                <div class="add-estimate-info">									
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Item</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Unit Cost</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Qty</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Amount</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                </div>
                                <a href="javascript:void(0);" class="text-primary add-more-estimate fw-medium d-flex align-items-center mb-2"><i class="ti ti-plus me-2"></i>Add New Item</a>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Total </label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tax </label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Discount(%) </label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Grand Total </label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Other Information</label>
                                <textarea class="form-control" rows="3"></textarea>
                            </div>									
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Estimate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Estimate -->

    <!-- Add Estimate  -->
    <div class="modal fade" id="edit_estimate">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Estimate</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('estimates')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Client <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Michael Walker</option>
                                        <option>Sophie Headrick</option>
                                        <option>Cameron Drake</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Project <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>Project Management</option>
                                        <option>Office Management</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger"> *</span></label>
                                    <input type="email" class="form-control" value="michaelwalker@example.com">
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tax <span class="text-danger"> *</span></label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>VAT</option>
                                        <option>GST</option>
                                        <option>No Tax</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Client Address</label>
                                    <textarea class="form-control" rows="3"></textarea>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Biling Address</label>
                                    <textarea class="form-control" rows="3"></textarea>
                                </div>									
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Estimate Date</label>
                                    <div class="input-icon position-relative w-100 me-2">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <div class="input-icon position-relative w-100 me-2">
                                        <span class="input-icon-addon">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h4 class="mb-2">Add Items</h4>
                            <div class="border rounded p-3 mb-3">
                                <div class="add-estimate-info">									
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Item</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Unit Cost</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Qty</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Amount</label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                </div>
                                <a href="javascript:void(0);" class="text-primary add-more-estimate fw-medium d-flex align-items-center mb-2"><i class="ti ti-plus me-2"></i>Add New Item</a>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Total </label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tax </label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Discount(%) </label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Grand Total </label>
                                            <input type="text" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Other Information</label>
                                <textarea class="form-control" rows="3"></textarea>
                            </div>									
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Estimate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Add Estimate -->

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
                        <a href="{{url('estimates')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif

@if (Route::is(['invoice']))
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
                        <a href="{{url('invoice')}}" class="btn btn-danger">Yes, Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Delete Modal -->
@endif
