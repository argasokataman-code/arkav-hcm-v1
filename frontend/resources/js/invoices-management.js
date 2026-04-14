(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/invoices";
  const PAGE_SIZE = 10;
  let apiToken = null;

  /**
   * Fetch API token from /api-token endpoint
   */
  function getApiToken() {
    if (apiToken) {
      return Promise.resolve(apiToken);
    }

    return fetch("/api-token", {
      method: "GET",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || !data.success) {
            return Promise.reject({
              status: res.status,
              data: data,
            });
          }
          apiToken = data.data.token;
          return apiToken;
        });
      })
      .catch(function (err) {
        console.error("Failed to fetch API token:", err);
        throw err;
      });
  }

  // Utility: API request with auth headers
  function apiRequest(method, url, body) {
    return getApiToken()
      .then(function (token) {
        const headers = {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "Authorization": "Bearer " + token,
        };

        if (body && typeof body === "object" && !(body instanceof FormData)) {
          headers["Content-Type"] = "application/json";
        }

        const opts = {
          method: method,
          headers: headers,
          credentials: "same-origin",
        };

        if (body && method !== "GET") {
          opts.body = body instanceof FormData ? body : JSON.stringify(body);
        }

        return fetch(url, opts)
          .then(function (res) {
            return res
              .json()
              .catch(function () {
                return {};
              })
              .then(function (data) {
                if (!res.ok) {
                  return Promise.reject({
                    status: res.status,
                    data: data,
                  });
                }
                return data;
              });
          });
      })
      .catch(function (err) {
        console.error("API request failed:", err);
        throw err;
      });
  }

  // Helper: escape HTML
  function esc(v) {
    return String(v || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  // Format date as DD/MM/YYYY
  function formatDate(dateStr) {
    if (!dateStr) return "-";
    const d = new Date(dateStr);
    return (
      ("0" + d.getDate()).slice(-2) +
      "/" +
      ("0" + (d.getMonth() + 1)).slice(-2) +
      "/" +
      d.getFullYear()
    );
  }

  // Format currency
  function formatCurrency(amount) {
    if (!amount) return "Rp 0";
    return "Rp " + parseInt(amount).toLocaleString("id-ID");
  }

  // Main InvoicesManager object
  const InvoicesManager = {
    currentPage: 1,
    totalPages: 1,
    invoices: [],
    currentEditId: null,

    /**
     * Initialize the invoices list page
     */
    init: function () {
      this.bindEvents();
      this.loadInvoices();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      // Add invoice button
      const addBtn = document.getElementById("btn_add_invoice");
      if (addBtn) {
        addBtn.addEventListener("click", function () {
          self.currentEditId = null;
          document.getElementById("invoiceForm").reset();
          document.getElementById("invoiceModalTitle").textContent = "Add Invoice";
        });
      }

      // Form submission
      const form = document.getElementById("invoiceForm");
      if (form) {
        form.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleSaveInvoice();
        });
      }

      // Filter by status
      const statusFilter = document.querySelector("[data-invoice-filter-status]");
      if (statusFilter) {
        statusFilter.addEventListener("change", function () {
          self.currentPage = 1;
          self.loadInvoices();
        });
      }

      // Filter by paid status
      const paidFilter = document.querySelector("[data-invoice-filter-paid]");
      if (paidFilter) {
        paidFilter.addEventListener("change", function () {
          self.currentPage = 1;
          self.loadInvoices();
        });
      }

      // Search
      const searchInput = document.querySelector("[data-invoice-filter-search]");
      if (searchInput) {
        searchInput.addEventListener("keyup", function () {
          self.currentPage = 1;
          self.loadInvoices();
        });
      }

      // Pagination
      document.addEventListener("click", function (e) {
        if (e.target.matches("[data-invoice-first]")) {
          self.currentPage = 1;
          self.loadInvoices();
        }
        if (e.target.matches("[data-invoice-prev]")) {
          self.currentPage = Math.max(1, self.currentPage - 1);
          self.loadInvoices();
        }
        if (e.target.matches("[data-invoice-next]")) {
          self.currentPage = Math.min(self.totalPages, self.currentPage + 1);
          self.loadInvoices();
        }
        if (e.target.matches("[data-invoice-last]")) {
          self.currentPage = self.totalPages;
          self.loadInvoices();
        }
        if (e.target.matches("[data-edit-invoice]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-edit-invoice");
          self.editInvoice(id);
        }
        if (e.target.matches("[data-view-invoice]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-view-invoice");
          self.viewInvoiceDetails(id);
        }
        if (e.target.matches("[data-delete-invoice]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-delete-invoice");
          self.deleteInvoice(id);
        }
      });
    },

    /**
     * Load invoices from API
     */
    loadInvoices: function () {
      const self = this;
      const statusFilter = document.querySelector("[data-invoice-filter-status]")?.value || "";
      const paidFilter = document.querySelector("[data-invoice-filter-paid]")?.value || "";
      const search = document.querySelector("[data-invoice-filter-search]")?.value || "";

      let url = API_BASE + "?page=" + this.currentPage + "&per_page=" + PAGE_SIZE;
      if (statusFilter) url += "&status=" + statusFilter;
      if (paidFilter !== "") url += "&is_paid=" + paidFilter;
      if (search) url += "&search=" + encodeURIComponent(search);

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.invoices = response.data;
            self.totalPages = response.pagination ? response.pagination.last_page : 1;
            self.renderInvoices();
          } else {
            self.showError("Failed to load invoices");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading invoices");
        });
    },

    /**
     * Render invoices table
     */
    renderInvoices: function () {
      const container = document.querySelector("[data-invoices-list-container]");
      if (!container) return;

      let html = '';
      if (this.invoices.length === 0) {
        html = '<div class="card"><div class="card-body text-center text-muted py-4">No invoices found</div></div>';
      } else {
        html = `
          <div class="card">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Invoice Number</th>
                    <th>Company</th>
                    <th>Amount</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Paid</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${this.invoices.map(inv => {
                    const statusBadgeClass = inv.status === "paid" ? "badge-success" : 
                                            inv.status === "sent" ? "badge-info" : 
                                            inv.status === "draft" ? "badge-secondary" : 
                                            inv.isOverdue ? "badge-danger" : "badge-warning";
                    return `
                      <tr>
                        <td><strong>${esc(inv.invoiceNumber)}</strong></td>
                        <td>${esc(inv.companyName)}</td>
                        <td>${formatCurrency(inv.amountDue)}</td>
                        <td>${formatDate(inv.issueDate)}</td>
                        <td>${formatDate(inv.dueDate)}</td>
                        <td>
                          <span class="badge ${statusBadgeClass} d-inline-flex align-items-center badge-xs">
                            <i class="ti ti-point-filled me-1"></i>${esc(inv.status)}
                          </span>
                        </td>
                        <td>
                          <span class="badge ${inv.isPaid ? "badge-success" : "badge-warning"} d-inline-flex align-items-center badge-xs">
                            <i class="ti ti-point-filled me-1"></i>${inv.isPaid ? "Yes" : "No"}
                          </span>
                        </td>
                        <td>
                          <div class="action-icon d-inline-flex">
                            <button class="btn btn-icon btn-sm me-2" data-view-invoice="${inv.id}" title="View">
                              <i class="ti ti-eye"></i>
                            </button>
                            <button class="btn btn-icon btn-sm me-2" data-edit-invoice="${inv.id}" title="Edit">
                              <i class="ti ti-edit"></i>
                            </button>
                            <button class="btn btn-icon btn-sm" data-delete-invoice="${inv.id}" title="Delete">
                              <i class="ti ti-trash"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                    `;
                  }).join('')}
                </tbody>
              </table>
            </div>
          </div>
        `;
      }
      container.innerHTML = html;
    },

    /**
     * Edit invoice
     */
    editInvoice: function (id) {
      const invoice = this.invoices.find(inv => inv.id == id);
      if (!invoice) return;

      this.currentEditId = id;
      document.getElementById("input_invoice_company").value = invoice.companyName;
      document.getElementById("input_invoice_amount").value = invoice.amountDue;
      document.getElementById("input_invoice_issue_date").value = invoice.issueDate;
      document.getElementById("input_invoice_due_date").value = invoice.dueDate;
      document.getElementById("input_invoice_status").value = invoice.status;
      document.getElementById("invoiceForm").dataset.invoiceId = id;
      document.getElementById("invoiceModalTitle").textContent = "Edit Invoice";
      
      const modal = window.bootstrap?.Modal?.getOrCreateInstance(document.getElementById("invoiceModal"));
      modal?.show();
    },

    /**
     * View invoice details
     */
    viewInvoiceDetails: function (id) {
      const invoice = this.invoices.find(inv => inv.id == id);
      if (!invoice) return;

      const detailsHtml = `
        <div class="invoice-details">
          <p><strong>Invoice Number:</strong> ${esc(invoice.invoiceNumber)}</p>
          <p><strong>Company:</strong> ${esc(invoice.companyName)}</p>
          <p><strong>Amount:</strong> ${formatCurrency(invoice.amountDue)}</p>
          <p><strong>Issue Date:</strong> ${formatDate(invoice.issueDate)}</p>
          <p><strong>Due Date:</strong> ${formatDate(invoice.dueDate)}</p>
          <p><strong>Status:</strong> ${esc(invoice.status)}</p>
          <p><strong>Paid:</strong> ${invoice.isPaid ? "Yes" : "No"}</p>
          <p><strong>Is Overdue:</strong> ${invoice.isOverdue ? "Yes" : "No"}</p>
        </div>
      `;

      alert(detailsHtml.replace(/<[^>]*>/g, ''));
    },

    /**
     * Delete invoice
     */
    deleteInvoice: function (id) {
      if (!confirm("Are you sure you want to delete this invoice?")) return;

      const self = this;
      apiRequest("DELETE", API_BASE + "/" + id, null)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Invoice deleted successfully");
            self.loadInvoices();
          } else {
            self.showError("Failed to delete invoice");
          }
        })
        .catch(function (err) {
          self.showError("Error deleting invoice");
        });
    },

    /**
     * Handle save invoice
     */
    handleSaveInvoice: function () {
      const self = this;
      const isEdit = this.currentEditId !== null;

      const payload = {
        company_id: 1, // TODO: Get from form
        amount_due: parseFloat(document.getElementById("input_invoice_amount").value),
        issue_date: document.getElementById("input_invoice_issue_date").value,
        due_date: document.getElementById("input_invoice_due_date").value,
      };

      const method = isEdit ? "PUT" : "POST";
      const url = isEdit ? API_BASE + "/" + this.currentEditId : API_BASE;

      apiRequest(method, url, payload)
        .then(function (response) {
          if (response.success) {
            self.showSuccess(isEdit ? "Invoice updated successfully" : "Invoice created successfully");
            self.currentEditId = null;
            document.getElementById("invoiceForm").reset();
            self.loadInvoices();
            const modal = window.bootstrap?.Modal?.getInstance(document.getElementById("invoiceModal"));
            modal?.hide();
          } else {
            self.showError(response.error?.message || (isEdit ? "Failed to update invoice" : "Failed to create invoice"));
          }
        })
        .catch(function (err) {
          self.showError(isEdit ? "Error updating invoice" : "Error creating invoice");
        });
    },

    /**
     * Show success message
     */
    showSuccess: function (message) {
      this.showToast(message, "success");
    },

    /**
     * Show error message
     */
    showError: function (message) {
      this.showToast(message, "danger");
    },

    /**
     * Show toast notification
     */
    showToast: function (message, type) {
      const alertDiv = document.createElement("div");
      alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
      alertDiv.style.zIndex = 9999;
      alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;
      document.body.appendChild(alertDiv);
      setTimeout(() => alertDiv.remove(), 5000);
    },
  };

  // Initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      InvoicesManager.init();
    });
  } else {
    InvoicesManager.init();
  }

  // Expose to global scope
  window.InvoicesManager = InvoicesManager;
})(window, document);
