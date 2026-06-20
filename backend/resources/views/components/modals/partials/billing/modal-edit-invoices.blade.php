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