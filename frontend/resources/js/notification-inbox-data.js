(function () {
    'use strict';

    var contentNode = document.querySelector('[data-notification-content]');
    var titleNode = document.querySelector('[data-notification-title]');
    var unreadBadgeNode = document.querySelector('[data-notification-unread-badge]');
    var markAllNode = document.querySelector('[data-notification-mark-all]');
    var refreshNode = document.querySelector('[data-notification-refresh]');
    var triggerNode = document.getElementById('notification_popup');

    if (!contentNode || !titleNode || !triggerNode) {
        return;
    }

    var state = {
        unreadCount: 0,
        loading: false,
        items: []
    };

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
                var authToken = window.AuthApi.getToken();
                if (authToken) {
                    return authToken;
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

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatRelativeTime(value) {
        if (!value) {
            return 'Just now';
        }

        var parsed = new Date(value);
        var diffSeconds = Math.max(0, Math.floor((Date.now() - parsed.getTime()) / 1000));
        if (diffSeconds < 60) {
            return 'Just now';
        }
        if (diffSeconds < 3600) {
            return Math.floor(diffSeconds / 60) + ' mins ago';
        }
        if (diffSeconds < 86400) {
            return Math.floor(diffSeconds / 3600) + ' hrs ago';
        }
        return Math.floor(diffSeconds / 86400) + ' days ago';
    }

    function renderUnreadBadge() {
        if (!unreadBadgeNode) {
            return;
        }

        unreadBadgeNode.textContent = String(state.unreadCount || 0);
        unreadBadgeNode.classList.toggle('d-none', !state.unreadCount);
        unreadBadgeNode.classList.toggle('d-flex', Boolean(state.unreadCount));
    }

    function renderTitle() {
        titleNode.textContent = 'Notifications (' + String(state.unreadCount || 0) + ')';
    }

    function renderContent() {
        if (state.loading) {
            contentNode.innerHTML = '<div class="text-muted text-center py-3">Loading notifications...</div>';
            return;
        }

        if (!Array.isArray(state.items) || state.items.length === 0) {
            contentNode.innerHTML = '<div class="text-muted text-center py-3">No notifications yet.</div>';
            return;
        }

        var html = '<div class="d-flex flex-column">';

        state.items.forEach(function (item, index) {
            var isLast = index === state.items.length - 1;
            var rowClass = isLast ? 'border-0 mb-0 pb-0' : 'border-bottom mb-3 pb-3';
            var title = escapeHtml(item.title || item.eventKey || 'Notification');
            var body = escapeHtml(item.body || '');
            var createdAt = formatRelativeTime(item.createdAt);
            var severity = escapeHtml(item.severity || 'informational');

            html += '<div class="' + rowClass + '">';
            html += '<div class="d-flex align-items-start justify-content-between gap-2">';
            html += '<div class="flex-grow-1">';
            html += '<p class="mb-1"><span class="text-dark fw-semibold">' + title + '</span></p>';
            if (body) {
                html += '<p class="mb-1 text-muted">' + body + '</p>';
            }
            html += '<div class="d-flex align-items-center gap-2">';
            html += '<span>' + escapeHtml(createdAt) + '</span>';
            html += '<span class="badge bg-light text-dark">' + severity + '</span>';
            html += '</div>';
            html += '</div>';
            if (!item.isRead) {
                html += '<button type="button" class="btn btn-sm btn-outline-primary" data-notification-mark-read-item="' + escapeHtml(item.uuid) + '">Mark read</button>';
            }
            html += '</div>';
            html += '</div>';
        });

        html += '</div>';
        contentNode.innerHTML = html;
    }

    async function fetchUnreadCount(token) {
        var response = await fetch('/v1/hcm/notifications/unread-count', {
            method: 'GET',
            headers: buildHeaders(token),
            credentials: 'same-origin'
        });

        var payload = await response.json().catch(function () { return null; });
        if (!response.ok || !payload || payload.success !== true) {
            return;
        }

        state.unreadCount = Number((payload.data && payload.data.unreadCount) || 0);
    }

    async function fetchLatestNotifications(token) {
        var response = await fetch('/v1/hcm/notifications?page=1&perPage=5', {
            method: 'GET',
            headers: buildHeaders(token),
            credentials: 'same-origin'
        });

        var payload = await response.json().catch(function () { return null; });
        if (!response.ok || !payload || payload.success !== true) {
            state.items = [];
            return;
        }

        var data = payload.data || {};
        state.items = Array.isArray(data.items) ? data.items : [];
        if (data.meta && Number.isFinite(Number(data.meta.unreadCount))) {
            state.unreadCount = Number(data.meta.unreadCount);
        }
    }

    async function refreshNotifications() {
        var token = await getApiToken();
        if (!token) {
            state.loading = false;
            state.items = [];
            state.unreadCount = 0;
            renderTitle();
            renderUnreadBadge();
            contentNode.innerHTML = '<div class="text-muted text-center py-3">Unable to load notifications. Login required.</div>';
            return;
        }

        state.loading = true;
        renderContent();

        try {
            await Promise.all([
                fetchUnreadCount(token),
                fetchLatestNotifications(token)
            ]);
        } catch (_e) {
            state.items = [];
        } finally {
            state.loading = false;
            renderTitle();
            renderUnreadBadge();
            renderContent();
        }
    }

    async function markOneRead(notificationUuid) {
        var token = await getApiToken();
        if (!token || !notificationUuid) {
            return;
        }

        await fetch('/v1/hcm/notifications/' + encodeURIComponent(String(notificationUuid)) + '/read', {
            method: 'POST',
            headers: Object.assign(buildHeaders(token), {
                'Content-Type': 'application/json'
            }),
            credentials: 'same-origin',
            body: '{}'
        });

        await refreshNotifications();
    }

    async function markAllRead() {
        var token = await getApiToken();
        if (!token) {
            return;
        }

        await fetch('/v1/hcm/notifications/read-all', {
            method: 'POST',
            headers: Object.assign(buildHeaders(token), {
                'Content-Type': 'application/json'
            }),
            credentials: 'same-origin',
            body: '{}'
        });

        await refreshNotifications();
    }

    contentNode.addEventListener('click', function (event) {
        var button = event.target.closest('[data-notification-mark-read-item]');
        if (!button) {
            return;
        }

        event.preventDefault();
        markOneRead(button.getAttribute('data-notification-mark-read-item'));
    });

    if (markAllNode) {
        markAllNode.addEventListener('click', function (event) {
            event.preventDefault();
            markAllRead();
        });
    }

    if (refreshNode) {
        refreshNode.addEventListener('click', function (event) {
            event.preventDefault();
            refreshNotifications();
        });
    }

    triggerNode.addEventListener('shown.bs.dropdown', function () {
        refreshNotifications();
    });

    refreshNotifications();
    window.setInterval(refreshNotifications, 60000);
})();
