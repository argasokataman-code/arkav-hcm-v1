(function () {
    'use strict';

    var form = document.querySelector('[data-notification-settings-form]');
    if (!form) {
        return;
    }

    var feedback = document.querySelector('[data-notification-settings-feedback]');
    var rowsNode = document.querySelector('[data-notification-settings-rows]');
    var statusNode = document.querySelector('[data-notification-settings-status]');
    var submitButton = document.querySelector('[data-notification-settings-submit]');
    var resetButton = document.querySelector('[data-notification-settings-reset]');
    var observabilityPanel = document.querySelector('[data-notification-observability-panel]');
    var observabilityHours = document.querySelector('[data-notification-observability-hours]');
    var observabilityChannel = document.querySelector('[data-notification-observability-channel]');
    var observabilityRefresh = document.querySelector('[data-notification-observability-refresh]');
    var totalAllNode = document.querySelector('[data-observability-total-all]');
    var totalSentNode = document.querySelector('[data-observability-total-sent]');
    var totalFailedNode = document.querySelector('[data-observability-total-failed]');
    var totalDroppedNode = document.querySelector('[data-observability-total-dropped]');
    var statusBreakdownNode = document.querySelector('[data-observability-status-breakdown]');
    var topFailedNode = document.querySelector('[data-observability-top-failed]');
    var lastUpdatedNode = document.querySelector('[data-observability-last-updated]');

    var DEFAULT_MODULES = [
        {
            eventKey: 'asset.assigned',
            title: 'Asset Assignment and Return',
            description: 'Alerts when assets are assigned or returned by employees.'
        },
        {
            eventKey: 'subscription.change_approval_needed',
            title: 'Subscription Change Approvals',
            description: 'Approval alerts for subscription plan changes.'
        },
        {
            eventKey: 'billing.invoice.overdue',
            title: 'Billing and Payment Alerts',
            description: 'Notifications for overdue invoices and payment operations.'
        },
        {
            eventKey: 'billing.payment_failed',
            title: 'Payment Failure Alerts',
            description: 'Warnings when payment processing fails and needs action.'
        },
        {
            eventKey: 'billing.subscription.expiring_in_7_days',
            title: 'Subscription Expiry Reminders',
            description: 'Reminders when a subscription is close to expiry.'
        },
        {
            eventKey: 'auth.password_reset_link_requested',
            title: 'Security Notifications',
            description: 'Security-critical notifications, including password reset flows.'
        }
    ];
    var modules = DEFAULT_MODULES.slice();

    var CHANNELS = [
        { key: 'database', label: 'Push' },
        { key: 'sms', label: 'SMS' },
        { key: 'mail', label: 'Email' }
    ];

    var snapshot = {};

    function isGlobalHcmAdmin() {
        try {
            return Boolean(window.AuthUser && window.AuthUser.hcmGlobalAdmin === true);
        } catch (_e) {}

        return false;
    }

    function showFeedback(type, message) {
        if (!feedback) {
            return;
        }

        feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        feedback.classList.add('alert-' + type);
        feedback.textContent = message;
    }

    function clearFeedback() {
        if (!feedback) {
            return;
        }

        feedback.classList.add('d-none');
        feedback.classList.remove('alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        feedback.textContent = '';
    }

    function setStatus(text) {
        if (statusNode) {
            statusNode.textContent = text;
        }
    }

    function setSaving(isSaving) {
        if (!submitButton) {
            return;
        }

        submitButton.disabled = isSaving;
        submitButton.textContent = isSaving ? 'Saving...' : 'Save Preferences';
    }

    function getTenantContext() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getTenantContext === 'function') {
                return window.AuthApi.getTenantContext() || {};
            }
        } catch (_e) {}

        return {};
    }

    async function getApiToken() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getToken === 'function') {
                var token = window.AuthApi.getToken();
                if (token) {
                    return token;
                }
            }
        } catch (_e) {}

        try {
            var response = await fetch('/api-token', { credentials: 'include' });
            var payload = await response.json();
            var tokenFromPayload = payload && payload.data && payload.data.token
                ? payload.data.token
                : (payload && payload.token ? payload.token : null);
            if (tokenFromPayload) {
                return tokenFromPayload;
            }
        } catch (_e) {}

        return null;
    }

    function buildHeaders(token) {
        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        if (token) {
            headers.Authorization = 'Bearer ' + String(token);
        }

        var tenant = getTenantContext();
        if (tenant && tenant.companyCode) {
            headers['X-Company-Code'] = String(tenant.companyCode);
        }
        if (tenant && tenant.companyId) {
            headers['X-Company-Id'] = String(tenant.companyId);
        }
        if (tenant && tenant.companyUuid) {
            headers['X-Company-UUID'] = String(tenant.companyUuid);
        }

        return headers;
    }

    function checkboxSelector(eventKey, channel) {
        return '[data-preference-checkbox="' + eventKey + '|' + channel + '"]';
    }

    function defaultEnabled(channel) {
        return channel !== 'sms';
    }

    function renderRows() {
        if (!rowsNode) {
            return;
        }

        var html = '';
        modules.forEach(function (module, index) {
            var isLast = index === modules.length - 1;
            var rowClass = isLast ? ' border-0 pb-0' : '';
            html += '<tr>';
            html += '<td class="ps-0' + rowClass + '">';
            html += '<h5 class="mb-1 fw-medium">' + module.title + '</h5>';
            html += '<p class="mb-0">' + module.description + '</p>';
            html += '</td>';

            CHANNELS.forEach(function (channel) {
                html += '<td class="' + (isLast ? 'border-0 pb-0' : '') + '">';
                html += '<div class="form-check form-check-md form-switch me-2">';
                html += '<input class="form-check-input me-2" type="checkbox" role="switch"';
                html += ' data-preference-checkbox="' + module.eventKey + '|' + channel.key + '"';
                html += ' data-event-key="' + module.eventKey + '"';
                html += ' data-channel="' + channel.key + '"';
                if (defaultEnabled(channel.key)) {
                    html += ' checked';
                }
                html += '>';
                html += '</div>';
                html += '</td>';
            });

            html += '</tr>';
        });

        rowsNode.innerHTML = html;
    }

    function normalizeTemplateItems(items) {
        if (!Array.isArray(items)) {
            return [];
        }

        return items
            .map(function (item) {
                var eventKey = String(item.eventKey || '').trim();
                if (!eventKey) {
                    return null;
                }

                return {
                    eventKey: eventKey,
                    title: String(item.title || eventKey),
                    description: String(item.description || '')
                };
            })
            .filter(Boolean);
    }

    async function loadTemplateCatalog() {
        var token = await getApiToken();
        if (!token) {
            return;
        }

        var response = await fetch('/v1/hcm/notifications/templates', {
            method: 'GET',
            headers: buildHeaders(token),
            credentials: 'same-origin'
        });

        var payload = await response.json().catch(function () { return null; });
        if (!response.ok || !payload || payload.success !== true) {
            return;
        }

        var items = normalizeTemplateItems(payload.data && payload.data.items);
        if (items.length > 0) {
            modules = items;
            renderRows();
        }
    }

    function applySnapshot() {
        Object.keys(snapshot).forEach(function (key) {
            var parts = key.split('|');
            var node = document.querySelector(checkboxSelector(parts[0], parts[1]));
            if (node) {
                node.checked = Boolean(snapshot[key]);
            }
        });
    }

    function captureSnapshot() {
        snapshot = {};

        var checkboxes = document.querySelectorAll('[data-preference-checkbox]');
        Array.prototype.forEach.call(checkboxes, function (node) {
            var key = node.getAttribute('data-preference-checkbox');
            snapshot[key] = Boolean(node.checked);
        });
    }

    async function loadPreferences() {
        clearFeedback();
        setStatus('Loading notification preferences...');

        var token = await getApiToken();
        if (!token) {
            showFeedback('warning', 'API token not found. Please login again.');
            setStatus('Unable to load preferences');
            return;
        }

        var response = await fetch('/v1/hcm/notification-preferences', {
            method: 'GET',
            headers: buildHeaders(token),
            credentials: 'same-origin'
        });

        var payload = await response.json().catch(function () { return null; });
        if (!response.ok || !payload || payload.success !== true) {
            showFeedback('danger', 'Failed to load notification preferences.');
            setStatus('Unable to load preferences');
            return;
        }

        var items = (payload.data && Array.isArray(payload.data.items)) ? payload.data.items : [];
        items.forEach(function (item) {
            var eventKey = String(item.eventKey || '');
            var channel = String(item.channel || 'database');
            var enabled = Boolean(item.enabled);
            var node = document.querySelector(checkboxSelector(eventKey, channel));
            if (node) {
                node.checked = enabled;
            }
        });

        captureSnapshot();
        setStatus('Preferences loaded');
    }

    function collectPayload() {
        var checkboxes = document.querySelectorAll('[data-preference-checkbox]');
        var preferences = [];

        Array.prototype.forEach.call(checkboxes, function (node) {
            preferences.push({
                eventKey: String(node.getAttribute('data-event-key') || ''),
                channel: String(node.getAttribute('data-channel') || 'database'),
                enabled: Boolean(node.checked),
                digestMode: 'instant'
            });
        });

        return { preferences: preferences };
    }

    async function savePreferences(event) {
        event.preventDefault();
        if (!ArcavValidation.validateForm(form)) { return; }
        clearFeedback();

        var token = await getApiToken();
        if (!token) {
            showFeedback('warning', 'API token not found. Please login again.');
            return;
        }

        setSaving(true);

        try {
            var response = await fetch('/v1/hcm/notification-preferences', {
                method: 'PUT',
                headers: Object.assign(buildHeaders(token), {
                    'Content-Type': 'application/json'
                }),
                credentials: 'same-origin',
                body: JSON.stringify(collectPayload())
            });

            var payload = await response.json().catch(function () { return null; });
            if (!response.ok || !payload || payload.success !== true) {
                throw new Error('Failed to save notification preferences.');
            }

            captureSnapshot();
            showFeedback('success', 'Notification preferences saved successfully.');
            setStatus('Saved just now');
        } catch (_error) {
            showFeedback('danger', 'Failed to save notification preferences.');
            setStatus('Save failed');
        } finally {
            setSaving(false);
        }
    }

    function renderStatusBreakdown(items) {
        if (!statusBreakdownNode) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            statusBreakdownNode.textContent = 'No status data.';
            return;
        }

        statusBreakdownNode.innerHTML = items
            .map(function (item) {
                var status = String(item.status || 'unknown').toUpperCase();
                var total = Number(item.total || 0);
                return '<div class="d-flex justify-content-between py-1 border-bottom"><span>' + status + '</span><strong>' + total + '</strong></div>';
            })
            .join('');
    }

    function renderTopFailed(items) {
        if (!topFailedNode) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            topFailedNode.textContent = 'No failed events in selected window.';
            return;
        }

        topFailedNode.innerHTML = items
            .map(function (item) {
                var eventKey = String(item.eventKey || '-');
                var total = Number(item.total || 0);
                return '<div class="d-flex justify-content-between py-1 border-bottom"><span>' + eventKey + '</span><strong>' + total + '</strong></div>';
            })
            .join('');
    }

    function setMetric(node, value) {
        if (!node) {
            return;
        }

        node.textContent = String(Number(value || 0));
    }

    async function loadObservabilitySummary() {
        if (!observabilityPanel || !isGlobalHcmAdmin()) {
            return;
        }

        var token = await getApiToken();
        if (!token) {
            return;
        }

        var hours = Number((observabilityHours && observabilityHours.value) || 24);
        var channel = String((observabilityChannel && observabilityChannel.value) || '');
        var params = new URLSearchParams();
        params.set('hours', String(hours));
        if (channel) {
            params.set('channel', channel);
        }

        var response = await fetch('/v1/hcm/notifications/delivery-summary?' + params.toString(), {
            method: 'GET',
            headers: buildHeaders(token),
            credentials: 'same-origin'
        });

        var payload = await response.json().catch(function () { return null; });
        if (!response.ok || !payload || payload.success !== true) {
            return;
        }

        var data = payload.data || {};
        var totals = data.totals || {};
        var breakdown = data.breakdown || {};

        setMetric(totalAllNode, totals.all);
        setMetric(totalSentNode, totals.sent);
        setMetric(totalFailedNode, totals.failed);
        setMetric(totalDroppedNode, totals.dropped);
        renderStatusBreakdown(Array.isArray(breakdown.byStatus) ? breakdown.byStatus : []);
        renderTopFailed(Array.isArray(data.topFailedEvents) ? data.topFailedEvents : []);

        if (lastUpdatedNode) {
            lastUpdatedNode.textContent = 'Last updated: ' + new Date().toLocaleString();
        }
    }

    renderRows();
    form.addEventListener('submit', savePreferences);

    if (resetButton) {
        resetButton.addEventListener('click', function () {
            applySnapshot();
            clearFeedback();
            setStatus('Changes reset');
        });
    }

    if (observabilityRefresh) {
        observabilityRefresh.addEventListener('click', function () {
            loadObservabilitySummary();
        });
    }

    if (observabilityHours) {
        observabilityHours.addEventListener('change', function () {
            loadObservabilitySummary();
        });
    }

    if (observabilityChannel) {
        observabilityChannel.addEventListener('change', function () {
            loadObservabilitySummary();
        });
    }

    loadTemplateCatalog().catch(function () {
        // Template catalog is optional — fall back to DEFAULT_MODULES silently.
    }).then(function () {
        return loadPreferences();
    }).catch(function () {
        showFeedback('danger', 'Failed to load notification preferences.');
        setStatus('Unable to load preferences');
    });

    loadObservabilitySummary().catch(function () {
        // Keep settings page usable even if observability endpoint fails.
    });
})();
