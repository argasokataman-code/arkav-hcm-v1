(function () {
    'use strict';

    var shell = document.querySelector('[data-email-settings-shell]');
    if (!shell) {
        return;
    }

    var globalFeedback = document.querySelector('[data-email-settings-feedback]');
    var mailtrapStatusText = document.querySelector('[data-mailtrap-status-text]');
    var mailtrapStatusBadge = document.querySelector('[data-mailtrap-status-badge]');
    var mailtrapStatusRefresh = document.querySelector('[data-mailtrap-status-refresh]');
    var providerStatusNodes = document.querySelectorAll('[data-provider-status]');
    var providerSwitches = document.querySelectorAll('[data-provider-switch]');
    var modalForms = document.querySelectorAll('[data-email-settings-form]');
    var testButtons = document.querySelectorAll('[data-email-settings-test-button]');
    var saveButton = document.querySelector('[data-email-settings-save]');
    var toastNode = document.querySelector('[data-email-settings-toast]');
    var toastMessageNode = document.querySelector('[data-email-settings-toast-message]');

    var profileState = null;
    var selectedProvider = 'mailtrap';
    var dirtyProviders = {};

    function normalize(value) {
        return (value || '').toString().trim();
    }

    function getApiToken() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getToken === 'function') {
                return window.AuthApi.getToken() || null;
            }
        } catch (_e) {}

        return window.localStorage.getItem('arcav_access_token') ||
            window.sessionStorage.getItem('arcav_access_token') ||
            window.localStorage.getItem('token') ||
            window.sessionStorage.getItem('token') ||
            (document.querySelector('meta[name="api-token"]') || {}).content ||
            (document.querySelector('meta[name="auth-token"]') || {}).content ||
            null;
    }

    function getTenantContext() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getTenantContext === 'function') {
                return window.AuthApi.getTenantContext() || {};
            }
        } catch (_e) {}

        return {};
    }

    function buildHeaders(extra) {
        var headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        var csrfNode = document.querySelector('meta[name="csrf-token"]');
        if (csrfNode && csrfNode.content) {
            headers['X-CSRF-TOKEN'] = csrfNode.content;
        }

        var token = getApiToken();
        if (token) {
            headers.Authorization = 'Bearer ' + String(token);
        }

        var tenant = getTenantContext();
        if (tenant.companyCode) {
            headers['X-Company-Code'] = String(tenant.companyCode);
        }
        if (tenant.companyId) {
            headers['X-Company-Id'] = String(tenant.companyId);
        }
        if (tenant.companyUuid) {
            headers['X-Company-UUID'] = String(tenant.companyUuid);
        }

        if (extra) {
            Object.keys(extra).forEach(function (key) {
                headers[key] = extra[key];
            });
        }

        return headers;
    }

    function showGlobalFeedback(type, message) {
        if (!globalFeedback) {
            return;
        }

        globalFeedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        globalFeedback.classList.add('alert-' + type);
        globalFeedback.textContent = message;
        showToast(type, message);
    }

    function showToast(type, message) {
        if (!toastNode || !toastMessageNode || !message) {
            return;
        }

        toastNode.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-warning', 'text-bg-info');
        if (type === 'success') {
            toastNode.classList.add('text-bg-success');
        } else if (type === 'danger') {
            toastNode.classList.add('text-bg-danger');
        } else if (type === 'warning') {
            toastNode.classList.add('text-bg-warning');
        } else {
            toastNode.classList.add('text-bg-info');
        }

        toastMessageNode.textContent = message;

        if (window.bootstrap && typeof window.bootstrap.Toast === 'function') {
            var instance = window.bootstrap.Toast.getOrCreateInstance(toastNode, { delay: 4000 });
            instance.show();
            return;
        }

        toastNode.classList.add('show');
        window.setTimeout(function () {
            toastNode.classList.remove('show');
        }, 3000);
    }

    function clearGlobalFeedback() {
        if (!globalFeedback) {
            return;
        }

        globalFeedback.classList.add('d-none');
        globalFeedback.classList.remove('alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        globalFeedback.textContent = '';
    }

    function setProviderStatus(provider, connected, labelClass, labelText) {
        Array.prototype.forEach.call(providerStatusNodes, function (node) {
            if (node.getAttribute('data-provider-status') !== provider) {
                return;
            }

            node.className = 'btn btn-sm d-inline-flex align-items-center ' + labelClass;
            node.innerHTML = '<i class="ti ti-checks me-1"></i>' + labelText;
        });
    }

    function applyMailtrapStatus(connected, text) {
        if (!mailtrapStatusBadge || !mailtrapStatusText) {
            return;
        }

        mailtrapStatusBadge.classList.remove('badge-success', 'badge-danger', 'badge-warning', 'badge-secondary');
        if (connected === true) {
            mailtrapStatusBadge.classList.add('badge-success');
            mailtrapStatusBadge.textContent = 'Connected';
            setProviderStatus('mailtrap', true, 'btn-dark', 'Connected');
        } else if (connected === false) {
            mailtrapStatusBadge.classList.add('badge-warning');
            mailtrapStatusBadge.textContent = 'Not Connected';
            setProviderStatus('mailtrap', false, 'btn-light', 'Not Connected');
        } else {
            mailtrapStatusBadge.classList.add('badge-secondary');
            mailtrapStatusBadge.textContent = 'Unknown';
            setProviderStatus('mailtrap', false, 'btn-light', 'Unknown');
        }

        mailtrapStatusText.textContent = text || 'No details.';
    }

    function showModalFeedback(provider, type, message) {
        var feedback = document.querySelector('[data-email-settings-modal-feedback="' + provider + '"]');
        if (!feedback) {
            return;
        }

        feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        feedback.classList.add('alert-' + type);
        feedback.textContent = message;
    }

    function clearModalFeedback(provider) {
        var feedback = document.querySelector('[data-email-settings-modal-feedback="' + provider + '"]');
        if (!feedback) {
            return;
        }

        feedback.classList.add('d-none');
        feedback.classList.remove('alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        feedback.textContent = '';
    }

    function setTestResult(provider, type, message) {
        var node = document.querySelector('[data-email-settings-test-result="' + provider + '"]');
        if (!node) {
            return;
        }

        node.classList.remove('d-none', 'border-success', 'border-danger', 'border-warning', 'text-success', 'text-danger', 'text-warning');
        node.classList.add('border-' + type, 'text-' + type);
        node.textContent = message;
    }

    function setButtonLoading(button, isLoading, loadingText, idleText) {
        if (!button) {
            return;
        }

        button.disabled = isLoading;
        button.textContent = isLoading ? loadingText : idleText;
    }

    function setShellSavingState(isSaving) {
        if (!saveButton) {
            return;
        }

        saveButton.disabled = isSaving;
        saveButton.textContent = isSaving ? 'Saving...' : 'Save';
    }

    function hasDirtyProviders() {
        return Object.keys(dirtyProviders).some(function (provider) {
            return dirtyProviders[provider] === true;
        });
    }

    function setDirty(provider, isDirty) {
        dirtyProviders[provider] = isDirty;
        if (saveButton) {
            saveButton.disabled = !hasDirtyProviders();
        }
    }

    function applyProviderSelection(provider) {
        selectedProvider = provider === 'smtp' ? 'smtp' : 'mailtrap';

        Array.prototype.forEach.call(providerSwitches, function (node) {
            node.checked = node.getAttribute('data-provider-switch') === selectedProvider;
        });
    }

    function assignFieldValue(provider, field, value) {
        var selector = '[data-email-settings-field="' + field + '"][data-provider="' + provider + '"]';
        var node = document.querySelector(selector);
        if (!node) {
            return;
        }

        node.value = value == null ? '' : String(value);
    }

    function assignMask(field, value) {
        var node = document.querySelector('[data-email-settings-mask="' + field + '"]');
        if (!node) {
            return;
        }

        node.textContent = value ? ('Credential tersimpan: ' + value) : 'Belum ada credential tersimpan.';
    }

    function applyProfile(profile) {
        profileState = profile || null;
        selectedProvider = profile && profile.provider ? profile.provider : 'mailtrap';

        assignFieldValue('smtp', 'fromAddress', profile && profile.fromAddress);
        assignFieldValue('smtp', 'fromName', profile && profile.fromName);
        assignFieldValue('smtp', 'smtp.host', profile && profile.smtp ? profile.smtp.host : '');
        assignFieldValue('smtp', 'smtp.port', profile && profile.smtp ? profile.smtp.port : '587');
        assignFieldValue('smtp', 'smtp.encryption', profile && profile.smtp && profile.smtp.encryption ? profile.smtp.encryption : 'tls');
        assignFieldValue('smtp', 'smtp.username', profile && profile.smtp ? profile.smtp.username : '');
        assignMask('smtp.password', profile && profile.smtp ? profile.smtp.passwordMasked : null);
        setProviderStatus('smtp', Boolean(profile && profile.smtp && profile.smtp.configured), profile && profile.smtp && profile.smtp.configured ? 'btn-dark' : 'btn-light', profile && profile.smtp && profile.smtp.configured ? 'Configured' : 'Not Configured');

        assignFieldValue('mailtrap', 'fromAddress', profile && profile.fromAddress);
        assignFieldValue('mailtrap', 'fromName', profile && profile.fromName);
        assignFieldValue('mailtrap', 'mailtrap.accountId', profile && profile.mailtrap ? profile.mailtrap.accountId : '');
        assignMask('mailtrap.apiToken', profile && profile.mailtrap ? profile.mailtrap.apiTokenMasked : null);

        applyProviderSelection(selectedProvider);
        setDirty('smtp', false);
        setDirty('mailtrap', false);
    }

    function collectPayload(provider, includeSecrets) {
        var payload = {
            provider: provider,
            fromAddress: normalize((document.querySelector('[data-email-settings-field="fromAddress"][data-provider="' + provider + '"]') || {}).value),
            fromName: normalize((document.querySelector('[data-email-settings-field="fromName"][data-provider="' + provider + '"]') || {}).value)
        };

        if (provider === 'smtp') {
            payload.smtp = {
                host: normalize((document.querySelector('[data-email-settings-field="smtp.host"][data-provider="smtp"]') || {}).value),
                port: Number((document.querySelector('[data-email-settings-field="smtp.port"][data-provider="smtp"]') || {}).value || 587),
                encryption: normalize((document.querySelector('[data-email-settings-field="smtp.encryption"][data-provider="smtp"]') || {}).value) || 'tls',
                username: normalize((document.querySelector('[data-email-settings-field="smtp.username"][data-provider="smtp"]') || {}).value)
            };
            if (includeSecrets || normalize((document.querySelector('[data-email-settings-field="smtp.password"][data-provider="smtp"]') || {}).value)) {
                payload.smtp.password = normalize((document.querySelector('[data-email-settings-field="smtp.password"][data-provider="smtp"]') || {}).value);
            }
        }

        if (provider === 'mailtrap') {
            payload.mailtrap = {
                accountId: Number((document.querySelector('[data-email-settings-field="mailtrap.accountId"][data-provider="mailtrap"]') || {}).value || 0)
            };
            if (includeSecrets || normalize((document.querySelector('[data-email-settings-field="mailtrap.apiToken"][data-provider="mailtrap"]') || {}).value)) {
                payload.mailtrap.apiToken = normalize((document.querySelector('[data-email-settings-field="mailtrap.apiToken"][data-provider="mailtrap"]') || {}).value);
            }
        }

        return payload;
    }

    function applySavedProfile(result) {
        if (!result || !result.data) {
            return;
        }

        applyProfile(result.data);
        setDirty('smtp', false);
        setDirty('mailtrap', false);
        clearSensitiveField('smtp', 'smtp.password');
        clearSensitiveField('mailtrap', 'mailtrap.apiToken');
    }

    function clearSensitiveField(provider, field) {
        var node = document.querySelector('[data-email-settings-field="' + field + '"][data-provider="' + provider + '"]');
        if (node) {
            node.value = '';
        }
    }

    async function saveProviderSettings(provider, submitButton) {
        clearModalFeedback(provider);
        clearGlobalFeedback();
        setButtonLoading(submitButton, true, 'Saving...', 'Save');
        setShellSavingState(true);

        var payload = collectPayload(provider, false);
        var result = await requestJson('/v1/hcm/email-settings', {
            method: 'PUT',
            headers: buildHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });

        setButtonLoading(submitButton, false, 'Saving...', 'Save');
        setShellSavingState(false);

        if (!result.ok || !result.payload || result.payload.success !== true) {
            var errorMessage = parseError(result.payload, 'Gagal menyimpan email settings.');
            showModalFeedback(provider, 'danger', errorMessage);
            showGlobalFeedback('danger', errorMessage);
            return;
        }

        applySavedProfile(result.payload);
        applyProviderSelection(provider);
        showModalFeedback(provider, 'success', 'Email settings berhasil disimpan.');
        showGlobalFeedback('success', 'Email settings berhasil disimpan untuk provider ' + provider + '.');

        if (provider === 'mailtrap') {
            loadMailtrapStatus();
        }
    }

    function parseError(payload, fallback) {
        if (!payload) {
            return fallback;
        }

        if (payload.error && payload.error.message) {
            return String(payload.error.message);
        }

        if (payload.message) {
            return String(payload.message);
        }

        return fallback;
    }

    async function requestJson(url, options) {
        var response = await fetch(url, options);
        var payload = await response.json().catch(function () { return null; });

        return {
            ok: response.ok,
            status: response.status,
            payload: payload
        };
    }

    async function loadMailtrapStatus() {
        applyMailtrapStatus(null, 'Checking configuration...');

        var result = await requestJson('/v1/hcm/email-settings/mailtrap-status', {
            method: 'GET',
            headers: buildHeaders(),
            credentials: 'same-origin'
        });

        if (!result.ok || !result.payload || result.payload.success !== true) {
            applyMailtrapStatus(false, parseError(result.payload, 'Request failed while checking Mailtrap status.'));
            return;
        }

        var data = result.payload.data || {};
        var sourceLabel = data.credentialSource === 'settings' ? 'saved settings' : 'environment';
        if (!data.tokenConfigured || !data.accountId) {
            applyMailtrapStatus(false, 'MAILTRAP_API_TOKEN / MAILTRAP_ACCOUNT_ID belum lengkap pada ' + sourceLabel + '.');
            return;
        }

        if (data.connected) {
            applyMailtrapStatus(true, 'Account #' + data.accountId + ' connected. Visible tokens: ' + (data.visibleTokenCount || 0) + '.');
            return;
        }

        applyMailtrapStatus(false, data.error && data.error.message ? data.error.message : (data.error || 'Unable to connect to Mailtrap API.'));
    }

    async function loadProfile() {
        showGlobalFeedback('info', 'Loading current email settings...');

        var result = await requestJson('/v1/hcm/email-settings', {
            method: 'GET',
            headers: buildHeaders(),
            credentials: 'same-origin'
        });

        if (!result.ok || !result.payload || result.payload.success !== true) {
            showGlobalFeedback('warning', parseError(result.payload, 'Gagal memuat profile email settings.'));
            return;
        }

        applyProfile(result.payload.data || {});
        if (result.payload.data && result.payload.data.provider) {
            showGlobalFeedback('success', 'Current email settings loaded. Active provider: ' + result.payload.data.provider + '.');
        } else {
            showGlobalFeedback('warning', 'Belum ada provider profile tersimpan. Isi salah satu modal lalu simpan.');
        }
    }

    async function handleTestConnection(provider, button) {
        clearModalFeedback(provider);
        setTestResult(provider, 'warning', 'Testing connection...');
        setButtonLoading(button, true, 'Testing...', 'Test Connection');

        var payload = collectPayload(provider, true);
        var result = await requestJson('/v1/hcm/email-settings/test-connection', {
            method: 'POST',
            headers: buildHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });

        setButtonLoading(button, false, 'Testing...', 'Test Connection');

        if (!result.ok || !result.payload || result.payload.success !== true) {
            var errorMessage = parseError(result.payload, 'Test connection gagal dijalankan.');
            showModalFeedback(provider, 'danger', errorMessage);
            setTestResult(provider, 'danger', errorMessage);
            return;
        }

        var data = result.payload.data || {};
        if (data.connected) {
            showModalFeedback(provider, 'success', 'Test connection berhasil.');
            setTestResult(provider, 'success', 'Connection successful.');
        } else {
            var failedMessage = data.error && data.error.message ? data.error.message : 'Connection failed.';
            showModalFeedback(provider, 'warning', failedMessage);
            setTestResult(provider, 'warning', failedMessage);
        }
    }

    Array.prototype.forEach.call(testButtons, function (button) {
        button.addEventListener('click', function () {
            var provider = button.getAttribute('data-email-settings-test-button');
            handleTestConnection(provider, button);
        });
    });

    Array.prototype.forEach.call(providerSwitches, function (node) {
        node.addEventListener('change', function () {
            applyProviderSelection(node.getAttribute('data-provider-switch'));
            setDirty(node.getAttribute('data-provider-switch'), true);
            showGlobalFeedback('info', 'Provider aktif akan disimpan saat kamu klik Save di modal provider terkait.');
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-email-settings-field]'), function (node) {
        node.addEventListener('input', function () {
            var provider = node.getAttribute('data-provider');
            setDirty(provider, true);
        });

        node.addEventListener('change', function () {
            var provider = node.getAttribute('data-provider');
            setDirty(provider, true);
        });
    });

    Array.prototype.forEach.call(modalForms, function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var provider = form.getAttribute('data-email-settings-form');
            var submitButton = form.querySelector('[data-email-settings-submit="' + provider + '"]');
            saveProviderSettings(provider, submitButton);
        });
    });

    window.addEventListener('beforeunload', function (event) {
        if (!hasDirtyProviders()) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });

    if (mailtrapStatusRefresh) {
        mailtrapStatusRefresh.addEventListener('click', function () {
            loadMailtrapStatus();
        });
    }

    loadMailtrapStatus();
    loadProfile();
})();