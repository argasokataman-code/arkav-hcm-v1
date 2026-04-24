(function () {
    'use strict';

    var form = document.querySelector('[data-email-settings-form]');
    if (!form) {
        return;
    }

    // ── DOM refs ──────────────────────────────────────────────────────────────
    var feedback          = document.querySelector('[data-email-settings-feedback]');
    var statusNode        = document.querySelector('[data-email-settings-status]');
    var submitBtn         = document.querySelector('[data-email-settings-submit]');
    var cancelBtn         = document.querySelector('[data-email-settings-cancel]');
    var testConnBtn       = document.querySelector('[data-email-settings-test-conn]');
    var testConnResult    = document.querySelector('[data-email-settings-test-result]');
    var providerSelect    = document.querySelector('[data-email-settings-provider]');
    var smtpSection       = document.querySelector('[data-email-settings-section="smtp"]');
    var mailtrapSection   = document.querySelector('[data-email-settings-section="mailtrap"]');
    var emptyState        = document.querySelector('[data-email-settings-empty]');
    var loadedState       = document.querySelector('[data-email-settings-loaded]');

    // ── Helpers ───────────────────────────────────────────────────────────────
    function getField(name) {
        return form.querySelector('[data-field="' + name + '"]');
    }

    function fieldVal(name) {
        var el = getField(name);
        return el ? el.value.trim() : '';
    }

    async function getApiToken() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getToken === 'function') {
                var t = window.AuthApi.getToken();
                if (t) { return t; }
            }
        } catch (_e) {}

        try {
            var res = await fetch('/api-token', { credentials: 'include' });
            var payload = await res.json();
            var tok = (payload && payload.data && payload.data.token)
                ? payload.data.token
                : (payload && payload.token ? payload.token : null);
            if (tok) { return tok; }
        } catch (_e) {}

        return null;
    }

    function buildHeaders(token, withJson) {
        var h = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (token) { h.Authorization = 'Bearer ' + token; }
        if (withJson) { h['Content-Type'] = 'application/json'; }
        return h;
    }

    function showFeedback(type, msg) {
        if (!feedback) { return; }
        feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        feedback.classList.add('alert-' + type);
        feedback.textContent = msg;
    }

    function clearFeedback() {
        if (!feedback) { return; }
        feedback.classList.add('d-none');
        feedback.classList.remove('alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        feedback.textContent = '';
    }

    function setStatus(text) {
        if (statusNode) { statusNode.textContent = text; }
    }

    function setSaving(on) {
        if (!submitBtn) { return; }
        submitBtn.disabled = on;
        submitBtn.textContent = on ? 'Saving…' : 'Save Settings';
    }

    function setTesting(on) {
        if (!testConnBtn) { return; }
        testConnBtn.disabled = on;
        testConnBtn.textContent = on ? 'Testing…' : 'Test Connection';
    }

    function showTestResult(ok, msg) {
        if (!testConnResult) { return; }
        testConnResult.classList.remove('d-none', 'text-success', 'text-danger');
        testConnResult.classList.add(ok ? 'text-success' : 'text-danger');
        testConnResult.textContent = msg || '';
    }

    function clearTestResult() {
        if (!testConnResult) { return; }
        testConnResult.classList.add('d-none');
        testConnResult.textContent = '';
    }

    // ── Provider section toggle ───────────────────────────────────────────────
    function applyProviderSection(provider) {
        if (smtpSection) {
            smtpSection.style.display = (provider === 'smtp') ? '' : 'none';
        }
        if (mailtrapSection) {
            mailtrapSection.style.display = (provider === 'mailtrap') ? '' : 'none';
        }
    }

    if (providerSelect) {
        providerSelect.addEventListener('change', function () {
            applyProviderSection(providerSelect.value);
            markDirty();
            clearTestResult();
        });
    }

    // ── Dirty tracking (unsaved changes guard) ────────────────────────────────
    var isDirty = false;

    function markDirty() {
        isDirty = true;
    }

    function markClean() {
        isDirty = false;
    }

    form.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.addEventListener('input', markDirty);
        el.addEventListener('change', markDirty);
    });

    window.addEventListener('beforeunload', function (e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = 'You have unsaved email settings changes. Leave anyway?';
        }
    });

    // ── Empty / loaded state helpers ──────────────────────────────────────────
    function showEmptyState() {
        if (emptyState) { emptyState.classList.remove('d-none'); }
        if (loadedState) { loadedState.classList.add('d-none'); }
    }

    function showLoadedState() {
        if (emptyState) { emptyState.classList.add('d-none'); }
        if (loadedState) { loadedState.classList.remove('d-none'); }
    }

    // ── Populate form from profile ────────────────────────────────────────────
    function populateForm(profile) {
        if (!profile || !profile.provider) {
            showEmptyState();
            return;
        }

        showLoadedState();

        if (providerSelect) {
            providerSelect.value = profile.provider;
        }
        applyProviderSection(profile.provider);

        var setVal = function (name, value) {
            var el = getField(name);
            if (el) { el.value = value || ''; }
        };

        setVal('fromAddress', profile.fromAddress);
        setVal('fromName', profile.fromName);

        if (profile.provider === 'smtp' && profile.smtp) {
            setVal('smtp.host', profile.smtp.host);
            setVal('smtp.port', profile.smtp.port);
            setVal('smtp.encryption', profile.smtp.encryption);
            setVal('smtp.username', profile.smtp.username);

            // Masked password: show placeholder, not actual value
            var pwEl = getField('smtp.password');
            if (pwEl) {
                if (profile.smtp.passwordMasked) {
                    pwEl.placeholder = '●●●●●●●● (leave blank to keep)';
                    pwEl.value = '';
                } else {
                    pwEl.placeholder = 'SMTP password';
                    pwEl.value = '';
                }
                pwEl.dataset.hasExisting = profile.smtp.passwordMasked ? '1' : '0';
            }
        }

        if (profile.provider === 'mailtrap' && profile.mailtrap) {
            setVal('mailtrap.accountId', profile.mailtrap.accountId);

            var tokenEl = getField('mailtrap.apiToken');
            if (tokenEl) {
                if (profile.mailtrap.apiTokenMasked) {
                    tokenEl.placeholder = '●●●●●●●● (leave blank to keep)';
                    tokenEl.value = '';
                } else {
                    tokenEl.placeholder = 'Mailtrap API token';
                    tokenEl.value = '';
                }
                tokenEl.dataset.hasExisting = profile.mailtrap.apiTokenMasked ? '1' : '0';
            }
        }

        markClean();
    }

    // ── Load current settings ─────────────────────────────────────────────────
    async function loadSettings() {
        clearFeedback();
        setStatus('Loading settings…');

        var token = await getApiToken();
        if (!token) {
            showFeedback('warning', 'Auth token not found. Please login again.');
            setStatus('Unable to load');
            showEmptyState();
            return;
        }

        try {
            var res = await fetch('/v1/hcm/email-settings', {
                method: 'GET',
                headers: buildHeaders(token),
                credentials: 'same-origin',
            });

            var payload = await res.json().catch(function () { return null; });

            if (res.status === 403) {
                showFeedback('danger', 'Access denied: only global admin can manage email settings.');
                setStatus('Access denied');
                showEmptyState();
                return;
            }

            if (!res.ok || !payload || payload.success !== true) {
                showFeedback('danger', 'Failed to load email settings.');
                setStatus('Load failed');
                showEmptyState();
                return;
            }

            var profile = payload.data || null;
            populateForm(profile);
            setStatus('Settings loaded');
        } catch (_e) {
            showFeedback('danger', 'Network error while loading settings.');
            setStatus('Load failed');
            showEmptyState();
        }
    }

    // ── Collect payload ───────────────────────────────────────────────────────
    function collectPayload(forTest) {
        var provider = providerSelect ? providerSelect.value : 'smtp';
        var data = {
            provider: provider,
            fromAddress: fieldVal('fromAddress') || undefined,
            fromName: fieldVal('fromName') || undefined,
        };

        if (provider === 'smtp') {
            var pwEl = getField('smtp.password');
            var pwVal = pwEl ? pwEl.value : '';
            var hasExisting = pwEl && pwEl.dataset.hasExisting === '1';

            data.smtp = {
                host: fieldVal('smtp.host') || undefined,
                port: fieldVal('smtp.port') ? parseInt(fieldVal('smtp.port'), 10) : undefined,
                encryption: fieldVal('smtp.encryption') || undefined,
                username: fieldVal('smtp.username') || undefined,
            };

            // Only include password if the user typed one (or it's required for test)
            if (pwVal !== '') {
                data.smtp.password = pwVal;
            } else if (forTest && !hasExisting) {
                data.smtp.password = '';
            }
        }

        if (provider === 'mailtrap') {
            var tokenEl = getField('mailtrap.apiToken');
            var tokenVal = tokenEl ? tokenEl.value : '';
            var hasExistingToken = tokenEl && tokenEl.dataset.hasExisting === '1';

            data.mailtrap = {
                accountId: fieldVal('mailtrap.accountId') ? parseInt(fieldVal('mailtrap.accountId'), 10) : undefined,
            };

            if (tokenVal !== '') {
                data.mailtrap.apiToken = tokenVal;
            }
        }

        return data;
    }

    // ── Save settings ─────────────────────────────────────────────────────────
    async function saveSettings(event) {
        event.preventDefault();
        clearFeedback();
        clearTestResult();

        var token = await getApiToken();
        if (!token) {
            showFeedback('warning', 'Auth token not found. Please login again.');
            return;
        }

        setSaving(true);

        try {
            var res = await fetch('/v1/hcm/email-settings', {
                method: 'PUT',
                headers: buildHeaders(token, true),
                credentials: 'same-origin',
                body: JSON.stringify(collectPayload(false)),
            });

            var payload = await res.json().catch(function () { return null; });

            if (!res.ok || !payload || payload.success !== true) {
                var errMsg = (payload && payload.error && payload.error.message)
                    ? payload.error.message
                    : 'Failed to save email settings.';
                showFeedback('danger', errMsg);
                setStatus('Save failed');
                return;
            }

            // Re-populate with returned data to refresh masked fields
            populateForm(payload.data || null);
            showFeedback('success', 'Email settings saved successfully.');
            setStatus('Saved just now');
            markClean();
        } catch (_e) {
            showFeedback('danger', 'Network error while saving settings.');
            setStatus('Save failed');
        } finally {
            setSaving(false);
        }
    }

    // ── Test Connection ───────────────────────────────────────────────────────
    async function testConnection() {
        clearTestResult();
        clearFeedback();

        var token = await getApiToken();
        if (!token) {
            showFeedback('warning', 'Auth token not found. Please login again.');
            return;
        }

        setTesting(true);

        try {
            var payload = collectPayload(true);

            var res = await fetch('/v1/hcm/email-settings/test-connection', {
                method: 'POST',
                headers: buildHeaders(token, true),
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            var data = await res.json().catch(function () { return null; });

            if (!res.ok || !data || data.success !== true) {
                var errMsg = (data && data.error && data.error.message)
                    ? data.error.message
                    : 'Test connection failed.';
                showTestResult(false, '✗ ' + errMsg);
                return;
            }

            var result = data.data || {};
            if (result.connected === true) {
                showTestResult(true, '✓ Connection successful' + (result.latencyMs ? ' (' + result.latencyMs + 'ms)' : ''));
            } else {
                var detail = result.error || result.message || 'Connection failed.';
                showTestResult(false, '✗ ' + detail);
            }
        } catch (_e) {
            showTestResult(false, '✗ Network error during connection test.');
        } finally {
            setTesting(false);
        }
    }

    // ── Cancel ────────────────────────────────────────────────────────────────
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            clearFeedback();
            clearTestResult();
            loadSettings();
        });
    }

    // ── Test Connection button ────────────────────────────────────────────────
    if (testConnBtn) {
        testConnBtn.addEventListener('click', function () {
            testConnection().catch(function () {
                showTestResult(false, '✗ Unexpected error during test.');
                setTesting(false);
            });
        });
    }

    // ── Form submit ───────────────────────────────────────────────────────────
    form.addEventListener('submit', function (event) {
        saveSettings(event).catch(function () {
            showFeedback('danger', 'Unexpected error while saving settings.');
            setSaving(false);
        });
    });

    // ── Init ──────────────────────────────────────────────────────────────────
    loadSettings().catch(function () {
        showFeedback('danger', 'Failed to load email settings.');
        setStatus('Unable to load');
        showEmptyState();
    });
})();
