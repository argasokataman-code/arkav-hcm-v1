(function () {
    'use strict';

    var pageNode = document.querySelector('[data-notification-observability-page]');
    if (!pageNode) {
        return;
    }

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
    var HOURS_STORAGE_KEY = 'hcm.notifications.observability.hours';
    var CHANNEL_STORAGE_KEY = 'hcm.notifications.observability.channel';

    function showToast(message, variant) {
        if (window.ArcavUi && typeof window.ArcavUi.showToast === 'function') {
            window.ArcavUi.showToast(message, variant || 'info');
            return;
        }

        // Fallback when shared UI helper is unavailable.
        window.alert(message);
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
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
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

    function setMetric(node, value) {
        if (!node) {
            return;
        }

        node.textContent = String(Number(value || 0));
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

        var token = getApiToken();
        topFailedNode.innerHTML = items
            .map(function (item) {
                var eventKey = String(item.eventKey || '-');
                var total = Number(item.total || 0);
                return '<div class="d-flex justify-content-between py-1 border-bottom cursor-pointer" style="cursor: pointer;" data-drilldown-event-key="' + eventKey + '"><span>' + eventKey + '</span><strong>' + total + '</strong></div>';
            })
            .join('');

        // Attach click handlers to drilldown items
        var drilldownItems = topFailedNode.querySelectorAll('[data-drilldown-event-key]');
        drilldownItems.forEach(function (item) {
            item.addEventListener('click', function () {
                var eventKey = item.getAttribute('data-drilldown-event-key');
                if (eventKey) {
                    showFailedEventDrilldown(eventKey, token);
                }
            });
        });
    }

    function restoreFilterState() {
        try {
            var savedHours = localStorage.getItem(HOURS_STORAGE_KEY);
            if (observabilityHours && savedHours) {
                observabilityHours.value = savedHours;
            }

            var savedChannel = localStorage.getItem(CHANNEL_STORAGE_KEY);
            if (observabilityChannel && savedChannel !== null) {
                observabilityChannel.value = savedChannel;
            }
        } catch (_e) {}
    }

    function persistFilterState() {
        try {
            if (observabilityHours) {
                localStorage.setItem(HOURS_STORAGE_KEY, String(observabilityHours.value || '24'));
            }

            if (observabilityChannel) {
                localStorage.setItem(CHANNEL_STORAGE_KEY, String(observabilityChannel.value || ''));
            }
        } catch (_e) {}
    }

    async function loadObservabilitySummary() {
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
            credentials: 'same-origin',
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

    if (observabilityRefresh) {
        observabilityRefresh.addEventListener('click', function () {
            loadObservabilitySummary();
        });
    }

    var observabilityExport = document.querySelector('[data-notification-observability-export]');
    if (observabilityExport) {
        observabilityExport.addEventListener('click', async function () {
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

            var url = '/v1/hcm/notifications/delivery-export?' + params.toString();
            var link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'delivery-export.csv');
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    if (observabilityHours) {
        observabilityHours.addEventListener('change', function () {
            persistFilterState();
            loadObservabilitySummary();
        });
    }

    if (observabilityChannel) {
        observabilityChannel.addEventListener('change', function () {
            persistFilterState();
            loadObservabilitySummary();
        });
    }

    async function showFailedEventDrilldown(eventKey, token) {
        if (!token) {
            token = await getApiToken();
        }
        if (!token) {
            return;
        }

        var modal = document.getElementById('failedEventDrilldownModal');
        if (!modal) {
            return;
        }

        var titleKeyNode = document.querySelector('[data-drilldown-event-key]');
        if (titleKeyNode) {
            titleKeyNode.textContent = eventKey;
        }

        resetDrilldownDetail();

        // Fetch first page
        fetchFailedEventPage(eventKey, 1, token);

        // Show modal
        var bsModal = new (window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal : null)(modal);
        if (bsModal) {
            bsModal.show();
        }
    }

    async function fetchFailedEventPage(eventKey, page, token) {
        var hours = Number((observabilityHours && observabilityHours.value) || 24);
        var channel = String((observabilityChannel && observabilityChannel.value) || '');
        var params = new URLSearchParams();
        params.set('status', 'failed');
        params.set('eventKey', eventKey);
        params.set('hours', String(hours));
        params.set('page', String(page));
        params.set('perPage', '10');
        if (channel) {
            params.set('channel', channel);
        }

        var response = await fetch('/v1/hcm/notifications/delivery-details?' + params.toString(), {
            method: 'GET',
            headers: buildHeaders(token),
            credentials: 'same-origin',
        });

        var payload = await response.json().catch(function () { return null; });
        if (!response.ok || !payload || payload.success !== true) {
            var errorElem = document.querySelector('[data-drilldown-last-error]');
            if (errorElem) {
                errorElem.textContent = 'Failed to load delivery details.';
            }
            return;
        }

        var data = payload.data || {};
        var items = Array.isArray(data.items) ? data.items : [];
        var meta = data.meta || {};

        renderFailedEventDetails(items);
        renderDrilldownPagination(eventKey, meta.page, meta.total, meta.perPage, token);
    }

    function renderFailedEventDetails(items) {
        if (items.length === 0) {
            resetDrilldownDetail();

            var errorElem = document.querySelector('[data-drilldown-last-error]');
            if (errorElem) {
                errorElem.textContent = 'No delivery details found.';
            }

            return;
        }

        // Show first failed event in detail
        var event = items[0];
        
        // Populate modal fields
        var eventKeyElem = document.querySelector('[data-drilldown-event-key-value]');
        if (eventKeyElem) eventKeyElem.textContent = String(event.eventKey || '-');

        var titleKeyElem = document.querySelector('[data-drilldown-event-key]');
        if (titleKeyElem) titleKeyElem.textContent = String(event.eventKey || '-');
        
        var channelElem = document.querySelector('[data-drilldown-channel]');
        if (channelElem) channelElem.textContent = String(event.channel || '-');
        
        var statusElem = document.querySelector('[data-drilldown-status]');
        if (statusElem) statusElem.textContent = String(event.status || 'failed');
        
        var attemptElem = document.querySelector('[data-drilldown-attempt-count]');
        if (attemptElem) attemptElem.textContent = String(event.attemptCount || '0');
        
        var recipientElem = document.querySelector('[data-drilldown-recipient]');
        if (recipientElem) recipientElem.textContent = String(event.recipient || '-');
        
        var createdElem = document.querySelector('[data-drilldown-created-at]');
        if (createdElem && event.createdAt) {
            createdElem.textContent = new Date(event.createdAt).toLocaleString();
        }
        
        var errorElem = document.querySelector('[data-drilldown-last-error]');
        if (errorElem) {
            errorElem.textContent = String(event.lastError || 'No error recorded.');
        }
        
        var metadataElem = document.querySelector('[data-drilldown-metadata]');
        if (metadataElem && event.metadata) {
            try {
                var metaObj = typeof event.metadata === 'string' ? JSON.parse(event.metadata) : event.metadata;
                metadataElem.textContent = JSON.stringify(metaObj, null, 2);

                var retryLogElem = document.querySelector('[data-drilldown-retry-log]');
                if (retryLogElem) {
                    var retryLog = Array.isArray(metaObj.retry_log) ? metaObj.retry_log : [];
                    if (retryLog.length === 0) {
                        retryLogElem.textContent = 'No manual retries recorded.';
                    } else {
                        retryLogElem.innerHTML = '';
                        retryLog.forEach(function (entry) {
                            var actorEmail = String(entry.actor_email || '-');
                            var retriedAt = entry.retried_at ? new Date(entry.retried_at).toLocaleString() : '-';
                            var previousStatus = String(entry.previous_status || '-');
                            var line = document.createElement('div');
                            line.className = 'border-bottom py-1';
                            line.textContent = actorEmail + ' retried at ' + retriedAt + ' (previous status: ' + previousStatus + ')';
                            retryLogElem.appendChild(line);
                        });
                    }
                }
            } catch (_e) {
                metadataElem.textContent = String(event.metadata);
            }
        }
        
        // Attach retry handler
        var retryBtn = document.querySelector('[data-drilldown-retry-btn]');
        if (retryBtn) {
            retryBtn.onclick = function () {
                retryFailedDelivery(event.id, event.eventKey);
            };
        }
    }

    function resetDrilldownDetail() {
        var eventKeyElem = document.querySelector('[data-drilldown-event-key-value]');
        if (eventKeyElem) eventKeyElem.textContent = '-';

        var channelElem = document.querySelector('[data-drilldown-channel]');
        if (channelElem) channelElem.textContent = '-';

        var statusElem = document.querySelector('[data-drilldown-status]');
        if (statusElem) statusElem.textContent = 'failed';

        var attemptElem = document.querySelector('[data-drilldown-attempt-count]');
        if (attemptElem) attemptElem.textContent = '-';

        var recipientElem = document.querySelector('[data-drilldown-recipient]');
        if (recipientElem) recipientElem.textContent = '-';

        var createdElem = document.querySelector('[data-drilldown-created-at]');
        if (createdElem) createdElem.textContent = '-';

        var errorElem = document.querySelector('[data-drilldown-last-error]');
        if (errorElem) errorElem.textContent = 'Loading...';

        var metadataElem = document.querySelector('[data-drilldown-metadata]');
        if (metadataElem) metadataElem.textContent = '{}';

        var retryLogElem = document.querySelector('[data-drilldown-retry-log]');
        if (retryLogElem) retryLogElem.textContent = 'No manual retries recorded.';
    }
    
    async function retryFailedDelivery(deliveryId, eventKey) {
        var token = await getApiToken();
        if (!token) {
            showToast('Authentication error. Please reload and try again.', 'danger');
            return;
        }
        
        var response = await fetch('/v1/hcm/notifications/delivery/' + encodeURIComponent(String(deliveryId)) + '/retry', {
            method: 'POST',
            headers: buildHeaders(token),
            credentials: 'same-origin',
        });
        
        var payload = await response.json().catch(function () { return null; });
        if (response.ok && payload && payload.success) {
            showToast('Retry queued for event: ' + eventKey, 'success');
            loadObservabilitySummary(token);
            showFailedEventDrilldown(eventKey, token);
        } else {
            var apiMessage = payload && payload.error && payload.error.message
                ? payload.error.message
                : 'Unknown error';
            showToast('Failed to retry delivery: ' + apiMessage, 'danger');
        }
    }

    function renderDrilldownPagination(eventKey, page, total, perPage, token) {
        var paginationNode = document.querySelector('[data-drilldown-pagination]');
        if (!paginationNode) {
            return;
        }

        perPage = Number(perPage || 10);
        var totalPages = Math.ceil(Number(total || 0) / perPage);

        if (totalPages <= 1) {
            paginationNode.innerHTML = '<small class=\"text-muted\">Page ' + page + ' of ' + totalPages + '</small>';
            return;
        }

        var html = '<small class=\"text-muted me-2\">Page ' + page + ' of ' + totalPages + '</small>';
        if (page > 1) {
            html += '<button class=\"btn btn-sm btn-light me-1\" data-drilldown-page=\"' + (page - 1) + '\">Previous</button>';
        }
        if (page < totalPages) {
            html += '<button class=\"btn btn-sm btn-light\" data-drilldown-page=\"' + (page + 1) + '\">Next</button>';
        }

        paginationNode.innerHTML = html;

        // Attach pagination handlers
        paginationNode.querySelectorAll('[data-drilldown-page]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var nextPage = Number(btn.getAttribute('data-drilldown-page'));
                fetchFailedEventPage(eventKey, nextPage, token);
            });
        });
    }

    restoreFilterState();

    loadObservabilitySummary().catch(function () {
        // Keep page visible even if API is not reachable.
    });
})();
