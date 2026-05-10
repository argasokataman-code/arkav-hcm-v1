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

    function formatDate(dateValue) {
        if (!dateValue) return "-";
        var date = new Date(dateValue);
        if (Number.isNaN(date.getTime())) return String(dateValue);
        return new Intl.DateTimeFormat("id-ID", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            timeZone: "Asia/Jakarta",
        }).format(date);
    }

    function formatDateTime(dateValue) {
        if (!dateValue) return "-";
        var date = new Date(dateValue);
        if (Number.isNaN(date.getTime())) return String(dateValue);
        return (
            new Intl.DateTimeFormat("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
                hour12: false,
                timeZone: "Asia/Jakarta",
            }).format(date) + " WIB"
        );
    }

    function formatMoney(amount) {
        if (amount == null || amount === "") return "-";
        var number = Number(amount);
        if (Number.isNaN(number)) return String(amount);
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(number);
    }

    function normalizeLabel(value) {
        return String(value || "")
            .replace(/_/g, " ")
            .replace(/\b\w/g, function (s) {
                return s.toUpperCase();
            });
    }

    function statusBadgeSubscription(status) {
        var s = String(status || "");
        if (s === "active") return badge("Aktif", "success");
        if (s === "trial") return badge("Trial", "info");
        if (s === "pending_payment") return badge("Menunggu Pembayaran", "warning");
        if (s === "suspended") return badge("Ditangguhkan", "danger");
        if (s === "expired") return badge("Berakhir", "danger");
        if (s === "inactive") return badge("Tidak Aktif", "secondary");
        if (s === "cancelled") return badge("Dibatalkan", "secondary");
        return badge(normalizeLabel(s) || "-", "secondary");
    }

    function emailBadge(status) {
        var s = String(status || "");
        if (s === "sent") return badge("Terkirim", "success");
        if (s === "failed") return badge("Gagal", "danger");
        if (s === "no_invoice") return badge("Belum Ada Invoice", "warning");
        return badge("Belum Dikirim", "secondary");
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

    function cancellationReasonLabel(code) {
        var reason = String(code || "").toLowerCase();
        if (reason === "trial_expired") return "Trial Expired";
        if (reason === "payment_overdue") return "Payment Overdue";
        if (reason === "tenant_request") return "Permintaan Tenant";
        if (reason === "system_webhook") return "Sinkronisasi Gateway";
        if (reason === "seeded_demo_state") return "Data Demo Seeded";
        if (reason === "manual_stop") return "Manual Stop";
        return "Alasan Tidak Tercatat";
    }

    function buildInvoiceDetailUrl(invoice) {
        if (!invoice) return "#";
        if (invoice.detailUrl) return String(invoice.detailUrl);
        if (invoice.uuid) return "/saas/billing-overview/invoices/" + encodeURIComponent(String(invoice.uuid));
        return "#";
    }

    function buildInvoicePdfPreviewUrl(invoice) {
        if (!invoice || !invoice.uuid) return "#";
        return "/v1/saas/invoices/" + encodeURIComponent(String(invoice.uuid)) + "/pdf/preview";
    }

    function buildInvoicePdfDownloadUrl(invoice) {
        if (!invoice || !invoice.uuid) return "#";
        return "/v1/saas/invoices/" + encodeURIComponent(String(invoice.uuid)) + "/pdf";
    }

    function renderCancellationMeta(subscription) {
        if (!subscription || String(subscription.status || "").toLowerCase() !== "cancelled") {
            return "";
        }

        var reasonLabel = cancellationReasonLabel(subscription.cancellationReason);
        var cancelledAt = subscription.cancelledAt ? formatDateTime(subscription.cancelledAt) : "-";
        var description = subscription.cancellationDescription || "Tidak ada detail alasan pembatalan.";

        return (
            '<div class="text-muted small mt-1">Dibatalkan: ' +
            escapeHtml(cancelledAt) +
            ' • ' +
            escapeHtml(reasonLabel) +
            '</div><div class="text-muted small">' +
            escapeHtml(description) +
            "</div>"
        );
    }

    function buildInvoiceListUrl(row) {
        var code = row && row.company && row.company.code ? String(row.company.code) : "";
        if (!code) return "/saas/invoices";
        return "/saas/invoices?search=" + encodeURIComponent(code);
    }

    function buildEmailFollowUpAction(emailStatus, subscriptionStatus, invoiceUuid) {
        var normalizedEmailStatus = String(emailStatus || "").toLowerCase();
        var normalizedSubscriptionStatus = String(subscriptionStatus || "").toLowerCase();

        if (normalizedSubscriptionStatus === "pending_payment") {
            return '<a class="btn btn-outline-warning btn-sm" href="/saas/reminders"><i class="ti ti-bell"></i> Kirim Reminder</a>';
        }

        if (normalizedEmailStatus === "not_sent") {
            return '<button class="btn btn-outline-primary btn-sm" data-action="resend" data-invoice-uuid="' +
                escapeHtml(invoiceUuid) +
                '"><i class="ti ti-mail-forward"></i> Kirim Ulang</button>';
        }

        return "";
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
        var tabButtons = Array.prototype.slice.call(root.querySelectorAll("[data-billing-tab-button]"));
        var selectPerPage = qs("[data-billing-per-page]", root);
        var btnRefresh = qs("[data-billing-refresh]", root);
        var btnReset = qs("[data-billing-reset]", root);
        var btnPrev = qs("[data-billing-prev]", root);
        var btnNext = qs("[data-billing-next]", root);
        var tbody = qs("[data-billing-tbody]", root);
        var mobileList = qs("[data-billing-mobile-list]", root);
        var errorBox = qs("[data-billing-error]", root);
        var pageInfo = qs("[data-billing-pagination-info]", root);

        var state = {
            page: 1,
            last_page: 1,
            rowsByInvoiceUuid: {},
        };

        function renderEmptyState(message) {
            var text = message || "Tidak ada data untuk filter ini.";
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">' + escapeHtml(text) + "</td></tr>";
            }
            if (mobileList) {
                mobileList.innerHTML = '<div class="text-center text-muted py-4">' + escapeHtml(text) + "</div>";
            }
        }

        function updatePaginationButtons() {
            if (btnPrev) btnPrev.disabled = state.page <= 1;
            if (btnNext) btnNext.disabled = state.page >= state.last_page;
        }

        function renderMobileRows(rows) {
            if (!mobileList) return;

            if (!rows || !rows.length) {
                mobileList.innerHTML = '<div class="text-center text-muted py-4">Tidak ada data untuk filter ini.</div>';
                return;
            }

            mobileList.innerHTML = rows
                .map(function (row) {
                    var c = row.company || {};
                    var s = row.subscription || {};
                    var inv = row.latestInvoice || null;
                    var email = row.email || {};
                    var stateBadges = row.stateBadges || [];
                    var hasInvoice = !!(inv && inv.uuid);
                                        var followUpAction = buildEmailFollowUpAction(email.status, s.status, inv && inv.uuid ? String(inv.uuid) : "");

                    var actionHtml = hasInvoice
                        ? '<div class="d-flex flex-wrap gap-2 mt-2">' +
                          '<a class="btn btn-outline-secondary btn-sm" href="' +
                          escapeHtml(buildInvoiceDetailUrl(inv)) +
                          '"><i class="ti ti-eye"></i> Detail</a>' +
                                                    followUpAction +
                          '<a class="btn btn-outline-dark btn-sm" href="' +
                                                    escapeHtml(buildInvoicePdfPreviewUrl(inv)) +
                                                    '" target="_blank" rel="noopener noreferrer"><i class="ti ti-file-search"></i> View PDF</a>' +
                                                    '<a class="btn btn-outline-dark btn-sm" href="' +
                                                    escapeHtml(buildInvoicePdfDownloadUrl(inv)) +
                                                    '"><i class="ti ti-file-download"></i> Download PDF</a>' +
                          "</div>"
                        : '<a class="btn btn-outline-secondary btn-sm mt-2" href="' +
                          escapeHtml(buildInvoiceListUrl(row)) +
                          '"><i class="ti ti-list"></i> Lihat Daftar Invoice</a>';

                    return (
                        '<div class="border rounded-2 p-3 mb-2">' +
                        '<div class="d-flex justify-content-between align-items-start gap-2">' +
                        '<div><div class="fw-semibold">' +
                        escapeHtml(c.name || "-") +
                        '</div><div class="text-muted small">' +
                        escapeHtml(c.code || "") +
                        "</div></div>" +
                        statusBadgeSubscription(s.status) +
                        "</div>" +
                        '<div class="text-muted small mt-2">Paket: ' +
                        escapeHtml(s.planCode || "-") +
                        (s.billingCycle ? " • " + escapeHtml(normalizeLabel(s.billingCycle)) : "") +
                        "</div>" +
                        renderCancellationMeta(s) +
                        renderStateBadges(stateBadges) +
                        '<div class="mt-2"><div class="small text-uppercase text-muted">Invoice Terbaru</div>' +
                        (inv
                            ? '<div class="fw-semibold">' +
                              escapeHtml(inv.invoiceNumber || "Invoice #" + inv.id) +
                                                            '</div><div class="text-muted small">Periode paket: ' +
                                                            escapeHtml(formatDate(s.startsAt)) +
                                                            " - " +
                                                            escapeHtml(formatDate(s.endsAt)) +
                                                            '</div><div class="text-muted small">Jumlah: ' +
                              escapeHtml(formatMoney(inv.amountDue)) +
                                                            "</div>" +
                                                            (String(inv.status || "").toLowerCase() === "paid"
                                                                    ? ""
                                                                    : '<div class="text-muted small">Jatuh tempo invoice: ' +
                                                                        escapeHtml(formatDate(inv.dueDate)) +
                                                                        "</div>")
                            : '<div class="text-muted">Belum ada invoice terkait.</div>') +
                        "</div>" +
                        '<div class="mt-2"><div class="small text-uppercase text-muted">Email</div>' +
                        emailBadge(email.status) +
                        '<div class="text-muted small mt-1">Terakhir: ' +
                        escapeHtml(formatDateTime(email.sentAt)) +
                        "</div>" +
                        (email.lastError ? '<div class="text-danger small mt-1">' + escapeHtml(email.lastError) + "</div>" : "") +
                        "</div>" +
                        actionHtml +
                        "</div>"
                    );
                })
                .join("");
        }

        function renderRows(rows) {
            state.rowsByInvoiceUuid = {};

            if (!rows || !rows.length) {
                renderEmptyState("Tidak ada data untuk filter ini.");
                return;
            }

            renderMobileRows(rows);

            if (!tbody) return;
            tbody.innerHTML = rows
                .map(function (row) {
                    var c = row.company || {};
                    var s = row.subscription || {};
                    var inv = row.latestInvoice || null;
                    var email = row.email || {};
                    var stateBadges = row.stateBadges || [];
                    var followUpAction = buildEmailFollowUpAction(email.status, s.status, inv && inv.uuid ? String(inv.uuid) : "");

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
                                                (s.billingCycle ? " • " + escapeHtml(normalizeLabel(s.billingCycle)) : "") +
                        "</div>" +
                        renderCancellationMeta(s) +
                        renderStateBadges(stateBadges);

                    var invHtml = inv
                        ? '<div class="fw-semibold">' +
                          escapeHtml(inv.invoiceNumber || ("Invoice #" + inv.id)) +
                          "</div>" +
                                                    '<div class="text-muted small">Periode paket: ' +
                                                    escapeHtml(formatDate(s.startsAt)) +
                                                    " - " +
                                                    escapeHtml(formatDate(s.endsAt)) +
                                                    '</div><div class="text-muted small">Jumlah: ' +
                                                    escapeHtml(formatMoney(inv.amountDue)) +
                                                    "</div>" +
                                                    (String(inv.status || "").toLowerCase() === "paid"
                                                            ? ""
                                                            : '<div class="text-muted small">Jatuh tempo invoice: ' +
                                                                escapeHtml(formatDate(inv.dueDate)) +
                                                                "</div>") +
                                                    '<div class="text-muted small">Status: ' +
                                                    escapeHtml(normalizeLabel(inv.status || "-")) +
                          "</div>"
                                                : '<div class="text-muted">Belum ada invoice terkait.</div>';

                    var emailHtml =
                        emailBadge(email.status) +
                                                '<div class="text-muted small mt-1">Terakhir: ' + escapeHtml(formatDateTime(email.sentAt)) + "</div>" +
                        (email.lastError
                                                        ? '<div class="text-danger small mt-1">' + escapeHtml(email.lastError) + "</div>"
                            : "");

                                        var hasInvoice = !!(inv && inv.uuid);
                                        var actions = hasInvoice
                                                ? '<div class="d-inline-flex flex-wrap gap-1 justify-content-end">' +
                                                    '<a class="btn btn-outline-secondary btn-sm" href="' +
                                                    escapeHtml(buildInvoiceDetailUrl(inv)) +
                                                    '"><i class="ti ti-eye"></i> Detail</a>' +
                                                    followUpAction +
                                                    '<a class="btn btn-outline-dark btn-sm" href="' +
                                                    escapeHtml(buildInvoicePdfPreviewUrl(inv)) +
                                                    '" target="_blank" rel="noopener noreferrer"><i class="ti ti-file-search"></i> View PDF</a>' +
                                                    '<a class="btn btn-outline-dark btn-sm" href="' +
                                                    escapeHtml(buildInvoicePdfDownloadUrl(inv)) +
                                                    '"><i class="ti ti-file-download"></i> Download PDF</a>' +
                                                    "</div>"
                                                : '<a class="btn btn-outline-secondary btn-sm" href="' +
                                                    escapeHtml(buildInvoiceListUrl(row)) +
                                                    '"><i class="ti ti-list"></i> Lihat Daftar Invoice</a>';

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
            if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Memuat data...</td></tr>';
            if (mobileList) mobileList.innerHTML = '<div class="text-center text-muted py-4">Memuat data...</div>';

            var params = {
                tab: selectTab ? selectTab.value : "subscribed",
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
                    state.page = Number(p.current_page || state.page);
                    updatePaginationButtons();
                    setText(
                        pageInfo,
                        "Halaman " +
                            String(p.current_page || state.page) +
                            " dari " +
                            String(p.last_page || state.last_page) +
                            " • Total " +
                            String(p.total || 0) +
                            " company"
                    );
                })
                .catch(function (err) {
                    show(errorBox);
                    setText(errorBox, formatApiError(err));
                    renderEmptyState("Gagal memuat data.");
                    updatePaginationButtons();
                });
        }

        function setActiveTab(nextTab) {
            if (!selectTab) return;
            selectTab.value = nextTab;
            tabButtons.forEach(function (button) {
                var isActive = button.getAttribute("data-tab-value") === nextTab;
                button.classList.toggle("active", isActive);
                button.classList.toggle("btn-secondary", isActive);
                button.classList.toggle("btn-outline-secondary", !isActive);
            });
        }

        function resetFilters() {
            if (inputSearch) inputSearch.value = "";
            if (selectPerPage) selectPerPage.value = "15";
            setActiveTab("subscribed");
            state.page = 1;
            load();
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
        tabButtons.forEach(function (button) {
            button.addEventListener("click", function () {
                var tabValue = button.getAttribute("data-tab-value");
                if (!tabValue || tabValue === selectTab.value) return;
                setActiveTab(tabValue);
                state.page = 1;
                load();
            });
        });
        if (selectPerPage) {
            selectPerPage.addEventListener("change", function () {
                state.page = 1;
                load();
            });
        }
        if (btnReset) {
            btnReset.addEventListener("click", function () {
                resetFilters();
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

        updatePaginationButtons();
        setActiveTab(selectTab ? selectTab.value : "subscribed");

        if (tbody) {
            tbody.addEventListener("click", function (e) {
                var target = e && e.target ? e.target : null;
                var btn = target && target.closest ? target.closest("button[data-action]") : null;
                if (!btn) return;
                var action = btn.getAttribute("data-action");
                var invoiceUuid = btn.getAttribute("data-invoice-uuid");
                if (!invoiceUuid) return;
                if (action === "resend") {
                    resend(invoiceUuid);
                    return;
                }
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
            var badges = Array.isArray(data.stateBadges) ? data.stateBadges.slice() : [];
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
                        "<td>" + escapeHtml(formatDateTime(emailLog.createdAt)) + "</td>" +
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
            var pendingPaymentOnly = String(subscription.status || "").toLowerCase() === "pending_payment";

            setText(pageTitle, data.invoiceNumber || "Detail Invoice");
            setText(companyName, company.name || data.companyName || "-");
            setText(companyCode, company.code || "-");
            setText(subscriptionStatus, normalizeLabel(subscription.status || "-"));
            setText(subscriptionPlan, (subscription.planCode || "-") + (subscription.packageName ? " • " + String(subscription.packageName) : ""));
            var cancellationSuffix = "";
            if (String(subscription.status || "").toLowerCase() === "cancelled") {
                var cancelledAt = subscription.cancelledAt ? formatDateTime(subscription.cancelledAt) : "-";
                var reason = cancellationReasonLabel(subscription.cancellationReason);
                cancellationSuffix = " • Dibatalkan: " + cancelledAt + " (" + reason + ")";
            }
            setText(subscriptionPeriod, "Periode: " + formatDate(subscription.startsAt) + " - " + formatDate(subscription.endsAt) + cancellationSuffix);
            setText(invoiceStatus, normalizeLabel(data.status || "-") + (data.isPaid ? " • Lunas" : " • Belum Lunas"));
            setText(invoiceNumber, data.invoiceNumber || "-");
            setText(invoiceDueDate, formatDate(data.dueDate));
            setText(invoiceAmount, formatMoney(data.amountDue));
            setText(latestEmailStatus, latestEmail ? normalizeLabel(latestEmail.status || "-") : "Belum Ada");
            setText(latestEmailTarget, latestEmail ? String(latestEmail.toEmail || "-") : "-");
            setText(latestEmailSentAt, latestEmail ? formatDateTime(latestEmail.createdAt) : "-");
            setText(latestEmailError, latestEmail && latestEmail.errorMessage ? String(latestEmail.errorMessage) : "-");

            if (resendButton) {
                resendButton.disabled = pendingPaymentOnly;
                resendButton.title = pendingPaymentOnly ? "Gunakan Payment Reminder untuk tenant pending payment." : "";
            }

            if (errorBox) {
                if (pendingPaymentOnly) {
                    show(errorBox);
                    setText(errorBox, "Tenant masih pending payment. Gunakan menu Payment Reminders.");
                } else {
                    hide(errorBox);
                    setText(errorBox, "");
                }
            }

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
                if (resendButton.disabled) {
                    if (errorBox) {
                        show(errorBox);
                        setText(errorBox, "Tenant masih pending payment. Gunakan menu Payment Reminders.");
                    }
                    return;
                }
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

