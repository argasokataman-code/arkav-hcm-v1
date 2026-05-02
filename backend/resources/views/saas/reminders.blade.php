<?php $page = 'saas-reminders'; ?>
@extends('layout.mainlayout')

@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-saas-reminders-page>

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Payment Reminders</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">SaaS</li>
                        <li class="breadcrumb-item active" aria-current="page">Reminders</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <button class="btn btn-primary d-flex align-items-center" id="btn_send_reminders">
                        <i class="ti ti-bell me-2"></i>Send Reminders Now
                    </button>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <!-- Summary Section -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Overdue Invoices</p>
                                <h3 class="mb-0 text-danger" id="count_overdue">0</h3>
                            </div>
                            <i class="ti ti-alert-circle text-danger fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Due Soon (7 days)</p>
                                <h3 class="mb-0 text-warning" id="count_due_soon">0</h3>
                            </div>
                            <i class="ti ti-calendar-event text-warning fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Overdue Amount</p>
                                <h3 class="mb-0 text-danger" id="amount_overdue">Rp 0</h3>
                            </div>
                            <i class="ti ti-coin text-danger fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Last Sent</p>
                                <h5 class="mb-0" id="last_reminder_sent">Never</h5>
                            </div>
                            <i class="ti ti-send text-primary fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-3">
                        <select class="form-select" id="filter_reminder_type" data-reminder-filter-type>
                            <option value="">All Types</option>
                            <option value="overdue">Overdue</option>
                            <option value="due_soon">Due Soon</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" placeholder="Search company..." id="search_reminders">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" id="btn_reset_reminder_filters">
                            <i class="ti ti-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reminders List Container -->
        <div data-reminders-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">Loading reminders...</div></div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

