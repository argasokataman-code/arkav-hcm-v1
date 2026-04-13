(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/subscriptions";
  const PAGE_SIZE = 10;

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

  // Main SubscriptionsManager object
  const SubscriptionsManager = {
    currentPage: 1,
    totalPages: 1,
    subscriptions: [],
    currentEditId: null,

    /**
     * Initialize the subscriptions list page
     */
    init: function () {
      this.bindEvents();
      this.loadSubscriptions();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      // Add form submission
      const addForm = document.getElementById("add_subscription_form");
      if (addForm) {
        addForm.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleAddSubscription(e.target);
        });
      }

      // Edit form submission
      const editForm = document.getElementById("edit_subscription_form");
      if (editForm) {
        editForm.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleEditSubscription(e.target);
        });
      }

      // Pagination buttons
      document.addEventListener("click", function (e) {
        if (e.target.matches("[data-page]")) {
          e.preventDefault();
          const page = parseInt(e.target.getAttribute("data-page"));
          self.currentPage = page;
          self.loadSubscriptions();
        }

        // Edit button
        if (e.target.matches("[data-edit-subscription]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-edit-subscription");
          self.editSubscription(id);
        }

        // Delete button
        if (e.target.matches("[data-delete-subscription]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-delete-subscription");
          self.deleteSubscription(id);
        }

        // Cancel subscription button
        if (e.target.matches("[data-cancel-subscription]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-cancel-subscription");
          self.cancelSubscription(id);
        }

        // View details button
        if (e.target.matches("[data-view-subscription]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-view-subscription");
          self.viewSubscriptionDetails(id);
        }
      });
    },

    /**
     * Load subscriptions from API
     */
    loadSubscriptions: function () {
      const self = this;
      const url = API_BASE + "?page=" + this.currentPage + "&per_page=" + PAGE_SIZE;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.subscriptions = response.data;
            self.totalPages = response.pagination ? response.pagination.last_page : 1;
            self.renderSubscriptions();
            self.updateStats();
          } else {
            self.showError("Failed to load subscriptions");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading subscriptions");
        });
    },

    /**
     * Render subscriptions table
     */
    renderSubscriptions: function () {
      const tbody = document.querySelector("#subscriptions_table tbody");
      if (!tbody) return;

      tbody.innerHTML = "";

      if (this.subscriptions.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="7" class="text-center py-3">No subscriptions found</td></tr>';
        return;
      }

      this.subscriptions.forEach((sub) => {
        const statusBadge = `badge bg-${
          sub.status === "active"
            ? "success"
            : sub.status === "trial"
            ? "info"
            : "danger"
        }`;
        const autoRenewBadge = `badge bg-${sub.autoRenew ? "success" : "warning"}`;

        const row = document.createElement("tr");
        row.innerHTML = `
          <td>${esc(sub.companyName || "N/A")}</td>
          <td>${esc(sub.packageName || "N/A")}</td>
          <td><span class="${statusBadge}">${esc(sub.status)}</span></td>
          <td>${formatDate(sub.startDate)}</td>
          <td>${formatDate(sub.endDate)}</td>
          <td><span class="${autoRenewBadge}">${sub.autoRenew ? "Yes" : "No"}</span></td>
          <td>
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-info" data-view-subscription="${sub.id}" title="View Details">
                <i class="ti ti-eye"></i>
              </button>
              <button class="btn btn-sm btn-primary" data-edit-subscription="${sub.id}" title="Edit">
                <i class="ti ti-edit"></i>
              </button>
              ${
                sub.status === "active"
                  ? `<button class="btn btn-sm btn-warning" data-cancel-subscription="${sub.id}" title="Cancel">
                      <i class="ti ti-x"></i>
                    </button>`
                  : ""
              }
              <button class="btn btn-sm btn-danger" data-delete-subscription="${sub.id}" title="Delete">
                <i class="ti ti-trash"></i>
              </button>
            </div>
          </td>
        `;
        tbody.appendChild(row);
      });

      this.renderPagination();
    },

    /**
     * Render pagination
     */
    renderPagination: function () {
      const container = document.getElementById("pagination_container");
      if (!container) return;

      container.innerHTML = "";
      const nav = document.createElement("nav");
      const ul = document.createElement("ul");
      ul.className = "pagination mb-0";

      if (this.currentPage > 1) {
        const li = document.createElement("li");
        li.className = "page-item";
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${
          this.currentPage - 1
        }">Previous</a>`;
        ul.appendChild(li);
      }

      for (let i = 1; i <= this.totalPages; i++) {
        const li = document.createElement("li");
        li.className = "page-item" + (i === this.currentPage ? " active" : "");
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${i}">${i}</a>`;
        ul.appendChild(li);
      }

      if (this.currentPage < this.totalPages) {
        const li = document.createElement("li");
        li.className = "page-item";
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${
          this.currentPage + 1
        }">Next</a>`;
        ul.appendChild(li);
      }

      nav.appendChild(ul);
      container.appendChild(nav);
    },

    /**
     * Update statistics
     */
    updateStats: function () {
      const totalEl = document.getElementById("total_subscriptions");
      const activeEl = document.getElementById("active_subscriptions");
      const trialEl = document.getElementById("trial_subscriptions");

      const activeCount = this.subscriptions.filter(
        (s) => s.status === "active"
      ).length;
      const trialCount = this.subscriptions.filter((s) => s.status === "trial").length;

      if (totalEl) totalEl.textContent = this.subscriptions.length;
      if (activeEl) activeEl.textContent = activeCount;
      if (trialEl) trialEl.textContent = trialCount;
    },

    /**
     * Handle add subscription
     */
    handleAddSubscription: function (form) {
      const self = this;
      const formData = new FormData(form);
      const data = {
        companyId: parseInt(formData.get("company_id")),
        packageId: parseInt(formData.get("package_id")),
        status: formData.get("status"),
        startDate: formData.get("start_date"),
        endDate: formData.get("end_date"),
        autoRenew: formData.get("auto_renew") === "1",
      };

      apiRequest("POST", API_BASE, data)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Subscription created successfully");
            form.reset();
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("add_subscription")
            );
            if (modal) modal.hide();
            self.currentPage = 1;
            self.loadSubscriptions();
          } else {
            self.showError(response.error?.message || "Failed to create subscription");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error creating subscription");
        });
    },

    /**
     * Edit subscription
     */
    editSubscription: function (id) {
      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            const sub = response.data;
            document.getElementById("edit_subscription_id").value = sub.id;
            document.getElementById("edit_company_id").value = sub.companyId;
            document.getElementById("edit_package_id").value = sub.packageId;
            document.getElementById("edit_status").value = sub.status;
            document.getElementById("edit_start_date").value = sub.startDate;
            document.getElementById("edit_end_date").value = sub.endDate;
            document.getElementById("edit_auto_renew").checked = sub.autoRenew;

            self.currentEditId = id;
            const modal = new bootstrap.Modal(document.getElementById("edit_subscription"));
            modal.show();
          } else {
            self.showError("Failed to load subscription");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading subscription");
        });
    },

    /**
     * Handle edit subscription
     */
    handleEditSubscription: function (form) {
      const self = this;
      const id = document.getElementById("edit_subscription_id").value;
      const formData = new FormData(form);
      const data = {
        status: formData.get("status"),
        endDate: formData.get("end_date"),
        autoRenew: formData.get("auto_renew") === "1",
      };

      const url = API_BASE + "/" + id;

      apiRequest("PUT", url, data)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Subscription updated successfully");
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("edit_subscription")
            );
            if (modal) modal.hide();
            self.loadSubscriptions();
          } else {
            self.showError(response.error?.message || "Failed to update subscription");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error updating subscription");
        });
    },

    /**
     * Cancel subscription
     */
    cancelSubscription: function (id) {
      if (!confirm("Are you sure you want to cancel this subscription?")) return;

      const self = this;
      const url = API_BASE + "/" + id;
      const data = { status: "cancelled" };

      apiRequest("PUT", url, data)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Subscription cancelled successfully");
            self.loadSubscriptions();
          } else {
            self.showError(response.error?.message || "Failed to cancel subscription");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error cancelling subscription");
        });
    },

    /**
     * Delete subscription
     */
    deleteSubscription: function (id) {
      if (!confirm("Are you sure you want to delete this subscription?")) return;

      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("DELETE", url, null)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Subscription deleted successfully");
            self.loadSubscriptions();
          } else {
            self.showError(response.error?.message || "Failed to delete subscription");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error deleting subscription");
        });
    },

    /**
     * View subscription details
     */
    viewSubscriptionDetails: function (id) {
      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            const sub = response.data;
            const html = `
              <div class="row">
                <div class="col-md-6">
                  <p><strong>Company:</strong> ${esc(sub.companyName)}</p>
                  <p><strong>Package:</strong> ${esc(sub.packageName)}</p>
                  <p><strong>Status:</strong> ${esc(sub.status)}</p>
                </div>
                <div class="col-md-6">
                  <p><strong>Start Date:</strong> ${formatDate(sub.startDate)}</p>
                  <p><strong>End Date:</strong> ${formatDate(sub.endDate)}</p>
                  <p><strong>Auto Renew:</strong> ${sub.autoRenew ? "Yes" : "No"}</p>
                </div>
              </div>
            `;
            document.getElementById("details_content").innerHTML = html;
            const modal = new bootstrap.Modal(document.getElementById("details_modal"));
            modal.show();
          } else {
            self.showError("Failed to load subscription details");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading subscription details");
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
      SubscriptionsManager.init();
    });
  } else {
    SubscriptionsManager.init();
  }

  // Expose to global scope
  window.SubscriptionsManager = SubscriptionsManager;
})(window, document);
