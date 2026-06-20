@if (Route::is(['storage-settings']))
    <!--Add Cronjob -->
    <div class="modal fade" id="aws_settings">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">AWS</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('storage-settings')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">AWS Access Key</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">AWS Secret Key</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>								
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Bucket Name</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>								
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Region</label>
                                    <input type="text" class="form-control">
                                </div>									
                            </div>								
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">AWS Base URL</label>
                                    <input type="text" class="form-control">
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
    <!-- /Add Cronjob -->
@endif