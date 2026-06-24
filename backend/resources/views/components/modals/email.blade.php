<div class="modal fade" id="smtpsettings" tabindex="-1" role="dialog" data-email-settings-modal>
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">SMTP Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form data-email-settings-form="smtp" novalidate>
                <div class="modal-body">
                    <div class="alert d-none" data-email-settings-modal-feedback="smtp"></div>

                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Sender Identity</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="smtp-from-address">From Address</label>
                            <input id="smtp-from-address" type="email" class="form-control" data-email-settings-field="fromAddress" data-provider="smtp" placeholder="noreply@example.com" required>
                            <div class="invalid-feedback">From address wajib diisi.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="smtp-from-name">From Name</label>
                            <input id="smtp-from-name" type="text" class="form-control" data-email-settings-field="fromName" data-provider="smtp" placeholder="ARCAV HCM" required>
                            <div class="invalid-feedback">From name wajib diisi.</div>
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase fw-semibold mb-2">SMTP Server</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="smtp-host">Host</label>
                            <input id="smtp-host" type="text" class="form-control" data-email-settings-field="smtp.host" data-provider="smtp" placeholder="smtp.example.com" required>
                            <div class="invalid-feedback">Host wajib diisi.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="smtp-port">Port</label>
                            <input id="smtp-port" type="number" class="form-control" data-email-settings-field="smtp.port" data-provider="smtp" value="587" required>
                            <div class="invalid-feedback">Port wajib diisi.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="smtp-encryption">Encryption</label>
                            <select id="smtp-encryption" class="form-select" data-email-settings-field="smtp.encryption" data-provider="smtp" required>
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="">None</option>
                            </select>
                            <div class="invalid-feedback">Encryption wajib dipilih.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="smtp-username">Username</label>
                            <input id="smtp-username" type="text" class="form-control" data-email-settings-field="smtp.username" data-provider="smtp" placeholder="username">
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label" for="smtp-password">Password</label>
                            <div class="input-group">
                                <input id="smtp-password" type="password" class="form-control" data-email-settings-field="smtp.password" data-provider="smtp" placeholder="Leave empty to keep current">
                                <button type="button" class="btn btn-outline-secondary" onclick="const i=this.previousElementSibling;i.type=i.type==='password'?'text':'password'"><i class="ti ti-eye"></i></button>
                            </div>
                            <div class="small text-muted mt-1" data-email-settings-mask="smtp.password">Belum ada password tersimpan.</div>
                        </div>
                    </div>

                    <div class="rounded border bg-light p-3 mt-3 d-none" data-email-settings-test-result="smtp"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary" data-email-settings-test-button="smtp">Test Connection</button>
                    <button type="submit" class="btn btn-primary" data-email-settings-submit="smtp">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="phpmailersettings" tabindex="-1" role="dialog" data-email-settings-modal>
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mailtrap Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form data-email-settings-form="mailtrap" novalidate>
                <div class="modal-body">
                    <div class="alert d-none" data-email-settings-modal-feedback="mailtrap"></div>

                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Sender Identity</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="mailtrap-from-address">From Address</label>
                            <input id="mailtrap-from-address" type="email" class="form-control" data-email-settings-field="fromAddress" data-provider="mailtrap" placeholder="noreply@example.com" required>
                            <div class="invalid-feedback">From address wajib diisi.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mailtrap-from-name">From Name</label>
                            <input id="mailtrap-from-name" type="text" class="form-control" data-email-settings-field="fromName" data-provider="mailtrap" placeholder="ARCAV HCM" required>
                            <div class="invalid-feedback">From name wajib diisi.</div>
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase fw-semibold mb-2">Mailtrap Account</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="mailtrap-account-id">Account ID</label>
                            <input id="mailtrap-account-id" type="number" class="form-control" data-email-settings-field="mailtrap.accountId" data-provider="mailtrap" placeholder="123456" required>
                            <div class="invalid-feedback">Account ID wajib diisi.</div>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-8">
                            <label class="form-label" for="mailtrap-api-token">API Token</label>
                            <div class="input-group">
                                <input id="mailtrap-api-token" type="password" class="form-control" data-email-settings-field="mailtrap.apiToken" data-provider="mailtrap" placeholder="Leave empty to keep current">
                                <button type="button" class="btn btn-outline-secondary" onclick="const i=this.previousElementSibling;i.type=i.type==='password'?'text':'password'"><i class="ti ti-eye"></i></button>
                            </div>
                            <div class="small text-muted mt-1" data-email-settings-mask="mailtrap.apiToken">Belum ada token tersimpan.</div>
                        </div>
                    </div>

                    <div class="rounded border bg-light p-3 mt-3 d-none" data-email-settings-test-result="mailtrap"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary" data-email-settings-test-button="mailtrap">Test Connection</button>
                    <button type="submit" class="btn btn-primary" data-email-settings-submit="mailtrap">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
