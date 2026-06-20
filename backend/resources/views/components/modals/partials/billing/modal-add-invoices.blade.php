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