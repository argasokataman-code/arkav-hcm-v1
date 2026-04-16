(function (window, document) {
    "use strict";

    var root = document.querySelector("[data-company-invoices-page]");
    if (!root) return;

    var listContainer = document.querySelector("[data-company-invoices-list-container]");
    var modalEl = document.querySelector("[data-company-invoice-modal]");
    var modal = null;
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
                              <a class="btn btn-sm btn-white" href="/v1/hcm/billing/invoices/${esc(r.id)}/download" target="_blank" rel="noopener noreferrer" title="Download PDF">
                                <i class="ti ti-download"></i>
                              </a>
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
    }

    function fillModal(inv) {
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
            var payBtn = e.target.closest("[data-invoice-mock-pay]");
            if (payBtn) {
                var id2 = payBtn.getAttribute("data-invoice-mock-pay");
                api("post", "/hcm/billing/invoices/" + encodeURIComponent(id2) + "/mock-pay").then(function () {
                    load();
                });
                return;
            }
        });
    }

    function load() {
        if (listContainer) {
            listContainer.innerHTML = '<div class="card"><div class="card-body text-center text-muted py-4"><i class="ti ti-loader-quarter fs-1 spin"></i> Loading invoices...</div></div>';
        }
        api("get", "/hcm/billing/invoices?perPage=50").then(function (payload) {
            if (!payload || payload.success !== true) {
                renderTable([]);
                return;
            }
            renderTable(payload.data || []);
        });
    }

    bindActions();
    load();
})(window, document);

