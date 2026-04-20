(function (window, document) {
    "use strict";

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function setText(el, text) {
        if (!el) return;
        el.textContent = String(text == null ? "" : text);
    }

    function show(el) {
        if (!el) return;
        el.classList.remove("d-none");
    }

    function hide(el) {
        if (!el) return;
        el.classList.add("d-none");
    }

    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function badge(text, kind) {
        var cls = "badge bg-secondary-subtle text-secondary";
        if (kind === "success") cls = "badge bg-success-subtle text-success";
        if (kind === "warning") cls = "badge bg-warning-subtle text-warning";
        if (kind === "danger") cls = "badge bg-danger-subtle text-danger";
        if (kind === "info") cls = "badge bg-info-subtle text-info";
        return '<span class="' + cls + '">' + escapeHtml(text) + "</span>";
    }

    function statusBadgeSubscription(status) {
        var s = String(status || "");
        if (s === "active") return badge("ACTIVE", "success");
        if (s === "trial") return badge("TRIAL", "info");
        if (s === "pending_payment") return badge("PENDING_PAYMENT", "warning");
        if (s === "suspended") return badge("SUSPENDED", "danger");
        if (s === "expired") return badge("EXPIRED", "danger");
        return badge(s || "—", "secondary");
    }

    function emailBadge(status) {
        var s = String(status || "");
        if (s === "sent") return badge("SENT", "success");
        if (s === "failed") return badge("FAILED", "danger");
        return badge("NOT_SENT", "secondary");
    }

    function renderStateBadges(stateBadges) {
        if (!Array.isArray(stateBadges) || !stateBadges.length) return "";

        return (
            '<div class="d-flex flex-wrap gap-1 mt-2">' +
            stateBadges
                .map(function (stateBadge) {
                    return badge(stateBadge.label || stateBadge.code || "STATE", stateBadge.kind || "warning");
                })
                .join("") +
            "</div>"
        );
    }

    function buildInvoiceDetailUrl(invoice) {
        if (!invoice) return "#";
        if (invoice.detailUrl) return String(invoice.detailUrl);
        if (invoice.uuid) return "/saas/billing-overview/invoices/" + encodeURIComponent(String(invoice.uuid));
        return "#";
    }

    function formatApiError(err) {
        try {
            if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
                return window.ApiErrorHelper.format(err);
            }
        } catch (_e) {}
        var data = err && err.response && err.response.data ? err.response.data : null;
        if (data && data.error && data.error.message) {
            return String(data.error.message);
        }
        return "Request failed.";
    }

    function request(method, path, payload) {
        if (!window.AuthApi || typeof window.AuthApi.request !== "function") {
            var err = new Error("AuthApi missing");
            return Promise.reject(err);
        }
        return window.AuthApi.request(method, path, payload).then(function (res) {
            return res && res.data ? res.data : {};
        });
    }

    function loadWithAuthCheck(callback, errorBox) {
        if (!window.AuthApi || typeof window.AuthApi.request !== "function") {
            window.setTimeout(function () {
                loadWithAuthCheck(callback, errorBox);
            }, 100);
            return;
        }

        var token = null;
        try {
            token = window.localStorage.getItem(window.AuthApi.tokenKey || "arcav_access_token");
        } catch (_e) {}

        callback();
    }

    function initOverviewPage() {
        var root = qs("[data-saas-billing-overview-page]");
        if (!root) return false;

        var inputSearch = qs("[data-billing-search]", root);
        var selectTab = qs("[data-billing-tab]", root);
        var selectPerPage = qs("[data-billing-per-page]", root);
        var btnRefresh = qs("[data-billing-refresh]", root);
        var btnPrev = qs("[data-billing-prev]", root);
        var btnNext = qs("[data-billing-next]", root);
        var tbody = qs("[data-billing-tbody]", root);
        var errorBox = qs("[data-billing-error]", root);
        var pageInfo = qs("[data-billing-pagination-info]", root);

        var state = {
            page: 1,
            last_page: 1,
            rowsByInvoiceUuid: {},
        };

        function renderRows(rows) {
            if (!tbody) return;
            state.rowsByInvoiceUuid = {};

            if (!rows || !rows.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No data</td></tr>';
                return;
            }

            tbody.innerHTML = rows
                .map(function (row) {
                    var c = row.company || {};
                    var s = row.subscription || {};
                    var inv = row.latestInvoice || null;
                    var email = row.email || {};
                    var stateBadges = row.stateBadges || [];

                    if (inv && inv.uuid) {
                        state.rowsByInvoiceUuid[String(inv.uuid)] = row;
                    }

                    var companyHtml =
                        '<div class="fw-semibold">' +
                        escapeHtml(c.name || "—") +
                        "</div>" +
                        '<div class="text-muted small">' +
                        escapeHtml(c.code || "") +
                        "</div>";

                    var subHtml =
                        statusBadgeSubscription(s.status) +
                        '<div class="text-muted small mt-1">' +
                        escapeHtml(s.planCode || "") +
                        (s.billingCycle ? " - " + escapeHtml(s.billingCycle) : "") +
                        "</div>" +
                        renderStateBadges(stateBadges);

                    var invHtml = inv
                        ? '<div class="fw-semibold">' +
                          escapeHtml(inv.invoiceNumber || ("Invoice #" + inv.id)) +
                          "</div>" +
                          '<div class="text-muted small">' +
                          "Due: " +
                          escapeHtml(inv.dueDate || "—") +
                          " • " +
                          "Status: " +
                          escapeHtml(inv.status || "—") +
                          "</div>"
                        : '<div class="text-muted">—</div>';

                    var emailHtml =
                        emailBadge(email.status) +
                        (email.sentAt
                            ? '<div class="text-muted small mt-1">' + escapeHtml(email.sentAt) + "</div>"
                            : "") +
                        (email.lastError
                            ? '<div class="text-muted small mt-1">' + escapeHtml(email.lastError) + "</div>"
                            : "");

                    var actions = inv
                        ? '<div class="btn-group btn-group-sm">' +
                          '<a class="btn btn-outline-secondary" href="' +
                          escapeHtml(buildInvoiceDetailUrl(inv)) +
                          '"><i class="ti ti-eye"></i> Detail invoice</a>' +
                          '<button class="btn btn-outline-primary" data-action="resend" data-invoice-uuid="' +
                          escapeHtml(inv.uuid) +
                          '"><i class="ti ti-mail-forward"></i> Resend</button>' +
                          '</div>'
                        : '<span class="text-muted small">-</span>';

                    return (
                        "<tr>" +
                        "<td>" +
                        companyHtml +
                        "</td>" +
                        "<td>" +
                        subHtml +
                        "</td>" +
                        "<td>" +
                        invHtml +
                        "</td>" +
                        "<td>" +
                        emailHtml +
                        "</td>" +
                        '<td class="text-end">' +
                        actions +
                        "</td>" +
                        "</tr>"
                    );
                })
                .join("");
        }

        function load() {
            hide(errorBox);
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>';
            }

            var params = {
                tab: selectTab ? selectTab.value : "trial",
                search: inputSearch ? String(inputSearch.value || "").trim() : "",
                page: state.page,
                per_page: selectPerPage ? Number(selectPerPage.value || 15) : 15,
            };

            return request("get", "/saas/companies/billing-overview", params)
                .then(function (data) {
                    if (!data.success) {
                        throw { response: { data: data, status: 200 } };
                    }
                    renderRows(data.data || []);
                    var p = data.pagination || {};
                    state.last_page = Number(p.last_page || 1);
                    setText(
                        pageInfo,
                        "Page " +
                            String(p.current_page || state.page) +
                            " / " +
                            String(p.last_page || state.last_page) +
                            " - Total " +
                            String(p.total || 0)
                    );
                })
                .catch(function (err) {
                    show(errorBox);
                    setText(errorBox, formatApiError(err));
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Failed to load</td></tr>';
                    }
                });
        }

        function resend(invoiceUuid) {
            hide(errorBox);
            return request("post", "/saas/invoices/" + String(invoiceUuid) + "/send-email", {})
                .then(function (data) {
                    if (!data.success) {
                        throw { response: { data: data, status: 200 } };
                    }
                    return load();
                })
                .catch(function (err) {
                    show(errorBox);
                    setText(errorBox, formatApiError(err));
                });
        }

        if (btnRefresh) {
            btnRefresh.addEventListener("click", function () {
                state.page = 1;
                load();
            });
        }
        if (selectTab) {
            selectTab.addEventListener("change", function () {
                state.page = 1;
                load();
            });
        }
        if (selectPerPage) {
            selectPerPage.addEventListener("change", function () {
                state.page = 1;
                load();
            });
        }
        if (inputSearch) {
            var t = null;
            inputSearch.addEventListener("input", function () {
                if (t) window.clearTimeout(t);
                t = window.setTimeout(function () {
                    state.page = 1;
                    load();
                }, 350);
            });
        }
        if (btnPrev) {
            btnPrev.addEventListener("click", function () {
                if (state.page <= 1) return;
                state.page -= 1;
                load();
            });
        }
        if (btnNext) {
            btnNext.addEventListener("click", function () {
                if (state.page >= state.last_page) return;
                state.page += 1;
                load();
            });
        }

        if (tbody) {
            tbody.addEventListener("click", function (e) {
                var target = e && e.target ? e.target : null;
                var btn = target && target.closest ? target.closest("button[data-action]") : null;
                if (!btn) return;
                var action = btn.getAttribute("data-action");
                if (action !== "resend") return;
                var invoiceUuid = btn.getAttribute("data-invoice-uuid");
                if (!invoiceUuid) return;
                resend(invoiceUuid);
            });
        }

        loadWithAuthCheck(function () {
            load();
        }, errorBox);

        return true;
    }

    function initDetailPage() {
        var root = qs("[data-saas-billing-invoice-detail-page]");
        if (!root) return false;

        var invoiceUuid = root.getAttribute("data-invoice-uuid");
        var errorBox = qs("[data-billing-detail-error]", root);
        var pageTitle = qs("[data-billing-detail-title]", root);
        var companyName = qs("[data-billing-detail-company-name]", root);
        var companyCode = qs("[data-billing-detail-company-code]", root);
        var subscriptionStatus = qs("[data-billing-detail-subscription-status]", root);
        var subscriptionPlan = qs("[data-billing-detail-subscription-plan]", root);
        var subscriptionPeriod = qs("[data-billing-detail-subscription-period]", root);
        var invoiceStatus = qs("[data-billing-detail-invoice-status]", root);
        var invoiceNumber = qs("[data-billing-detail-invoice-number]", root);
        var invoiceDueDate = qs("[data-billing-detail-invoice-due-date]", root);
        var invoiceAmount = qs("[data-billing-detail-invoice-amount]", root);
        var latestEmailStatus = qs("[data-billing-detail-latest-email-status]", root);
        var latestEmailTarget = qs("[data-billing-detail-latest-email-target]", root);
        var latestEmailSentAt = qs("[data-billing-detail-latest-email-sent-at]", root);
        var latestEmailError = qs("[data-billing-detail-latest-email-error]", root);
        var stateBadges = qs("[data-billing-detail-state-badges]", root);
        var historyBody = qs("[data-billing-email-history-body]", root);
        var resendButton = qs("[data-billing-detail-resend]", root);

        function renderStateSummary(data) {
            var badges = [];
            var subscription = data.subscription || null;

            if (subscription && subscription.status === "pending_payment" && data.isPaid) {
                badges.push({ label: "State Mismatch", kind: "warning" });
            }

            if (stateBadges) {
                stateBadges.innerHTML = renderStateBadges(badges);
            }
        }

        function renderHistory(emailLogs) {
            if (!historyBody) return;

            if (!Array.isArray(emailLogs) || !emailLogs.length) {
                historyBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Belum ada riwayat email.</td></tr>';
                return;
            }

            historyBody.innerHTML = emailLogs
                .map(function (emailLog) {
                    return (
                        "<tr>" +
                        "<td>" + escapeHtml(emailLog.toEmail || "-") + "</td>" +
                        "<td>" + emailBadge(emailLog.status) + "</td>" +
                        "<td>" + escapeHtml(emailLog.createdAt || "-") + "</td>" +
                        "<td>" + escapeHtml(emailLog.errorMessage || "-") + "</td>" +
                        "</tr>"
                    );
                })
                .join("");
        }

        function renderDetail(data) {
            var company = data.company || {};
            var subscription = data.subscription || {};
            var latestEmail = data.latestEmail || null;

            setText(pageTitle, data.invoiceNumber || "Detail Invoice");
            setText(companyName, company.name || data.companyName || "-");
            setText(companyCode, company.code || "-");
            setText(subscriptionStatus, String(subscription.status || "-").toUpperCase());
            setText(subscriptionPlan, (subscription.planCode || "-") + (subscription.packageName ? " - " + String(subscription.packageName) : ""));
            setText(subscriptionPeriod, "Start: " + String(subscription.startsAt || "-") + " - End: " + String(subscription.endsAt || "-"));
            setText(invoiceStatus, String(data.status || "-").toUpperCase() + (data.isPaid ? " - PAID" : " - UNPAID"));
            setText(invoiceNumber, data.invoiceNumber || "-");
            setText(invoiceDueDate, data.dueDate || "-");
            setText(invoiceAmount, String(data.amountDue != null ? data.amountDue : "-"));
            setText(latestEmailStatus, latestEmail ? String(latestEmail.status || "-").toUpperCase() : "BELUM ADA");
            setText(latestEmailTarget, latestEmail ? String(latestEmail.toEmail || "-") : "-");
            setText(latestEmailSentAt, latestEmail ? String(latestEmail.createdAt || "-") : "-");
            setText(latestEmailError, latestEmail && latestEmail.errorMessage ? String(latestEmail.errorMessage) : "-");

            renderStateSummary(data);
            renderHistory(data.emailLogs || []);
        }

        function loadDetail() {
            hide(errorBox);
            return request("get", "/saas/invoices/" + String(invoiceUuid))
                .then(function (data) {
                    if (!data.success) {
                        throw { response: { data: data, status: 200 } };
                    }
                    renderDetail(data.data || {});
                })
                .catch(function (err) {
                    if (errorBox) {
                        show(errorBox);
                        setText(errorBox, formatApiError(err));
                    }
                    if (historyBody) {
                        historyBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Gagal memuat detail invoice.</td></tr>';
                    }
                });
        }

        if (resendButton) {
            resendButton.addEventListener("click", function () {
                hide(errorBox);
                request("post", "/saas/invoices/" + String(invoiceUuid) + "/send-email", {})
                    .then(function (data) {
                        if (!data.success) {
                            throw { response: { data: data, status: 200 } };
                        }
                        return loadDetail();
                    })
                    .catch(function (err) {
                        if (errorBox) {
                            show(errorBox);
                            setText(errorBox, formatApiError(err));
                        }
                    });
            });
        }

        loadWithAuthCheck(function () {
            loadDetail();
        }, errorBox);

        return true;
    }

    function init() {
        var hasOverview = initOverviewPage();
        var hasDetail = initDetailPage();
        return hasOverview || hasDetail;
    }

    window.SaaSBillingOverview = {
        init: init,
    };

    if (!window.__ARCAV_DISABLE_AUTOINIT__) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", init);
        } else {
            init();
        }
    }
})(window, document);

