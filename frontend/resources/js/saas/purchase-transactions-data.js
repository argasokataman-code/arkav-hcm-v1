(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/transactions";
  const PAGE_SIZE = 15;

  // Shared token key – same as api-client.js so both stay in sync
  var TOKEN_KEY = "arcav_access_token";

  // Get API token: prefer cached arcav_access_token, fall back to /api-token fetch
  function getApiToken() {
    return new Promise(function (resolve, reject) {
      var cached = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem(TOKEN_KEY);
      if (cached) {
        resolve(cached);
        return;
      }
      fetch("/api-token", {
        method: "GET",
        headers: { Accept: "application/json" },
        credentials: "include",
      })
        .then(function (res) {
          if (res.status === 302 || res.status === 401) {
            window.location.href = "/lock-screen";
            reject(new Error("Not authenticated."));
            return;
          }
          if (res.status === 403) {
            window.location.href = "/employee-dashboard";
            reject(new Error("Admin access required."));
            return;
          }
          return res.json();
        })
        .then(function (data) {
          if (data && data.success && data.data && data.data.token) {
            localStorage.setItem(TOKEN_KEY, data.data.token);
            resolve(data.data.token);
          } else {
            reject(new Error("Failed to get API token"));
          }
        })
        .catch(reject);
    });
  }

  // Utility: API request with auth headers
  function apiRequest(method, url, body) {
    const headers = {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    };

    if (body && typeof body === "object" && !(body instanceof FormData)) {
      headers["Content-Type"] = "application/json";
    }

    // Add authorization token if available
    const token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem(TOKEN_KEY);
    if (token) {
      headers["Authorization"] = "Bearer " + token;
    }

    const opts = {
      method: method,
      headers: headers,
      credentials: "include",
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
              // On 401: clear stale cached token and retry once with fresh /api-token
              if (res.status === 401 && !opts._retried) {
                localStorage.removeItem(TOKEN_KEY);
                return fetch("/api-token", { method: "GET", headers: { Accept: "application/json" }, credentials: "include" })
                  .then(function (r) { return r.json(); })
                  .then(function (freshData) {
                    if (freshData && freshData.success && freshData.data && freshData.data.token) {
                      localStorage.setItem(TOKEN_KEY, freshData.data.token);
                      opts.headers["Authorization"] = "Bearer " + freshData.data.token;
                      opts._retried = true;
                      return fetch(url, opts).then(function (r2) { return r2.json().catch(function () { return {}; }); });
                    }
                    return data;
                  })
                  .catch(function () { return data; });
              }
              return Promise.reject({
                status: res.status,
                data: data,
              });
            }
            return data;
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

  // Format date as DD/MM/YYYY HH:MM
  function formatDate(dateStr) {
    if (!dateStr) return "-";
    const d = new Date(dateStr);
    return (
      ("0" + d.getDate()).slice(-2) +
      "/" +
      ("0" + (d.getMonth() + 1)).slice(-2) +
      "/" +
      d.getFullYear() +
      " " +
      ("0" + d.getHours()).slice(-2) +
      ":" +
      ("0" + d.getMinutes()).slice(-2)
    );
  }

  // Format currency
  function formatCurrency(amount) {
    if (!amount) return "Rp 0";
    return "Rp " + parseInt(amount).toLocaleString("id-ID");
  }

  function extractErrorMessage(error, fallback) {
    if (error && error.data && error.data.error && error.data.error.message) {
      return error.data.error.message;
    }

    if (error && error.message) {
      return error.message;
    }

    return fallback;
  }

  // Main TransactionsManager object
  const TransactionsManager = {
    currentPage: 1,
    totalPages: 1,
    transactions: [],
    filters: {
      invoiceNumber: "",
      companySearch: "",
      status: "",
      paymentMethod: "",
      dateFrom: "",
    },

    /**
     * Initialize the transactions list page
     */
    init: function () {
      var self = this;
      getApiToken()
        .then(function () {
          self.bindEvents();
          self.loadTransactions();
        })
        .catch(function (err) {
          console.error("Transactions init error:", err);
          var container = document.querySelector("[data-transactions-list-container]");
          if (container) {
            container.innerHTML = '<div class="card"><div class="card-body text-center text-danger py-4">Failed to initialize: ' + String(err.message || err) + '</div></div>';
          }
        });
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      const searchInvoiceInput = document.getElementById("search_invoice_number");
      const searchCompanyInput = document.getElementById("search_company");
      const statusFilter = document.getElementById("filter_status");
      const paymentMethodFilter = document.getElementById("filter_payment_method");
      const dateFromFilter = document.getElementById("filter_date_from");
      const resetButton = document.getElementById("btn_reset_filters");
      const downloadAllButton = document.getElementById("btn_download_all");

      if (searchInvoiceInput) {
        searchInvoiceInput.addEventListener("keyup", function (e) {
          if (e.key === "Enter") {
            e.preventDefault();
            self.currentPage = 1;
            self.loadTransactions();
          }
        });
      }

      if (searchCompanyInput) {
        searchCompanyInput.addEventListener("keyup", function (e) {
          if (e.key === "Enter") {
            e.preventDefault();
            self.currentPage = 1;
            self.loadTransactions();
          }
        });
      }

      [statusFilter, paymentMethodFilter, dateFromFilter].forEach(function (element) {
        if (!element) return;
        element.addEventListener("change", function () {
          self.currentPage = 1;
          self.loadTransactions();
        });
      });

      if (resetButton) {
        resetButton.addEventListener("click", function () {
          self.resetFilters();
          self.currentPage = 1;
          self.loadTransactions();
        });
      }

      if (downloadAllButton) {
        downloadAllButton.addEventListener("click", function () {
          self.downloadAllTransactions();
        });
      }

      // Pagination buttons
      document.addEventListener("click", function (e) {
        if (e.target.matches("[data-page]")) {
          e.preventDefault();
          const page = parseInt(e.target.getAttribute("data-page"));
          self.currentPage = page;
          self.loadTransactions();
        }

        // View details button
        if (e.target.matches("[data-view-transaction]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-view-transaction");
          self.viewTransactionDetails(id);
        }

        // Download receipt button
        if (e.target.matches("[data-download-receipt]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-download-receipt");
          self.downloadReceipt(id);
        }
      });
    },

    /**
     * Load transactions from API
     */
    loadTransactions: function () {
      const self = this;
      const filters = this.readFilters();
      const params = new URLSearchParams();

      params.set("page", String(this.currentPage));
      params.set("per_page", String(PAGE_SIZE));

      if (filters.invoiceNumber) {
        params.set("invoice_number", filters.invoiceNumber);
      }

      if (filters.companySearch) {
        params.set("company_search", filters.companySearch);
      }

      if (filters.status) {
        params.set("status", filters.status);
      }

      if (filters.paymentMethod) {
        params.set("payment_method", filters.paymentMethod);
      }

      if (filters.dateFrom) {
        params.set("date_from", filters.dateFrom);
      }

      const url = API_BASE + "?" + params.toString();

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.transactions = response.data;
            self.totalPages = response.pagination ? response.pagination.last_page : 1;
            self.renderTransactions();
            self.updateStats();
          } else {
            self.showError(extractErrorMessage(response, "Failed to load transactions"));
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError(extractErrorMessage(err, "Error loading transactions"));
        });
    },

    /**
     * Render transactions table
     */
    renderTransactions: function () {
      const container = document.querySelector('[data-transactions-list-container]');
      if (!container) return;

      let html = '';
      if (this.transactions.length === 0) {
        html = '<div class="card"><div class="card-body text-center text-muted py-4">No transactions found</div></div>';
      } else {
        html = `
          <div class="card">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Invoice</th>
                    <th>Company</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Paid At</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${this.transactions.map(txn => {
                    const statusBadge = `badge bg-${
                      txn.status === "paid" || txn.status === "completed"
                        ? "success"
                        : txn.status === "pending"
                        ? "warning"
                        : txn.status === "failed"
                        ? "danger"
                        : "info"
                    }`;
                    const paymentMethodBadge = `badge bg-light text-dark`;
                    return `
                      <tr>
                        <td><strong>${esc(txn.invoiceNumber || txn.transactionCode || "-")}</strong></td>
                        <td>${esc(txn.companyName || txn.company?.name || "N/A")}</td>
                        <td>${esc(txn.packageName || txn.packageAddon?.name || txn.subscription?.planCode || "-")}</td>
                        <td>${formatCurrency(txn.amount)}</td>
                        <td><span class="${paymentMethodBadge}">${esc(txn.paymentMethod)}</span></td>
                        <td><span class="${statusBadge}">${esc(txn.status)}</span></td>
                        <td>${formatDate(txn.createdAt)}</td>
                        <td>${formatDate(txn.paidAt) || "-"}</td>
                        <td>
                          <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-info" data-view-transaction="${txn.id}" title="View Details">
                              <i class="ti ti-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-secondary" data-download-receipt="${txn.id}" title="Download Receipt">
                              <i class="ti ti-download"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                    `;
                  }).join('')}
                </tbody>
              </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
              <small class="text-muted">Showing ${this.transactions.length} transactions</small>
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0" data-transaction-pagination></ul>
              </nav>
            </div>
          </div>
        `;
      }
      container.innerHTML = html;
      this.renderPagination();
    },

    readFilters: function () {
      const invoiceInput = document.getElementById("search_invoice_number");
      const companyInput = document.getElementById("search_company");
      const statusSelect = document.getElementById("filter_status");
      const paymentMethodSelect = document.getElementById("filter_payment_method");
      const dateFromInput = document.getElementById("filter_date_from");

      this.filters = {
        invoiceNumber: invoiceInput ? invoiceInput.value.trim() : "",
        companySearch: companyInput ? companyInput.value.trim() : "",
        status: statusSelect ? statusSelect.value.trim() : "",
        paymentMethod: paymentMethodSelect ? paymentMethodSelect.value.trim() : "",
        dateFrom: dateFromInput ? dateFromInput.value.trim() : "",
      };

      return this.filters;
    },

    resetFilters: function () {
      const invoiceInput = document.getElementById("search_invoice_number");
      const companyInput = document.getElementById("search_company");
      const statusSelect = document.getElementById("filter_status");
      const paymentMethodSelect = document.getElementById("filter_payment_method");
      const dateFromInput = document.getElementById("filter_date_from");

      if (invoiceInput) invoiceInput.value = "";
      if (companyInput) companyInput.value = "";
      if (statusSelect) statusSelect.value = "";
      if (paymentMethodSelect) paymentMethodSelect.value = "";
      if (dateFromInput) dateFromInput.value = "";

      this.filters = {
        invoiceNumber: "",
        companySearch: "",
        status: "",
        paymentMethod: "",
        dateFrom: "",
      };
    },

    /**
     * Render pagination
     */
    renderPagination: function () {
      const container = document.querySelector('[data-transaction-pagination]');
      if (!container) return;
      container.innerHTML = "";
      if (this.currentPage > 1) {
        const li = document.createElement("li");
        li.className = "page-item";
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${this.currentPage - 1}">Previous</a>`;
        container.appendChild(li);
      }
      for (let i = 1; i <= this.totalPages; i++) {
        const li = document.createElement("li");
        li.className = "page-item" + (i === this.currentPage ? " active" : "");
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${i}">${i}</a>`;
        container.appendChild(li);
      }
      if (this.currentPage < this.totalPages) {
        const li = document.createElement("li");
        li.className = "page-item";
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${this.currentPage + 1}">Next</a>`;
        container.appendChild(li);
      }
    },

    /**
     * Update statistics
     */
    updateStats: function () {
      const totalEl = document.getElementById("total_transactions");
      const completedEl = document.getElementById("completed_transactions");
      const totalAmountEl = document.getElementById("total_amount");

      if (totalEl) totalEl.textContent = this.transactions.length;

      const completedCount = this.transactions.filter(
        (t) => t.status === "completed"
      ).length;
      if (completedEl) completedEl.textContent = completedCount;

      const totalAmount = this.transactions.reduce((sum, t) => sum + (t.amount || 0), 0);
      if (totalAmountEl) totalAmountEl.textContent = formatCurrency(totalAmount);
    },

    /**
     * View transaction details
     */
    viewTransactionDetails: function (id) {
      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            const txn = response.data;
            const html = `
              <div class="row">
                <div class="col-md-6">
                  <p><strong>Invoice Number:</strong> ${esc(txn.invoiceNumber)}</p>
                  <p><strong>Company:</strong> ${esc(txn.companyName)}</p>
                  <p><strong>Package:</strong> ${esc(txn.packageName || "-")}</p>
                  <p><strong>Amount:</strong> ${formatCurrency(txn.amount)}</p>
                  <p><strong>Payment Method:</strong> ${esc(txn.paymentMethod)}</p>
                </div>
                <div class="col-md-6">
                  <p><strong>Status:</strong> <span class="badge bg-success">${esc(txn.status)}</span></p>
                  <p><strong>Created:</strong> ${formatDate(txn.createdAt)}</p>
                  <p><strong>Paid:</strong> ${formatDate(txn.paidAt) || "-"}</p>
                  <p><strong>Description:</strong> ${esc(txn.description || "-")}</p>
                </div>
              </div>
            `;
            const content = document.getElementById("details_content");
            if (content) {
              content.innerHTML = html;
            }

            const modalEl = document.getElementById("detailsModal");
            if (modalEl && window.bootstrap && window.bootstrap.Modal) {
              const modal = window.bootstrap.Modal.getOrCreateInstance
                ? window.bootstrap.Modal.getOrCreateInstance(modalEl)
                : new window.bootstrap.Modal(modalEl);
              modal.show();
            }
          } else {
            self.showError(extractErrorMessage(response, "Failed to load transaction details"));
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError(extractErrorMessage(err, "Error loading transaction details"));
        });
    },

    downloadAllTransactions: function () {
      window.open(API_BASE + "/export", "_self");
    },

    /**
     * Download receipt
     */
    downloadReceipt: function (id) {
      this.viewTransactionDetails(id);
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
      TransactionsManager.init();
    });
  } else {
    TransactionsManager.init();
  }

  // Expose to global scope
  window.TransactionsManager = TransactionsManager;
})(window, document);
