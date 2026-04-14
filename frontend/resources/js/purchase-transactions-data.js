(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/transactions";
  const PAGE_SIZE = 15;

  // Utility: API request with auth headers
  function apiRequest(method, url, body) {
    const headers = {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
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

  // Main TransactionsManager object
  const TransactionsManager = {
    currentPage: 1,
    totalPages: 1,
    transactions: [],
    currentFilter: "",
    filterType: "all",

    /**
     * Initialize the transactions list page
     */
    init: function () {
      this.bindEvents();
      this.loadTransactions();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      // Search functionality
      const searchInput = document.getElementById("transaction_search");
      if (searchInput) {
        searchInput.addEventListener("keyup", function (e) {
          if (e.key === "Enter") {
            e.preventDefault();
            self.currentFilter = this.value;
            self.currentPage = 1;
            self.loadTransactions();
          }
        });

        // Search button
        const searchBtn = document.getElementById("search_button");
        if (searchBtn) {
          searchBtn.addEventListener("click", function () {
            self.currentFilter = searchInput.value;
            self.currentPage = 1;
            self.loadTransactions();
          });
        }
      }

      // Filter functionality
      const filterSelect = document.getElementById("transaction_filter");
      if (filterSelect) {
        filterSelect.addEventListener("change", function () {
          self.filterType = this.value;
          self.currentPage = 1;
          self.loadTransactions();
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
      let url = API_BASE + "?page=" + this.currentPage + "&per_page=" + PAGE_SIZE;

      if (self.currentFilter) {
        url += "&search=" + encodeURIComponent(self.currentFilter);
      }

      if (self.filterType !== "all") {
        switch (self.filterType) {
          case "status":
            url += "&filter_type=status";
            break;
          case "company":
            url += "&filter_type=company";
            break;
          case "date":
            url += "&filter_type=date";
            break;
          case "amount":
            url += "&filter_type=amount";
            break;
        }
      }

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.transactions = response.data;
            self.totalPages = response.pagination ? response.pagination.last_page : 1;
            self.renderTransactions();
            self.updateStats();
          } else {
            self.showError("Failed to load transactions");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading transactions");
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
                      txn.status === "completed"
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
                        <td><strong>${esc(txn.transactionCode)}</strong></td>
                        <td>${esc(txn.companyName || "N/A")}</td>
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
                  <p><strong>Transaction Code:</strong> ${esc(txn.transactionCode)}</p>
                  <p><strong>Company:</strong> ${esc(txn.companyName)}</p>
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
              ${
                txn.items && txn.items.length > 0
                  ? `
                <hr>
                <h6>Items:</h6>
                <table class="table table-sm">
                  <tbody>
                    ${txn.items
                      .map(
                        (item) => `
                      <tr>
                        <td>${esc(item.description)}</td>
                        <td class="text-end">${formatCurrency(item.amount)}</td>
                      </tr>
                    `
                      )
                      .join("")}
                  </tbody>
                </table>
              `
                  : ""
              }
            `;
            document.getElementById("transaction_details_content").innerHTML = html;
            const modal = new bootstrap.Modal(
              document.getElementById("transaction_details_modal")
            );
            modal.show();
          } else {
            self.showError("Failed to load transaction details");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading transaction details");
        });
    },

    /**
     * Download receipt
     */
    downloadReceipt: function (id) {
      const url = API_BASE + "/" + id + "/receipt";
      const link = document.createElement("a");
      link.href = url;
      link.download = "receipt-" + id + ".pdf";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
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
