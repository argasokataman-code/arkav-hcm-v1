(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/payments";
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

  // Main PaymentsManager object
  const PaymentsManager = {
    currentPage: 1,
    totalPages: 1,
    payments: [],
    currentVerifyId: null,

    /**
     * Initialize the payments list page
     */
    init: function () {
      this.bindEvents();
      this.loadCompaniesDropdown();
      this.loadPayments();
    },

    /**
     * Load companies into the payment modal dropdown
     */
    loadCompaniesDropdown: function () {
      const select = document.getElementById("input_payment_company_id");
      if (!select) return;

      apiRequest("GET", "/v1/saas/dashboard/companies")
        .then(function (response) {
          const companies = response.data || [];
          select.innerHTML = '<option value="">Select company</option>';
          companies.forEach(function (c) {
            const opt = document.createElement("option");
            opt.value = c.id;
            opt.textContent = esc(c.name);
            select.appendChild(opt);
          });
        })
        .catch(function () {
          select.innerHTML = '<option value="">Failed to load companies</option>';
        });
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      // Add payment button
      const addBtn = document.getElementById("btn_add_payment");
      if (addBtn) {
        addBtn.addEventListener("click", function () {
          document.getElementById("paymentForm").reset();
          document.getElementById("paymentModalTitle").textContent = "Add Payment";
        });
      }

      // Form submission
      const form = document.getElementById("paymentForm");
      if (form) {
        form.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleSavePayment();
        });
      }

      // Filter by status
      const statusFilter = document.querySelector("[data-payment-filter-status]");
      if (statusFilter) {
        statusFilter.addEventListener("change", function () {
          self.currentPage = 1;
          self.loadPayments();
        });
      }

      // Filter by method
      const methodFilter = document.querySelector("[data-payment-filter-method]");
      if (methodFilter) {
        methodFilter.addEventListener("change", function () {
          self.currentPage = 1;
          self.loadPayments();
        });
      }

      // Pagination
      document.addEventListener("click", function (e) {
        if (e.target.matches("[data-payment-first]")) {
          self.currentPage = 1;
          self.loadPayments();
        }
        if (e.target.matches("[data-payment-prev]")) {
          self.currentPage = Math.max(1, self.currentPage - 1);
          self.loadPayments();
        }
        if (e.target.matches("[data-payment-next]")) {
          self.currentPage = Math.min(self.totalPages, self.currentPage + 1);
          self.loadPayments();
        }
        if (e.target.matches("[data-payment-last]")) {
          self.currentPage = self.totalPages;
          self.loadPayments();
        }
        const verifyBtn = e.target.closest("[data-verify-payment]");
        if (verifyBtn) {
          e.preventDefault();
          const id = verifyBtn.getAttribute("data-verify-payment");
          self.verifyPayment(id);
        }
        const viewBtn = e.target.closest("[data-view-payment]");
        if (viewBtn) {
          e.preventDefault();
          const id = viewBtn.getAttribute("data-view-payment");
          self.viewPaymentDetails(id);
        }
        const deleteBtn = e.target.closest("[data-delete-payment]");
        if (deleteBtn) {
          e.preventDefault();
          const id = deleteBtn.getAttribute("data-delete-payment");
          self.deletePayment(id);
        }
      });
    },

    /**
     * Load payments from API
     */
    loadPayments: function () {
      const self = this;
      const statusFilter = document.querySelector("[data-payment-filter-status]")?.value || "";
      const methodFilter = document.querySelector("[data-payment-filter-method]")?.value || "";

      let url = API_BASE + "?page=" + this.currentPage + "&per_page=" + PAGE_SIZE;
      if (statusFilter) url += "&status=" + statusFilter;
      if (methodFilter) url += "&payment_method=" + methodFilter;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.payments = response.data;
            self.totalPages = response.pagination ? response.pagination.last_page : 1;
            self.renderPayments();
          } else {
            self.showError("Failed to load payments");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading payments");
        });
    },

    /**
     * Render payments table
     */
    renderPayments: function () {
      const container = document.querySelector("[data-payments-list-container]");
      if (!container) return;

      let html = '';
      if (this.payments.length === 0) {
        html = '<div class="card"><div class="card-body text-center text-muted py-4">No payments found</div></div>';
      } else {
        html = `
          <div class="card">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Company</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                    <th>Paid Date</th>
                    <th>Verified</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${this.payments.map(pmt => {
                    const statusBadgeClass = pmt.status === "completed" ? "text-bg-success" : 
                                            pmt.status === "pending" ? "text-bg-warning" : 
                                            pmt.status === "failed" ? "text-bg-danger" : "text-bg-secondary";
                    return `
                      <tr>
                        <td>${esc(pmt.companyName)}</td>
                        <td>${formatCurrency(pmt.amount)}</td>
                        <td>${esc(pmt.paymentMethod || "-")}</td>
                        <td>
                          <span class="badge ${statusBadgeClass} d-inline-flex align-items-center badge-xs">
                            <i class="ti ti-point-filled me-1"></i>${esc(pmt.status)}
                          </span>
                        </td>
                        <td>${pmt.paidAt ? formatDate(pmt.paidAt) : "-"}</td>
                        <td>
                          <span class="badge ${pmt.isCompleted ? "text-bg-success" : "text-bg-secondary"} d-inline-flex align-items-center badge-xs">
                            <i class="ti ti-point-filled me-1"></i>${pmt.isCompleted ? "Yes" : "No"}
                          </span>
                        </td>
                        <td>
                          <div class="action-icon d-inline-flex">
                            <button class="btn btn-icon btn-sm me-2" data-view-payment="${pmt.id}" title="View">
                              <i class="ti ti-eye"></i>
                            </button>
                            ${pmt.status === "pending" ? `
                              <button class="btn btn-icon btn-sm me-2" data-verify-payment="${pmt.id}" title="Verify">
                                <i class="ti ti-check"></i>
                              </button>
                            ` : ""}
                            <button class="btn btn-icon btn-sm" data-delete-payment="${pmt.id}" title="Delete">
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
     * Verify payment
     */
    verifyPayment: function (id) {
      const self = this;
      const confirmMessage = "Mark this payment as verified?";
      const confirmPromise =
        window.ArcavUi && typeof window.ArcavUi.confirm === "function"
          ? window.ArcavUi.confirm(confirmMessage, "Verify Payment")
          : Promise.resolve(false);

      confirmPromise.then(function (confirmed) {
        if (!confirmed) return;

        apiRequest("PUT", API_BASE + "/" + id + "/verify", {})
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Payment verified successfully");
            self.loadPayments();
          } else {
            self.showError("Failed to verify payment");
          }
        })
        .catch(function (err) {
          self.showError("Error verifying payment");
        });
      });
    },

    /**
     * View payment details
     */
    viewPaymentDetails: function (id) {
      const payment = this.payments.find(pmt => pmt.id == id);
      if (!payment) return;

      const detailsHtml = `
        <div class="payment-details">
          <p><strong>Company:</strong> ${esc(payment.companyName)}</p>
          <p><strong>Amount:</strong> ${formatCurrency(payment.amount)} ${payment.currency}</p>
          <p><strong>Payment Method:</strong> ${esc(payment.paymentMethod || "-")}</p>
          <p><strong>Status:</strong> ${esc(payment.status)}</p>
          <p><strong>Gateway:</strong> ${esc(payment.gateway || "-")}</p>
          <p><strong>Gateway Reference:</strong> ${esc(payment.gatewayReference || "-")}</p>
          <p><strong>Paid Date:</strong> ${payment.paidAt ? formatDate(payment.paidAt) : "-"}</p>
          <p><strong>Verified:</strong> ${payment.isCompleted ? "Yes" : "No"}</p>
          <p><strong>Notes:</strong> ${payment.notes || "-"}</p>
        </div>
      `;
      const text = detailsHtml.replace(/<[^>]*>/g, '');
      if (window.ArcavUi && typeof window.ArcavUi.showInfo === "function") {
        window.ArcavUi.showInfo("Payment Details", text);
        return;
      }
      this.showToast(text, "info");
    },

    /**
     * Delete payment
     */
    deletePayment: function (id) {
      const self = this;
      const confirmMessage = "Are you sure you want to delete this payment?";
      const confirmPromise =
        window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
          ? window.ArcavUi.confirmDelete("Hapus payment ini? Tindakan tidak dapat dibatalkan.", "Delete Payment")
          : window.ArcavUi && typeof window.ArcavUi.confirm === "function"
            ? window.ArcavUi.confirm(confirmMessage, "Delete Payment")
            : Promise.resolve(false);

      confirmPromise.then(function (confirmed) {
        if (!confirmed) return;

        apiRequest("DELETE", API_BASE + "/" + id, null)
          .then(function (response) {
            if (response.success) {
              self.showSuccess("Payment deleted successfully");
              self.loadPayments();
            } else {
              self.showError("Failed to delete payment");
            }
          })
          .catch(function (err) {
            self.showError("Error deleting payment");
          });
      });
    },

    /**
     * Handle save payment
     */
    handleSavePayment: function () {
      const self = this;

      const payload = {
        company_id: parseInt(document.getElementById("input_payment_company_id").value, 10),
        amount: parseFloat(document.getElementById("input_payment_amount").value),
        currency: document.getElementById("input_payment_currency").value,
        payment_method: document.getElementById("input_payment_method").value,
        gateway: document.getElementById("input_payment_gateway").value || undefined,
        gateway_reference: document.getElementById("input_payment_gateway_ref").value || undefined,
        notes: document.getElementById("input_payment_notes").value || undefined,
      };

      apiRequest("POST", API_BASE, payload)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Payment recorded successfully");
            document.getElementById("paymentForm").reset();
            self.loadPayments();
            const modal = window.bootstrap?.Modal?.getInstance(document.getElementById("paymentModal"));
            modal?.hide();
          } else {
            self.showError(response.error?.message || "Failed to record payment");
          }
        })
        .catch(function (err) {
          self.showError("Error recording payment");
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
      PaymentsManager.init();
    });
  } else {
    PaymentsManager.init();
  }

  // Expose to global scope
  window.PaymentsManager = PaymentsManager;
})(window, document);
