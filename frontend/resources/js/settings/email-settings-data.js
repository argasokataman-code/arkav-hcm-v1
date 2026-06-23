(function () {
    'use strict';

    var root = document.querySelector('[data-email-settings-page]');
    if (!root) {
        return;
    }

    var feedback = document.querySelector('[data-email-settings-feedback]');
    var loading = document.querySelector('[data-email-settings-loading]');
    var refreshBtn = document.querySelector('[data-email-settings-refresh]');
    var refreshStatusBtn = document.querySelector('[data-email-settings-refresh-status]');
    var providerBadge = document.querySelector('[data-email-settings-summary-provider]');
    var senderSummary = document.querySelector('[data-email-settings-summary-sender]');
    var transportSummary = document.querySelector('[data-email-settings-summary-transport]');
    var credentialSummary = document.querySelector('[data-email-settings-summary-credential]');
    var updatedAtSummary = document.querySelector('[data-email-settings-summary-updated-at]');
    var mailtrapConnected = document.querySelector('[data-email-settings-mailtrap-connected]');
    var mailtrapSource = document.querySelector('[data-email-settings-mailtrap-source]');
    var mailtrapAccount = document.querySelector('[data-email-settings-mailtrap-account]');
    var mailtrapTokenCount = document.querySelector('[data-email-settings-mailtrap-token-count]');
    var mailtrapTokens = document.querySelector('[data-email-settings-mailtrap-tokens]');
    var mailtrapTokensEmpty = document.querySelector('[data-email-settings-mailtrap-tokens-empty]');
    var mailtrapError = document.querySelector('[data-email-settings-mailtrap-error]');
    var forms = Array.prototype.slice.call(document.querySelectorAll('[data-email-settings-form]'));

    var state = {
        profile: null,
        lastUpdatedAt: null,
        token: null,
    };

    function showToast(message, danger) {
        try {
            var Ui = window.ArcavUi;
            if (Ui && typeof Ui.showToast === 'function') {
                Ui.showToast(message, danger ? 'danger' : 'success');
                return;
            }
        } catch (_e) {}
        showFeedback(danger ? 'danger' : 'success', message);
    }

    function showFeedback(type, message) {
        if (!feedback) {
            return;
        }
        feedback.className = 'alert alert-' + type + ' mb-3';
        feedback.textContent = message;
        feedback.classList.remove('d-none');
    }

    function clearFeedback() {
        if (!feedback) {
            return;
        }
        feedback.textContent = '';
        feedback.classList.add('d-none');
    }

    function setLoading(isLoading, message) {
        if (!loading) {
            return;
        }
        loading.textContent = message || 'Memuat profile email runtime...';
        loading.classList.toggle('d-none', !isLoading);
    }

    function getStoredToken() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getToken === 'function') {
                var apiToken = window.AuthApi.getToken();
                if (apiToken) {
                    return apiToken;
                }
            }
        } catch (_e) {}

        return window.localStorage.getItem('arcav_access_token') ||
            window.sessionStorage.getItem('arcav_access_token') ||
            window.localStorage.getItem('token') ||
            window.sessionStorage.getItem('token') ||
            ((document.querySelector('meta[name="api-token"]') || {}).content) ||
            null;
    }

    function getTenantContext() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getTenantContext === 'function') {
                return window.AuthApi.getTenantContext() || {};
            }
        } catch (_e) {}

        try {
            return JSON.parse(window.localStorage.getItem('arcav_active_tenant') || '{}');
        } catch (_e) {
            return {};
        }
    }

    async function ensureToken() {
        if (state.token) {
            return state.token;
        }

        var token = getStoredToken();
        if (token) {
            state.token = token;
            return token;
        }

        var response = await fetch('/api-token', {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });
        var data = await response.json().catch(function () { return {}; });
        if (response.ok && data && data.success && data.data && data.data.token) {
            state.token = String(data.data.token);
            try {
                window.localStorage.setItem('arcav_access_token', state.token);
            } catch (_e) {}
            return state.token;
        }

        throw new Error('API token tidak ditemukan. Silakan login ulang lalu buka halaman ini lagi.');
    }

    async function buildHeaders(extra) {
        var headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        var token = await ensureToken();
        headers.Authorization = 'Bearer ' + token;

        var csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf && csrf.content) {
            headers['X-CSRF-TOKEN'] = csrf.content;
        }

        var tenant = getTenantContext();
        if (tenant.companyId) {
            headers['X-Company-Id'] = String(tenant.companyId);
        }
        if (tenant.companyCode) {
            headers['X-Company-Code'] = String(tenant.companyCode);
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

    async function requestJson(url, options) {
        var config = options || {};
        config.headers = await buildHeaders(config.headers || null);
        var response = await fetch(url, config);
        var data = await response.json().catch(function () {
            return { success: false, error: { message: 'Invalid JSON response.' } };
        });

        if (!response.ok || !data.success) {
            var error = new Error((data.error && data.error.message) || ('Request gagal (' + response.status + ')'));
            error.payload = data;
            error.status = response.status;
            throw error;
        }

        return data;
    }

    function formatDateTime(value) {
        if (!value) {
            return 'Belum ada update dari UI ini.';
        }

        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return date.toLocaleString();
    }

    function setText(node, value, emptyFallback) {
        if (!node) {
            return;
        }
        node.textContent = value || emptyFallback || '-';
    }

    function summarizeTransport(profile) {
        if (!profile) {
            return '-';
        }

        if (profile.provider === 'smtp') {
            var host = ((profile.smtp || {}).host) || '-';
            var port = ((profile.smtp || {}).port) || 587;
            var encryption = ((profile.smtp || {}).encryption) || 'none';
            return 'SMTP ' + host + ':' + port + ' (' + encryption + ')';
        }

        var accountId = ((profile.mailtrap || {}).accountId) || '-';
        return 'Mailtrap account ' + accountId;
    }

    function summarizeCredential(profile) {
        if (!profile) {
            return '-';
        }

        if (profile.provider === 'smtp') {
            return ((profile.smtp || {}).passwordMasked) || 'Belum ada password tersimpan.';
        }

        return ((profile.mailtrap || {}).apiTokenMasked) || 'Belum ada token tersimpan.';
    }

    function renderProfile(profile) {
        state.profile = profile;
        if (!profile) {
            return;
        }

        if (providerBadge) {
            providerBadge.textContent = String(profile.provider || 'unknown').toUpperCase();
        }

        var sender = [profile.fromName, profile.fromAddress].filter(Boolean).join(' • ');
        setText(senderSummary, sender, 'Belum ada sender identity tersimpan.');
        setText(transportSummary, summarizeTransport(profile), '-');
        setText(credentialSummary, summarizeCredential(profile), '-');
        setText(updatedAtSummary, formatDateTime(state.lastUpdatedAt), 'Belum ada update dari UI ini.');

        applyProfileToForms(profile);
    }

    function createTokenBadge(token) {
        var badge = document.createElement('span');
        badge.className = 'badge bg-light text-dark border';
        badge.textContent = token.name ? (token.name + ' • ****' + (token.last4 || '----')) : ('****' + (token.last4 || '----'));
        return badge;
    }

    function renderMailtrapStatus(status) {
        setText(mailtrapConnected, status.connected ? 'Connected' : 'Not connected', '-');
        if (mailtrapConnected) {
            mailtrapConnected.className = status.connected ? 'text-success fw-semibold' : 'text-danger fw-semibold';
        }
        setText(mailtrapSource, [status.credentialSource, status.mode].filter(Boolean).join(' / '), '-');
        setText(mailtrapAccount, status.accountId ? String(status.accountId) : '-', '-');
        setText(mailtrapTokenCount, String(status.visibleTokenCount || 0), '0');

        if (mailtrapError) {
            if (status.error && status.error.message) {
                mailtrapError.textContent = (status.error.code ? status.error.code + ': ' : '') + status.error.message;
                mailtrapError.classList.remove('d-none');
            } else {
                mailtrapError.classList.add('d-none');
                mailtrapError.textContent = '';
            }
        }

        if (mailtrapTokens) {
            mailtrapTokens.innerHTML = '';
            var visibleTokens = Array.isArray(status.visibleTokens) ? status.visibleTokens : [];
            visibleTokens.forEach(function (token) {
                mailtrapTokens.appendChild(createTokenBadge(token));
            });
            mailtrapTokens.classList.toggle('d-none', visibleTokens.length === 0);
            if (mailtrapTokensEmpty) {
                mailtrapTokensEmpty.classList.toggle('d-none', visibleTokens.length > 0);
            }
        }
    }

    function setFieldValue(provider, field, value) {
        var selector = '[data-email-settings-field="' + field + '"][data-provider="' + provider + '"]';
        var input = document.querySelector(selector);
        if (!input) {
            return;
        }
        if (value === null || value === undefined) {
            input.value = '';
            return;
        }
        input.value = String(value);
    }

    function setMaskText(maskKey, text) {
        var node = document.querySelector('[data-email-settings-mask="' + maskKey + '"]');
        if (node) {
            node.textContent = text || 'Belum ada secret tersimpan.';
        }
    }

    function clearTransientSecretFields() {
        Array.prototype.slice.call(document.querySelectorAll('[data-email-settings-field="smtp.password"], [data-email-settings-field="mailtrap.apiToken"]')).forEach(function (input) {
            input.value = '';
        });
    }

    function applyProfileToForms(profile) {
        setFieldValue('smtp', 'fromAddress', profile.fromAddress || '');
        setFieldValue('smtp', 'fromName', profile.fromName || '');
        setFieldValue('smtp', 'smtp.host', (profile.smtp || {}).host || '');
        setFieldValue('smtp', 'smtp.port', (profile.smtp || {}).port || 587);
        setFieldValue('smtp', 'smtp.encryption', (profile.smtp || {}).encryption || 'tls');
        setFieldValue('smtp', 'smtp.username', (profile.smtp || {}).username || '');

        setFieldValue('mailtrap', 'fromAddress', profile.fromAddress || '');
        setFieldValue('mailtrap', 'fromName', profile.fromName || '');
        setFieldValue('mailtrap', 'mailtrap.accountId', (profile.mailtrap || {}).accountId || '');

        setMaskText('smtp.password', (profile.smtp || {}).passwordMasked || 'Belum ada password tersimpan.');
        setMaskText('mailtrap.apiToken', (profile.mailtrap || {}).apiTokenMasked || 'Belum ada token tersimpan.');
        clearTransientSecretFields();
    }

    function setFormBusy(provider, busy, saveLabel, testLabel) {
        var submitBtn = document.querySelector('[data-email-settings-submit="' + provider + '"]');
        var testBtn = document.querySelector('[data-email-settings-test-button="' + provider + '"]');
        if (submitBtn) {
            submitBtn.disabled = busy;
            submitBtn.textContent = saveLabel || 'Save';
        }
        if (testBtn) {
            testBtn.disabled = busy;
            testBtn.textContent = testLabel || 'Test Connection';
        }
    }

    function renderModalFeedback(provider, type, message) {
        var node = document.querySelector('[data-email-settings-modal-feedback="' + provider + '"]');
        if (!node) {
            return;
        }
        node.className = 'alert alert-' + type;
        node.textContent = message;
        node.classList.remove('d-none');
    }

    function clearModalFeedback(provider) {
        var node = document.querySelector('[data-email-settings-modal-feedback="' + provider + '"]');
        if (!node) {
            return;
        }
        node.className = 'alert d-none';
        node.textContent = '';
    }

    function renderTestResult(provider, result) {
        var panel = document.querySelector('[data-email-settings-test-result="' + provider + '"]');
        if (!panel) {
            return;
        }

        var statusText = result.connected ? 'Connected' : 'Not connected';
        var lines = [
            'Status: ' + statusText,
            'Provider: ' + (result.provider || provider),
            'Mode: ' + (result.mode || 'unknown'),
            'Tested At: ' + (result.testedAt || '-')
        ];

        if (result.details && result.details.host) {
            lines.push('Host: ' + result.details.host + ':' + (result.details.port || '-'));
        }

        if (result.error && result.error.message) {
            lines.push('Error: ' + (result.error.code ? result.error.code + ' - ' : '') + result.error.message);
        }

        panel.textContent = lines.join('\n');
        panel.className = 'rounded border bg-light p-3';
        panel.classList.add(result.connected ? 'border-success' : 'border-danger');
        panel.classList.add(result.connected ? 'text-success' : 'text-danger');
    }

    function clearTestResult(provider) {
        var panel = document.querySelector('[data-email-settings-test-result="' + provider + '"]');
        if (!panel) {
            return;
        }
        panel.textContent = '';
        panel.className = 'rounded border bg-light p-3 d-none';
    }

    function readField(provider, field) {
        var input = document.querySelector('[data-email-settings-field="' + field + '"][data-provider="' + provider + '"]');
        return input ? String(input.value || '').trim() : '';
    }

    function buildSavePayload(provider) {
        if (provider === 'smtp') {
            var smtpPayload = {
                provider: 'smtp',
                fromAddress: readField('smtp', 'fromAddress'),
                fromName: readField('smtp', 'fromName'),
                smtp: {
                    host: readField('smtp', 'smtp.host'),
                    port: Number(readField('smtp', 'smtp.port') || 587),
                    encryption: readField('smtp', 'smtp.encryption') || 'tls',
                    username: readField('smtp', 'smtp.username')
                }
            };

            var smtpPassword = readField('smtp', 'smtp.password');
            if (smtpPassword !== '') {
                smtpPayload.smtp.password = smtpPassword;
            }

            return smtpPayload;
        }

        var mailtrapPayload = {
            provider: 'mailtrap',
            fromAddress: readField('mailtrap', 'fromAddress'),
            fromName: readField('mailtrap', 'fromName'),
            mailtrap: {
                accountId: Number(readField('mailtrap', 'mailtrap.accountId') || 0)
            }
        };

        var apiToken = readField('mailtrap', 'mailtrap.apiToken');
        if (apiToken !== '') {
            mailtrapPayload.mailtrap.apiToken = apiToken;
        }

        return mailtrapPayload;
    }

    function buildTestPayload(provider) {
        var payload = { provider: provider, timeout: 10 };
        if (provider === 'smtp') {
            payload.smtp = {
                host: readField('smtp', 'smtp.host'),
                port: Number(readField('smtp', 'smtp.port') || 587),
                encryption: readField('smtp', 'smtp.encryption') || 'tls',
                username: readField('smtp', 'smtp.username'),
                password: readField('smtp', 'smtp.password')
            };
            return payload;
        }

        payload.mailtrap = {
            accountId: Number(readField('mailtrap', 'mailtrap.accountId') || 0),
            apiToken: readField('mailtrap', 'mailtrap.apiToken')
        };
        return payload;
    }

    async function loadProfile() {
        var response = await requestJson('/v1/hcm/email-settings', { method: 'GET' });
        renderProfile(response.data || null);
    }

    async function loadMailtrapStatus() {
        var response = await requestJson('/v1/hcm/email-settings/mailtrap-status', { method: 'GET' });
        renderMailtrapStatus(response.data || {});
    }

    async function refreshAll() {
        clearFeedback();
        setLoading(true, 'Memuat profile email runtime...');
        try {
            await Promise.all([loadProfile(), loadMailtrapStatus()]);
        } catch (error) {
            showFeedback('danger', error.message || 'Gagal memuat email settings.');
        } finally {
            setLoading(false);
        }
    }

    function closeProviderModal(provider) {
        var modalSelector = provider === 'smtp' ? '#smtpsettings' : '#phpmailersettings';
        var modalEl = document.querySelector(modalSelector);
        if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }
        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    }

    async function handleSave(provider) {
        clearModalFeedback(provider);
        clearTestResult(provider);
        setFormBusy(provider, true, 'Saving...', 'Processing...');
        try {
            var response = await requestJson('/v1/hcm/email-settings', {
                method: 'PUT',
                body: JSON.stringify(buildSavePayload(provider))
            });

            state.lastUpdatedAt = response.meta && response.meta.updatedAt ? response.meta.updatedAt : new Date().toISOString();
            renderProfile(response.data || null);
            renderModalFeedback(provider, 'success', 'Profile email berhasil disimpan.');
            showToast('Profile email berhasil disimpan.', false);
            await loadMailtrapStatus();
            closeProviderModal(provider);
        } catch (error) {
            renderModalFeedback(provider, 'danger', error.message || 'Gagal menyimpan profile email.');
            showToast(error.message || 'Gagal menyimpan profile email.', true);
        } finally {
            setFormBusy(provider, false, 'Save', 'Test Connection');
        }
    }

    async function handleTest(provider) {
        clearModalFeedback(provider);
        clearTestResult(provider);
        setFormBusy(provider, true, 'Processing...', 'Testing...');
        try {
            var response = await requestJson('/v1/hcm/email-settings/test-connection', {
                method: 'POST',
                body: JSON.stringify(buildTestPayload(provider))
            });
            renderTestResult(provider, response.data || {});
            renderModalFeedback(provider, (response.data && response.data.connected) ? 'success' : 'warning', 'Test connection selesai. Lihat detail hasil probe di bawah.');
        } catch (error) {
            renderModalFeedback(provider, 'danger', error.message || 'Test connection gagal.');
            showToast(error.message || 'Test connection gagal.', true);
        } finally {
            setFormBusy(provider, false, 'Save', 'Test Connection');
        }
    }

    function bindForms() {
        forms.forEach(function (form) {
            var provider = form.getAttribute('data-email-settings-form');
            if (!provider) {
                return;
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!ArcavValidation.validateForm(form)) { return; }
                handleSave(provider);
            });

            var testBtn = document.querySelector('[data-email-settings-test-button="' + provider + '"]');
            if (testBtn) {
                testBtn.addEventListener('click', function () {
                    handleTest(provider);
                });
            }

            var modalSelector = provider === 'smtp' ? '#smtpsettings' : '#phpmailersettings';
            var modalEl = document.querySelector(modalSelector);
            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', function () {
                    clearModalFeedback(provider);
                    clearTestResult(provider);
                    if (state.profile) {
                        applyProfileToForms(state.profile);
                    }
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindForms();

        if (refreshBtn) {
            refreshBtn.addEventListener('click', refreshAll);
        }

        if (refreshStatusBtn) {
            refreshStatusBtn.addEventListener('click', function () {
                loadMailtrapStatus().catch(function (error) {
                    showFeedback('danger', error.message || 'Gagal mengecek status Mailtrap.');
                });
            });
        }

        refreshAll();
    });
})();