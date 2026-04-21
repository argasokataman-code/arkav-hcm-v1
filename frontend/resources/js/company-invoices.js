(function (window, document) {
    "use strict";

    var root = document.querySelector("[data-company-invoices-page]");
    if (!root) return;

    var listContainer = document.querySelector("[data-company-invoices-list-container]");
    var feedback = document.querySelector("[data-company-invoices-feedback]");
    var modalEl = document.querySelector("[data-company-invoice-modal]");
    var searchInput = document.getElementById("search_invoices");
    var statusFilter = document.getElementById("filter_invoice_status");
    var paidFilter = document.getElementById("filter_invoice_paid");
    var resetFiltersBtn = document.getElementById("btn_reset_invoice_filters");
    var totalDueNode = document.getElementById("total_due");
    var countUnpaidNode = document.getElementById("count_unpaid");
    var countOverdueNode = document.getElementById("count_overdue");
    var paidThisMonthNode = document.getElementById("paid_this_month");
    var downloadBtn = document.querySelector("[data-company-invoice-download]");
    var modal = null;
    var currentInvoice = null;
    var searchTimer = null;
    try {
        if (modalEl && window.bootstrap) {
            modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        }
    } catch (_e) {}

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function fmtMoney(v) {
        var n = Number(v || 0);
        try {
            return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(n);
        } catch (_e) {
            return "Rp " + String(n);
        }
    }

    function badge(status) {
        var s = String(status || "").toLowerCase();
        var map = { draft: "secondary", sent: "info", viewed: "info", paid: "success", expired: "danger" };
        return '<span class="badge bg-' + (map[s] || "secondary") + '">' + esc(s || "-") + "</span>";
    }

    function showFeedback(message) {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.classList.remove("d-none");
    }

    function clearFeedback() {
        if (!feedback) return;
        feedback.textContent = "";
        feedback.classList.add("d-none");
    }

    function parseError(err) {
        if (err && err.response && err.response.data && err.response.data.error && err.response.data.error.message) {
            return String(err.response.data.error.message);
        }
        if (err && err.data && err.data.error && err.data.error.message) {
            return String(err.data.error.message);
        }
        if (err && err.message) {
            return String(err.message);
        }
        return "Gagal memuat invoice company.";
    }

    function isSameMonth(dateValue) {
        if (!dateValue) return false;
        var date = new Date(dateValue);
        var now = new Date();
        return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
    }

    function isPastDue(dateValue) {
        if (!dateValue) return false;
        return new Date(dateValue) < new Date();
    }

    function applySummary(rows) {
        var items = Array.isArray(rows) ? rows : [];
        var totalDue = 0;
        var unpaidCount = 0;
        var overdueCount = 0;
        var paidThisMonth = 0;

        items.forEach(function (row) {
            var amount = Number(row && row.amountDue ? row.amountDue : 0);
            if (!row || row.isPaid) {
                if (row && row.isPaid && isSameMonth(row.paidDate || row.updatedAt)) {
                    paidThisMonth += amount;
                }
                return;
            }

            totalDue += amount;
            unpaidCount += 1;
            if (isPastDue(row.dueDate)) {
                overdueCount += 1;
            }
        });

        if (totalDueNode) totalDueNode.textContent = fmtMoney(totalDue);
        if (countUnpaidNode) countUnpaidNode.textContent = String(unpaidCount);
        if (countOverdueNode) countOverdueNode.textContent = String(overdueCount);
        if (paidThisMonthNode) paidThisMonthNode.textContent = fmtMoney(paidThisMonth);
    }

    function renderState(message) {
        if (!listContainer) return;
        listContainer.innerHTML = '<div class="card"><div class="card-body text-center text-muted py-4">' + esc(message) + '</div></div>';
    }

    function api(method, path, payload) {
        if (!window.AuthApi || typeof window.AuthApi.request !== "function") {
            return Promise.reject({ status: 0, data: { error: { message: "Auth API not initialized." } } });
        }
        return window.AuthApi.request(method, path, payload).then(function (res) {
            return res && res.data ? res.data : res;
        }).catch(function (err) {
            // AuthApi.request already handles 401/403 modals globally.
            throw err;
        });
    }

    function renderTable(rows) {
        if (!listContainer) return;
        var html = `
          <div class="card">
            <div class="custom-datatable-filter table-responsive">
              <table class="table">
                <thead class="thead-light">
                  <tr>
                    <th>Invoice</th>
                    <th>Issue</th>
                    <th>Due</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Paid</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${(rows || []).map(function (r) {
                      var paid = r.isPaid ? '<span class="badge bg-success">paid</span>' : '<span class="badge bg-warning text-dark">unpaid</span>';
                      return `
                        <tr>
                          <td>
                            <div class="fw-semibold">${esc(r.invoiceNumber || ("INV-" + r.id))}</div>
                            <div class="text-muted small">#${esc(r.id)}</div>
                          </td>
                          <td>${esc(r.issueDate || "-")}</td>
                          <td>${esc(r.dueDate || "-")}</td>
                          <td class="fw-semibold">${esc(fmtMoney(r.amountDue))}</td>
                          <td>${badge(r.status)}</td>
                          <td>${paid}</td>
                          <td class="text-end">
                            <div class="d-inline-flex gap-2">
                              <button class="btn btn-sm btn-white" data-invoice-view="${esc(r.id)}"><i class="ti ti-eye"></i></button>
                                                            <button class="btn btn-sm btn-white" type="button" data-invoice-download="${esc(r.id)}" title="Download PDF">
                                <i class="ti ti-download"></i>
                                                            </button>
                              ${r.isPaid ? "" : `<button class="btn btn-sm btn-primary" data-invoice-mock-pay="${esc(r.id)}">Mock Pay</button>`}
                            </div>
                          </td>
                        </tr>
                      `;
                  }).join("") || '<tr><td colspan="7" class="text-center text-muted py-4">No invoices found.</td></tr>'}
                </tbody>
              </table>
            </div>
          </div>
        `;
        listContainer.innerHTML = html;
        applySummary(rows);
    }

    function fillModal(inv) {
        currentInvoice = inv || null;
        function set(sel, val) {
            var el = document.querySelector(sel);
            if (el) el.textContent = val;
        }
        set("[data-invoice-modal-number]", inv.invoiceNumber || ("INV-" + inv.id));
        var stEl = document.querySelector("[data-invoice-modal-status]");
        if (stEl) stEl.outerHTML = '<span class="badge bg-primary" data-invoice-modal-status>' + esc(String(inv.status || "")) + "</span>";
        set("[data-invoice-modal-company]", inv.company || "-");
        var paidEl = document.querySelector("[data-invoice-modal-payment-status]");
        if (paidEl) paidEl.outerHTML = inv.isPaid
            ? '<span class="badge bg-success" data-invoice-modal-payment-status>Paid</span>'
            : '<span class="badge bg-warning text-dark" data-invoice-modal-payment-status>Unpaid</span>';
        set("[data-invoice-modal-issue-date]", inv.issueDate || "-");
        set("[data-invoice-modal-due-date]", inv.dueDate || "-");
        set("[data-invoice-modal-amount]", fmtMoney(inv.amountDue));
        set("[data-invoice-modal-notes]", inv.notes || "—");
        if (downloadBtn) {
            downloadBtn.disabled = !inv || !inv.id;
        }
    }

    function buildQuery() {
        var query = { perPage: 50 };
        if (searchInput && searchInput.value.trim()) {
            query.search = searchInput.value.trim();
        }
        if (statusFilter && statusFilter.value) {
            query.status = statusFilter.value;
        }
        if (paidFilter && paidFilter.value !== "") {
            query.is_paid = paidFilter.value;
        }
        return query;
    }

    function triggerDownload(inv) {
        if (!inv || !inv.id) return;
        if (window.AuthApi && typeof window.AuthApi.downloadV1Binary === "function") {
            window.AuthApi.downloadV1Binary(
                "/hcm/billing/invoices/" + encodeURIComponent(inv.id) + "/download",
                (inv.invoiceNumber || ("invoice-" + inv.id)) + ".pdf"
            ).catch(function (err) {
                showFeedback(parseError(err));
            });
            return;
        }
        window.open("/v1/hcm/billing/invoices/" + encodeURIComponent(inv.id) + "/download", "_blank", "noopener");
    }

    function bindActions() {
        if (!listContainer) return;
        listContainer.addEventListener("click", function (e) {
            var viewBtn = e.target.closest("[data-invoice-view]");
            if (viewBtn) {
                var id = viewBtn.getAttribute("data-invoice-view");
                api("get", "/hcm/billing/invoices/" + encodeURIComponent(id)).then(function (payload) {
                    if (!payload || payload.success !== true) return;
                    fillModal(payload.data);
                    if (modal) modal.show();
                });
                return;
            }
            var downloadActionBtn = e.target.closest("[data-invoice-download]");
            if (downloadActionBtn) {
                var idDownload = downloadActionBtn.getAttribute("data-invoice-download");
                var invoiceNumber = downloadActionBtn.closest("tr")?.querySelector(".fw-semibold")?.textContent || ("invoice-" + idDownload);
                triggerDownload({ id: idDownload, invoiceNumber: invoiceNumber });
                return;
            }
            var payBtn = e.target.closest("[data-invoice-mock-pay]");
            if (payBtn) {
                var id2 = payBtn.getAttribute("data-invoice-mock-pay");
                api("post", "/hcm/billing/invoices/" + encodeURIComponent(id2) + "/mock-pay").then(function () {
                    load();
                });
                return;
            }
        });

        if (downloadBtn) {
            downloadBtn.addEventListener("click", function () {
                triggerDownload(currentInvoice);
            });
        }

        if (searchInput) {
            searchInput.addEventListener("input", function () {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(load, 250);
            });
        }
        if (statusFilter) {
            statusFilter.addEventListener("change", load);
        }
        if (paidFilter) {
            paidFilter.addEventListener("change", load);
        }
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener("click", function () {
                if (searchInput) searchInput.value = "";
                if (statusFilter) statusFilter.value = "";
                if (paidFilter) paidFilter.value = "";
                load();
            });
        }
    }

    function load() {
        clearFeedback();
        if (listContainer) {
            listContainer.innerHTML = '<div class="card"><div class="card-body text-center text-muted py-4"><i class="ti ti-loader-quarter fs-1 spin"></i> Loading invoices...</div></div>';
        }
        api("get", "/hcm/billing/invoices", buildQuery()).then(function (payload) {
            if (!payload || payload.success !== true) {
                renderTable([]);
                return;
            }
            renderTable(payload.data || []);
        }).catch(function (err) {
            renderState("Invoice belum tersedia atau gagal dimuat.");
            applySummary([]);
            showFeedback(parseError(err));
        });
    }

    bindActions();
    load();
})(window, document);

