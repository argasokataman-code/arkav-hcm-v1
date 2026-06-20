@if (Route::is(['cronjob']))
    <!--Add Cronjob -->
    <div class="modal fade" id="add_note">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Add Note</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label">Note Title <span class="text-danger">*</span></label>
                                <input type="text" id="note-add-title" class="form-control" placeholder="Enter note title">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Tag</label>
                                <select id="note-add-tag" class="form-control">
                                    <option value="personal">Personal</option>
                                    <option value="social">Social</option>
                                    <option value="work">Work</option>
                                    <option value="others">Others</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Priority</label>
                                <select id="note-add-priority" class="form-control">
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-0">
                                <label class="form-label">Content</label>
                                <textarea id="note-add-content" class="form-control" rows="4" placeholder="Write your note here…"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="note-add-submit" class="btn btn-primary">Add Note</button>
                </div>
            </div>
        </div>
    </div>
                    <h4 class="modal-title">Edit Cronjob</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <form action="{{url('cronjob')}}">
                    <div class="modal-body pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" class="form-control" value="Report Generation Cron">
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Schedule</label>
                                    <select class="select">
                                        <option>Select</option>
                                        <option selected>5 Minutes</option>
                                        <option>3 Minutes</option>
                                    </select>
                                </div>									
                            </div>
                            <div class="col-md-12">
                                <div class="row ">
                                    <div class="col-md-2 d-flex align-items-center">
                                        <div class="mb-3">
                                            <label class="form-label">Next Run</label>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="mb-3">
                                            <div class="input-icon-end position-relative">
                                                <input type="text" class="form-control datetimepicker" placeholder="dd/mm/yyyy" value="09-09-2024">
                                                <span class="input-icon-addon">
                                                    <i class="ti ti-calendar text-gray-7"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>	
                                    <div class="col-md-5">
                                        <div class="mb-3">
                                            <div class="input-icon position-relative w-100">                                           
                                                <input type="text" class="form-control timepicker ps-3" placeholder="-- : -- : --" value="14:02 PM">
                                                <span class="input-icon-addon">
                                                    <i class="ti ti-clock-hour-3"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>	
                                </div>
                                                                
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">URL</label>
                                    <input type="text" class="form-control" value="www.example.com">
                                </div>									
                            </div>								
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Cronjob</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Edit Cronjob -->
@endif