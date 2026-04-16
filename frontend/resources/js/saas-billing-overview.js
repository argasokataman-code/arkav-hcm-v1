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

    function init() {
        var root = qs("[data-saas-billing-overview-page]");
        if (!root) return;

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
        };

        function request(method, path, payload) {
            if (!window.ApiClient || typeof window.ApiClient.request !== "function") {
                var err = new Error("ApiClient missing");
                return Promise.reject(err);
            }
            return window.ApiClient.request(method, path, payload).then(function (res) {
                return res && res.data ? res.data : {};
            });
        }

        function renderRows(rows) {
            if (!tbody) return;

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
                        (s.billingCycle ? " • " + escapeHtml(s.billingCycle) : "") +
                        "</div>";

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
                        ? '<button class="btn btn-sm btn-outline-primary" data-action="resend" data-invoice-id="' +
                          escapeHtml(inv.id) +
                          '"><i class="ti ti-mail-forward"></i> Resend</button>'
                        : '<span class="text-muted small">—</span>';

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
                            " • Total " +
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

        function resend(invoiceId) {
            hide(errorBox);
            return request("post", "/saas/invoices/" + String(invoiceId) + "/send-email", {})
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
                var invoiceId = btn.getAttribute("data-invoice-id");
                if (!invoiceId) return;
                resend(invoiceId);
            });
        }

        load();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);