@push('scripts')
<script>
(function (window, document) {
    "use strict";

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function show(el) {
        if (el) {
            el.classList.remove("d-none");
        }
    }

    function hide(el) {
        if (el) {
            el.classList.add("d-none");
        }
    }

    function toNum(v) {
        var n = Number(v || 0);
        return isFinite(n) ? n : 0;
    }

    function esc(v) {
        return String(v == null ? "" : v)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatMoney(v) {
        return "Rp " + toNum(v).toLocaleString("id-ID", { maximumFractionDigits: 0 });
    }

    function formatDate(value) {
        if (!value) return "-";
        var d = new Date(value);
        if (isNaN(d.getTime())) return "-";
        return d.toLocaleDateString("id-ID", { year: "numeric", month: "short", day: "2-digit" });
    }

    function getTenantContext() {
        if (window.AuthApi && typeof window.AuthApi.getTenantContext === "function") {
            return window.AuthApi.getTenantContext() || {};
        }
        return {};
    }

    function buildHeaders() {
        var headers = { Accept: "application/json", "Content-Type": "application/json" };
        var token = window.AuthApi && typeof window.AuthApi.getToken === "function"
            ? window.AuthApi.getToken()
            : null;

        if (token) {
            headers.Authorization = "Bearer " + token;
        }

        var tenant = getTenantContext();
        if (tenant.companyCode) {
            headers["X-Company-Code"] = tenant.companyCode;
        }
        if (tenant.companyId) {
            headers["X-Company-Id"] = String(tenant.companyId);
        }
        if (tenant.companyUuid) {
            headers["X-Company-UUID"] = String(tenant.companyUuid);
        }

        return headers;
    }

    function request(method, path, body) {
        return fetch(path, {
            method: method,
            headers: buildHeaders(),
            credentials: "same-origin",
            body: body ? JSON.stringify(body) : undefined,
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    var err = (data && data.error && data.error.message) || "Request failed.";
                    throw new Error(err);
                }
                return data;
            });
        });
    }

    function daysUntil(dateValue) {
        var dueDate = new Date(dateValue);
        if (isNaN(dueDate.getTime())) return null;

        var now = new Date();
        var startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var startOfDue = new Date(dueDate.getFullYear(), dueDate.getMonth(), dueDate.getDate());
        return Math.round((startOfDue.getTime() - startOfToday.getTime()) / 86400000);
    }

    function normalizeInvoice(item) {
        var dayDiff = daysUntil(item.dueDate);
        var overdue = Boolean(item.isOverdue) || (dayDiff !== null && dayDiff < 0);
        var dueSoon = !overdue && dayDiff !== null && dayDiff <= 7;

        return {
            id: item.id,
            uuid: item.uuid,
            invoiceNumber: item.invoiceNumber || "-",
            companyName: item.companyName || "-",
            amountDue: toNum(item.amountDue),
            dueDate: item.dueDate,
            status: item.status || "unknown",
            isPaid: Boolean(item.isPaid),
            isOverdue: overdue,
            isDueSoon: dueSoon,
        };
    }

    function toast(message, kind) {
        if (window.ArcavUi) {
            if (kind === "success" && typeof window.ArcavUi.showSuccess === "function") {
                window.ArcavUi.showSuccess(message);
                return;
            }
            if (kind === "error" && typeof window.ArcavUi.showError === "function") {
                window.ArcavUi.showError(message);
                return;
            }
            if (typeof window.ArcavUi.showInfo === "function") {
                window.ArcavUi.showInfo("Payment Reminder", message);
                return;
            }
        }
        window.alert(message);
    }

    function init() {
        var root = qs("[data-saas-reminders-page]");
        if (!root) {
            return;
        }

        var listContainer = qs("[data-reminders-list-container]", root);
        var filterType = qs("#filter_reminder_type", root);
        var searchInput = qs("#search_reminders", root);
        var resetBtn = qs("#btn_reset_reminder_filters", root);
        var sendAllBtn = qs("#btn_send_reminders", root);
        var countOverdue = qs("#count_overdue", root);
        var countDueSoon = qs("#count_due_soon", root);
        var amountOverdue = qs("#amount_overdue", root);
        var lastSent = qs("#last_reminder_sent", root);

        var state = {
            loading: false,
            invoices: [],
            sending: false,
            lastSentAt: null,
        };

        function setLastSent(value) {
            state.lastSentAt = value;
            if (lastSent) {
                lastSent.textContent = value ? formatDate(value) : "Never";
            }
        }

        function selectedType() {
            return filterType ? String(filterType.value || "") : "";
        }

        function selectedSearch() {
            return searchInput ? String(searchInput.value || "").trim().toLowerCase() : "";
        }

        function getFiltered() {
            var type = selectedType();
            var keyword = selectedSearch();

            return state.invoices.filter(function (inv) {
                if (inv.isPaid) {
                    return false;
                }

                if (type === "overdue" && !inv.isOverdue) {
                    return false;
                }
                if (type === "due_soon" && !inv.isDueSoon) {
                    return false;
                }

                if (keyword) {
                    var haystack = (inv.companyName + " " + inv.invoiceNumber).toLowerCase();
                    if (haystack.indexOf(keyword) === -1) {
                        return false;
                    }
                }

                return inv.isOverdue || inv.isDueSoon;
            });
        }

        function updateSummary() {
            var overdueList = state.invoices.filter(function (inv) {
                return !inv.isPaid && inv.isOverdue;
            });
            var dueSoonList = state.invoices.filter(function (inv) {
                return !inv.isPaid && inv.isDueSoon;
            });
            var overdueTotal = overdueList.reduce(function (sum, inv) {
                return sum + inv.amountDue;
            }, 0);

            if (countOverdue) {
                countOverdue.textContent = String(overdueList.length);
            }
            if (countDueSoon) {
                countDueSoon.textContent = String(dueSoonList.length);
            }
            if (amountOverdue) {
                amountOverdue.textContent = formatMoney(overdueTotal);
            }
        }

        function render() {
            if (!listContainer) {
                return;
            }

            var rows = getFiltered();
            if (!rows.length) {
                listContainer.innerHTML = '<div class="card"><div class="card-body text-center text-muted py-4">No reminder targets found.</div></div>';
                return;
            }

            listContainer.innerHTML =
                '<div class="card">' +
                '  <div class="table-responsive">' +
                '    <table class="table table-hover mb-0">' +
                '      <thead class="table-light">' +
                '        <tr>' +
                '          <th>Company</th>' +
                '          <th>Invoice</th>' +
                '          <th>Due Date</th>' +
                '          <th>Status</th>' +
                '          <th>Amount</th>' +
                '          <th class="text-end">Action</th>' +
                '        </tr>' +
                '      </thead>' +
                '      <tbody>' +
                rows.map(function (inv) {
                    var badgeClass = inv.isOverdue ? 'badge-danger' : 'badge-warning';
                    var label = inv.isOverdue ? 'Overdue' : 'Due Soon';
                    var ref = inv.uuid || inv.id;
                    return (
                        '<tr>' +
                        '  <td>' + esc(inv.companyName) + '</td>' +
                        '  <td><strong>' + esc(inv.invoiceNumber) + '</strong></td>' +
                        '  <td>' + esc(formatDate(inv.dueDate)) + '</td>' +
                        '  <td><span class="badge ' + badgeClass + '">' + label + '</span></td>' +
                        '  <td>' + esc(formatMoney(inv.amountDue)) + '</td>' +
                        '  <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-send-reminder="' + esc(ref) + '"><i class="ti ti-send"></i> Send</button></td>' +
                        '</tr>'
                    );
                }).join('') +
                '      </tbody>' +
                '    </table>' +
                '  </div>' +
                '</div>';
        }

        function loadInvoices() {
            if (state.loading) {
                return Promise.resolve();
            }

            state.loading = true;
            if (listContainer) {
                listContainer.innerHTML = '<div class="card"><div class="card-body text-center text-muted py-4">Loading reminders...</div></div>';
            }

            return request("GET", "/v1/saas/invoices?per_page=200")
                .then(function (payload) {
                    var list = payload && payload.data && Array.isArray(payload.data) ? payload.data : [];
                    state.invoices = list.map(normalizeInvoice);
                    updateSummary();
                    render();
                })
                .catch(function (err) {
                    if (listContainer) {
                        listContainer.innerHTML = '<div class="card"><div class="card-body text-center text-danger py-4">' + esc(err.message || 'Failed loading reminders.') + '</div></div>';
                    }
                })
                .finally(function () {
                    state.loading = false;
                });
        }

        function sendReminder(reference) {
            if (!reference || state.sending) {
                return Promise.resolve(false);
            }

            state.sending = true;
            return request("POST", "/v1/saas/invoices/" + encodeURIComponent(String(reference)) + "/send-email", {})
                .then(function () {
                    setLastSent(new Date().toISOString());
                    toast("Reminder sent.", "success");
                    return true;
                })
                .catch(function (err) {
                    toast(err.message || "Failed sending reminder.", "error");
                    return false;
                })
                .finally(function () {
                    state.sending = false;
                });
        }

        function sendBulk() {
            var targets = getFiltered();
            if (!targets.length) {
                toast("No reminder targets found.", "error");
                return;
            }

            var sequence = Promise.resolve();
            var successCount = 0;

            targets.forEach(function (inv) {
                var reference = inv.uuid || inv.id;
                sequence = sequence.then(function () {
                    return sendReminder(reference).then(function (ok) {
                        if (ok) {
                            successCount += 1;
                        }
                    });
                });
            });

            sequence.then(function () {
                toast("Completed sending " + String(successCount) + " reminder(s).", "success");
            });
        }

        if (filterType) {
            filterType.addEventListener("change", render);
        }

        if (searchInput) {
            var timer = null;
            searchInput.addEventListener("input", function () {
                if (timer) {
                    window.clearTimeout(timer);
                }
                timer = window.setTimeout(render, 250);
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener("click", function () {
                if (filterType) filterType.value = "";
                if (searchInput) searchInput.value = "";
                render();
            });
        }

        if (sendAllBtn) {
            sendAllBtn.addEventListener("click", function () {
                sendBulk();
            });
        }

        if (listContainer) {
            listContainer.addEventListener("click", function (event) {
                var button = event.target && event.target.closest
                    ? event.target.closest("button[data-send-reminder]")
                    : null;
                if (!button) {
                    return;
                }
                var reference = button.getAttribute("data-send-reminder");
                sendReminder(reference);
            });
        }

        setLastSent(null);
        loadInvoices();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
</script>
@endpush

@endsection
