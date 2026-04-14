(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/subscriptions";
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

  // Main SubscriptionsManager object
  const SubscriptionsManager = {
    isInitialized: false,
    currentUser: null,
    isAdminUser: false,
    currentPage: 1,
    totalPages: 1,
    subscriptions: [],
    companies: [],
    packages: [],
    currentEditId: null,
    subscriptionModalInstance: null,

    /**
     * Initialize the subscriptions list page
     */
    init: function () {
      if (this.isInitialized) return;
      this.isInitialized = true;

      this.subscriptionModalInstance = window.bootstrap
        ? window.bootstrap.Modal.getOrCreateInstance(document.getElementById("subscriptionModal"))
        : null;

      this.bindEvents();
      this.loadCurrentUser()
        .then(() => {
          this.applyRoleUi();

          const tasks = [this.loadSubscriptions()];
          if (this.isAdminUser) {
            tasks.unshift(this.loadPackages());
            tasks.unshift(this.loadCompanies());
          }
          return Promise.all(tasks);
        })
        .catch((err) => {
          if (err && window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
            window.AuthApi.handleUnauthorizedFromApi(err.status, err.data);
          }
        });
    },

    loadCurrentUser: function () {
      const self = this;
      return apiRequest("GET", "/v1/identity/auth/me", null)
        .then(function (response) {
          self.currentUser = response?.data || null;
          self.isAdminUser = !!response?.data?.hcmAdmin;
          return response;
        });
    },

    applyRoleUi: function () {
      const addButton = document.querySelector("[data-subscription-add-button]");
      const readOnlyNotice = document.querySelector("[data-subscription-readonly-notice]");

      if (addButton) {
        addButton.classList.toggle("d-none", !this.isAdminUser);
      }

      if (readOnlyNotice) {
        readOnlyNotice.classList.toggle("d-none", this.isAdminUser);
      }
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      const form = document.getElementById("subscriptionForm");
      if (form) {
        form.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleSaveSubscription();
        });
      }

      const addBtn = document.getElementById("btn_add_subscription");
      if (addBtn) {
        addBtn.addEventListener("click", function () {
          self.openCreateModal();
        });
      }

      const statusFilter = document.getElementById("filter_status");
      if (statusFilter) {
        statusFilter.addEventListener("change", function () {
          self.currentPage = 1;
          self.loadSubscriptions();
        });
      }

      const cycleFilter = document.getElementById("filter_cycle");
      if (cycleFilter) {
        cycleFilter.addEventListener("change", function () {
          self.currentPage = 1;
          self.loadSubscriptions();
        });
      }

      const searchInput = document.getElementById("search_subscriptions");
      if (searchInput) {
        let timer = null;
        searchInput.addEventListener("input", function () {
          window.clearTimeout(timer);
          timer = window.setTimeout(function () {
            self.currentPage = 1;
            self.loadSubscriptions();
          }, 250);
        });
      }

      const resetBtn = document.getElementById("btn_reset_filters");
      if (resetBtn) {
        resetBtn.addEventListener("click", function () {
          const status = document.getElementById("filter_status");
          const cycle = document.getElementById("filter_cycle");
          const search = document.getElementById("search_subscriptions");
          if (status) status.value = "";
          if (cycle) cycle.value = "";
          if (search) search.value = "";
          self.currentPage = 1;
          self.loadSubscriptions();
        });
      }

      // Pagination buttons
      document.addEventListener("click", function (e) {
        const pageLink = e.target.closest("[data-page]");
        if (pageLink) {
          e.preventDefault();
          const page = parseInt(pageLink.getAttribute("data-page"), 10);
          self.currentPage = page;
          self.loadSubscriptions();
        }

        const editBtn = e.target.closest("[data-edit-subscription]");
        if (editBtn) {
          e.preventDefault();
          const id = editBtn.getAttribute("data-edit-subscription");
          self.editSubscription(id);
        }

        const deleteBtn = e.target.closest("[data-delete-subscription]");
        if (deleteBtn) {
          e.preventDefault();
          const id = deleteBtn.getAttribute("data-delete-subscription");
          self.deleteSubscription(id);
        }

        const cancelBtn = e.target.closest("[data-cancel-subscription]");
        if (cancelBtn) {
          e.preventDefault();
          const id = cancelBtn.getAttribute("data-cancel-subscription");
          self.cancelSubscription(id);
        }

        const viewBtn = e.target.closest("[data-view-subscription]");
        if (viewBtn) {
          e.preventDefault();
          const id = viewBtn.getAttribute("data-view-subscription");
          self.viewSubscriptionDetails(id);
        }
      });
    },

    loadCompanies: function () {
      const self = this;
      if (!this.isAdminUser) {
        self.companies = [];
        self.renderCompanyOptions();
        return Promise.resolve([]);
      }

      apiRequest("GET", "/v1/company?page=1&per_page=200", null)
        .then(function (response) {
          const list = response?.data?.companies || response?.data || [];
          self.companies = Array.isArray(list) ? list : [];
          self.renderCompanyOptions();
          return self.companies;
        })
        .catch(function () {
          self.companies = [];
          self.renderCompanyOptions();
          return [];
        });
    },

    loadPackages: function () {
      const self = this;
      if (!this.isAdminUser) {
        self.packages = [];
        self.renderPackageOptions();
        return Promise.resolve([]);
      }

      apiRequest("GET", "/v1/saas/packages?status=active&per_page=200", null)
        .then(function (response) {
          const list = response?.data || [];
          self.packages = Array.isArray(list) ? list : [];
          self.renderPackageOptions();
          return self.packages;
        })
        .catch(function () {
          self.packages = [];
          self.renderPackageOptions();
          return [];
        });
    },

    renderCompanyOptions: function () {
      const select = document.getElementById("input_subscription_company");
      if (!select) return;

      const options = this.companies
        .map(function (company) {
          return '<option value="' + esc(company.id) + '">' + esc(company.name || ("Company #" + company.id)) + "</option>";
        })
        .join("");
      select.innerHTML = '<option value="">Select company</option>' + options;
    },

    renderPackageOptions: function () {
      const select = document.getElementById("input_subscription_package");
      if (!select) return;

      const options = this.packages
        .map(function (pkg) {
          return '<option value="' + esc(pkg.id) + '">' + esc(pkg.name || pkg.code || ("Package #" + pkg.id)) + "</option>";
        })
        .join("");
      select.innerHTML = '<option value="">Select package</option>' + options;
    },

    /**
     * Load subscriptions from API
     */
    loadSubscriptions: function () {
      const self = this;
      const params = new URLSearchParams({
        page: String(this.currentPage),
        per_page: String(PAGE_SIZE),
      });

      const status = document.getElementById("filter_status")?.value || "";
      const cycle = document.getElementById("filter_cycle")?.value || "";
      const search = String(document.getElementById("search_subscriptions")?.value || "").trim();
      if (status) params.set("status", status);
      if (cycle) params.set("billing_cycle", cycle);
      if (search) params.set("search", search);

      const url = API_BASE + "?" + params.toString();

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.subscriptions = response.data;
            self.totalPages = response.pagination ? response.pagination.last_page : 1;
            self.renderSubscriptions();
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
      const container = document.querySelector('[data-subscriptions-list-container]');
      if (!container) return;

      let html = '';
      if (this.subscriptions.length === 0) {
        html = '<div class="card"><div class="card-body text-center text-muted py-4">No subscriptions found</div></div>';
      } else {
        html = `
          <div class="card">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Company</th>
                    <th>Package</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Auto Renew</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${this.subscriptions.map(sub => {
                    const statusBadgeClass =
                      sub.status === "active"
                        ? "badge-success"
                        : sub.status === "trial"
                        ? "badge-info"
                        : sub.status === "inactive"
                        ? "badge-secondary"
                        : sub.status === "expired"
                        ? "badge-warning"
                        : "badge-danger";
                    const companyName = sub.companyName || sub.company?.name || "-";
                    const packageName = sub.packageName || sub.package?.name || sub.planCode || "-";
                    const startDate = sub.startDate || sub.startsAt || null;
                    const endDate = sub.endDate || sub.endsAt || null;
                    return `
                      <tr>
                        <tr data-subscription-row="${sub.id}">
                        <td>${esc(companyName)}</td>
                        <td>${esc(packageName)}</td>
                        <td>
                          <span class="badge ${statusBadgeClass} d-inline-flex align-items-center badge-xs">
                            <i class="ti ti-point-filled me-1"></i>${esc(sub.status)}
                          </span>
                        </td>
                        <td>${formatDate(startDate)}</td>
                        <td>${formatDate(endDate)}</td>
                        <td>
                          <span class="badge ${sub.autoRenew ? "badge-success" : "badge-warning"} d-inline-flex align-items-center badge-xs">
                            <i class="ti ti-point-filled me-1"></i>${sub.autoRenew ? "Yes" : "No"}
                          </span>
                        </td>
                        <td>
                          <div class="action-icon d-inline-flex">
                            <button class="btn btn-icon btn-sm me-2" data-view-subscription="${sub.id}" title="View Details">
                              <i class="ti ti-eye"></i>
                            </button>
                            ${this.isAdminUser ? `
                              <button class="btn btn-icon btn-sm me-2" data-edit-subscription="${sub.id}" title="Edit">
                                <i class="ti ti-edit"></i>
                              </button>
                              ${
                                sub.status === "active"
                                  ? `<button class="btn btn-icon btn-sm me-2" data-cancel-subscription="${sub.id}" title="Cancel">
                                      <i class="ti ti-x"></i>
                                    </button>`
                                  : ""
                              }
                              <button class="btn btn-icon btn-sm" data-delete-subscription="${sub.id}" title="Delete">
                                <i class="ti ti-trash"></i>
                              </button>
                            ` : ""}
                          </div>
                        </td>
                      </tr>
                    `;
                  }).join('')}
                </tbody>
              </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
              <small class="text-muted">Showing ${this.subscriptions.length} subscriptions</small>
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0" data-subscription-pagination></ul>
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
      const container = document.querySelector('[data-subscription-pagination]');
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

    openCreateModal: function () {
      if (!this.isAdminUser) {
        this.showError("Admin access required.");
        return;
      }

      this.currentEditId = null;
      const form = document.getElementById("subscriptionForm");
      if (form) form.reset();
      const title = document.getElementById("subscriptionModalTitle");
      const submitBtn = document.querySelector("#subscriptionForm button[type='submit']");
      if (title) title.textContent = "Add Subscription";
      if (submitBtn) submitBtn.textContent = "Save Subscription";
    },

    handleSaveSubscription: function () {
      if (!this.isAdminUser) {
        this.showError("Admin access required.");
        return;
      }

      const self = this;
      const companyId = document.getElementById("input_subscription_company")?.value;
      const packageId = document.getElementById("input_subscription_package")?.value;
      const startDate = document.getElementById("input_subscription_start")?.value;
      const billingCycle = document.getElementById("input_subscription_cycle")?.value;

      if (!companyId || !packageId || !startDate || !billingCycle) {
        self.showError("Company, package, start date, dan billing cycle wajib diisi.");
        return;
      }

      let endDate = null;
      const start = new Date(startDate + "T00:00:00");
      if (!Number.isNaN(start.getTime())) {
        if (billingCycle === "yearly") {
          start.setFullYear(start.getFullYear() + 1);
        } else {
          start.setMonth(start.getMonth() + 1);
        }
        endDate = start.toISOString().slice(0, 10);
      }

      const data = {
        company_id: Number(companyId),
        package_id: Number(packageId),
        status: "active",
        starts_at: startDate,
        ends_at: endDate,
        auto_renew: true,
        billing_cycle: billingCycle,
      };

      const isEdit = !!this.currentEditId;
      const method = isEdit ? "PUT" : "POST";
      const url = isEdit ? API_BASE + "/" + this.currentEditId : API_BASE;

      apiRequest(method, url, data)
        .then(function (response) {
          if (response.success) {
            self.showSuccess(isEdit ? "Subscription updated successfully" : "Subscription created successfully");
            self.currentEditId = null;
            if (self.subscriptionModalInstance) self.subscriptionModalInstance.hide();
            self.currentPage = 1;
            self.loadSubscriptions();
          } else {
            self.showError(response.error?.message || "Failed to save subscription");
          }
        })
        .catch(function (err) {
          self.showError(err?.data?.error?.message || "Error saving subscription");
        });
    },

    /**
     * Edit subscription
     */
    editSubscription: function (id) {
      if (!this.isAdminUser) {
        this.showError("Admin access required.");
        return;
      }

      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            const sub = response.data;
            document.getElementById("input_subscription_company").value = String(sub.companyId || "");
            document.getElementById("input_subscription_package").value = String(sub.packageId || "");
            document.getElementById("input_subscription_start").value = sub.startDate || "";
            document.getElementById("input_subscription_cycle").value = sub.billingCycle || "monthly";

            self.currentEditId = id;
            const title = document.getElementById("subscriptionModalTitle");
            const submitBtn = document.querySelector("#subscriptionForm button[type='submit']");
            if (title) title.textContent = "Edit Subscription";
            if (submitBtn) submitBtn.textContent = "Update Subscription";
            if (self.subscriptionModalInstance) self.subscriptionModalInstance.show();
          } else {
            self.showError("Failed to load subscription");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading subscription");
        });
    },

    cancelSubscription: async function (id) {
      if (!this.isAdminUser) {
        this.showError("Admin access required.");
        return;
      }

      let confirmed = false;
      if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
        confirmed = await window.ArcavUi.confirmDelete(
          "Batalkan subscription ini?",
          "Cancel Subscription"
        );
      } else {
        confirmed = window.confirm("Are you sure you want to cancel this subscription?");
      }
      if (!confirmed) return;

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

    deleteSubscription: async function (id) {
      if (!this.isAdminUser) {
        this.showError("Admin access required.");
        return;
      }

      let confirmed = false;
      if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
        confirmed = await window.ArcavUi.confirmDelete(
          "Hapus subscription ini? Tindakan tidak dapat dibatalkan.",
          "Delete Subscription"
        );
      } else {
        confirmed = window.confirm("Are you sure you want to delete this subscription?");
      }
      if (!confirmed) return;

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
      const sub = this.subscriptions.find(function (item) {
        return String(item.id) === String(id);
      });

      if (!sub) {
        this.showError("Subscription details not found");
        return;
      }

      const text =
        "Company: " + (sub.companyName || "-") + "\n" +
        "Package: " + (sub.packageName || sub.planCode || "-") + "\n" +
        "Status: " + (sub.status || "-") + "\n" +
        "Start Date: " + formatDate(sub.startDate || sub.startsAt) + "\n" +
        "End Date: " + formatDate(sub.endDate || sub.endsAt) + "\n" +
        "Auto Renew: " + (sub.autoRenew ? "Yes" : "No") + "\n" +
        "Billing Cycle: " + (sub.billingCycle || "-") + "\n" +
        "Amount: " + formatCurrency(sub.amount || 0);

      window.alert(text);
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
