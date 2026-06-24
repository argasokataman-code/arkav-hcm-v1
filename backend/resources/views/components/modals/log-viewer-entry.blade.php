<div class="modal fade" id="logEntryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Entry Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0 g-2">
                    <dt class="col-sm-2 text-muted">Timestamp</dt>
                    <dd class="col-sm-10 mb-0" data-log-entry-timestamp></dd>
                    <dt class="col-sm-2 text-muted">Level</dt>
                    <dd class="col-sm-10 mb-0" data-log-entry-level></dd>
                    <dt class="col-sm-2 text-muted">Env</dt>
                    <dd class="col-sm-10 mb-0" data-log-entry-env></dd>
                    <dt class="col-sm-2 text-muted">Message</dt>
                    <dd class="col-sm-10 mb-0"><pre class="mb-0 bg-light p-2 rounded" style="white-space:pre-wrap;word-break:break-all;" data-log-entry-message></pre></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
