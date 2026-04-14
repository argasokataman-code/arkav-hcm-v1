(function (window, document) {
  "use strict";

  const API_BASE = "/v1/company";
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

  // Main CompaniesManager object
  const CompaniesManager = {
    currentPage: 1,
    currentStatus: null,
    companies: [],

    /**
     * Initialize the companies list page
     */
    init: function () {
      this.bindEvents();
      this.loadCompanies();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      // Form submissions
      const addForm = document.getElementById("add_company_form");
      if (addForm) {
        addForm.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleAddCompany(e.target);
        });
      }

      const editForm = document.getElementById("edit_company_form");
      if (editForm) {
        editForm.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleEditCompany(e.target);
        });
      }

      // Filter buttons
      const statusFilter = document.getElementById("status_filter");
      if (statusFilter) {
        statusFilter.addEventListener("change", function () {
          self.currentStatus = this.value || null;
          self.currentPage = 1;
          self.loadCompanies();
        });
      }

      // Delete confirmation
      document.addEventListener("click", function (e) {
        if (e.target.classList.contains("btn-delete-company")) {
          const id = e.target.dataset.id;
          self.showDeleteConfirm(id);
        }
      });

      // Pagination
      document.addEventListener("click", function (e) {
        if (e.target.classList.contains("btn-page")) {
          self.currentPage = parseInt(e.target.dataset.page, 10);
          self.loadCompanies();
        }
      });

      // Edit button: populate form
      document.addEventListener("click", function (e) {
        if (e.target.classList.contains("btn-edit-company")) {
          const id = e.target.dataset.id;
          self.loadCompanyForEdit(id);
        }
      });
    },

    /**
     * Load and display companies list
     */
    loadCompanies: function () {
      const self = this;
      const params = new URLSearchParams({
        page: self.currentPage,
        per_page: PAGE_SIZE,
      });

      if (self.currentStatus) {
        params.append("status", self.currentStatus);
      }

      const tableBody = document.getElementById("companies_table_body");
      if (tableBody) {
        tableBody.innerHTML =
          '<tr><td colspan="8" class="text-center"><i class="ti ti-loader"></i> Loading...</td></tr>';
      }

      apiRequest("GET", API_BASE + "?" + params.toString(), null)
        .then(function (response) {
          if (response.success && response.data) {
            self.companies = response.data.companies || [];
            self.renderSummaryStats(response.data.stats || {});
            self.renderTable(response.data.companies || []);
            self.renderPagination(response.data.pagination || {});
          } else {
            throw new Error(
              "Invalid API response " +
                (response.error?.message || "unknown error")
            );
          }
        })
        .catch(function (err) {
          console.error("Failed to load companies:", err);
          if (tableBody) {
            tableBody.innerHTML =
              '<tr><td colspan="8" class="text-center text-danger">Failed to load companies</td></tr>';
          }
        });
    },

    /**
     * Render top summary stats
     */
    renderSummaryStats: function (stats) {
      const totalEl = document.getElementById("companies_total_count");
      const activeEl = document.getElementById("companies_active_count");
      const inactiveEl = document.getElementById("companies_inactive_count");
      const locationEl = document.getElementById("companies_location_count");

      if (totalEl) totalEl.textContent = String(stats.totalCompanies || 0);
      if (activeEl) activeEl.textContent = String(stats.activeCompanies || 0);
      if (inactiveEl)
        inactiveEl.textContent = String(stats.inactiveCompanies || 0);
      if (locationEl) locationEl.textContent = String(stats.locationCount || 0);
    },

    /**
     * Render companies into table
     */
    renderTable: function (companies) {
      const tableBody = document.getElementById("companies_table_body");
      if (!tableBody) return;

      if (companies.length === 0) {
        tableBody.innerHTML =
          '<tr><td colspan="8" class="text-center">No companies found</td></tr>';
        return;
      }

      let html = "";
      companies.forEach(function (company) {
        const owner = company.owner || {};
        const subscription = company.subscriptions?.[0] || {};
        const statusBadge =
          company.status === "active"
            ? '<span class="badge badge-success d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>Active</span>'
            : '<span class="badge badge-danger d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>Inactive</span>';

        html += `
          <tr>
            <td>
              <div class="form-check form-check-md">
                <input class="form-check-input" type="checkbox">
              </div>
            </td>
            <td>
              <div class="d-flex align-items-center file-name-icon">
                <div class="ms-2">
                  <h6 class="fw-medium">${esc(company.name)}</h6>
                  <p class="fs-12 fw-normal text-muted">${esc(company.code)}</p>
                </div>
              </div>
            </td>
            <td>${esc(owner.email || "-")}</td>
            <td>${esc(company.legal_name || "-")}</td>
            <td>
              <p class="mb-0">${esc(subscription.plan_code || "No Plan")}</p>
            </td>
            <td>${formatDate(company.created_at)}</td>
            <td>${statusBadge}</td>
            <td>
              <div class="action-icon d-inline-flex">
                <button class="btn-edit-company me-2" data-id="${company.id}" data-bs-toggle="modal" data-bs-target="#edit_company">
                  <i class="ti ti-edit"></i>
                </button>
                <button class="btn-delete-company" data-id="${company.id}">
                  <i class="ti ti-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        `;
      });

      tableBody.innerHTML = html;
    },

    /**
     * Render pagination controls
     */
    renderPagination: function (pagination) {
      const paginationContainer = document.getElementById(
        "companies_pagination"
      );
      if (!paginationContainer || !pagination.last_page) return;

      let html = '';
      const totalPages = pagination.last_page;

      // Previous button
      if (pagination.page > 1) {
        html += `<button class="btn-page me-1" data-page="${pagination.page - 1}">Previous</button>`;
      }

      // Page numbers
      for (let i = 1; i <= totalPages; i++) {
        if (i === pagination.page) {
          html += `<span class="page-indicator me-1">${i}</span>`;
        } else if (
          i === 1 ||
          i === totalPages ||
          Math.abs(i - pagination.page) <= 1
        ) {
          html += `<button class="btn-page me-1" data-page="${i}">${i}</button>`;
        } else if (i === 2 || i === totalPages - 1) {
          html += `<span class="me-1">...</span>`;
        }
      }

      // Next button
      if (pagination.page < totalPages) {
        html += `<button class="btn-page" data-page="${pagination.page + 1}">Next</button>`;
      }

      paginationContainer.innerHTML = html;
    },

    /**
     * Handle add company form submission
     */
    handleAddCompany: function (form) {
      const self = this;
      const formData = new FormData(form);
      const body = {
        code: formData.get("company_code"),
        name: formData.get("company_name"),
        legal_name: formData.get("company_legal_name"),
        status: formData.get("company_status"),
        timezone: formData.get("company_timezone"),
        currency: formData.get("company_currency"),
        country_code: formData.get("company_country"),
      };

      apiRequest("POST", API_BASE, body)
        .then(function (response) {
          if (response.success) {
            alert("Company created successfully");
            form.reset();
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("add_company")
            );
            if (modal) modal.hide();
            self.currentPage = 1;
            self.loadCompanies();
          } else {
            alert("Error: " + (response.error?.message || "unknown error"));
          }
        })
        .catch(function (err) {
          console.error("Failed to create company:", err);
          alert(
            "Error creating company: " +
              (err.data?.error?.message || err.message)
          );
        });
    },

    /**
     * Load company data for edit
     */
    loadCompanyForEdit: function (id) {
      const company = this.companies.find((c) => c.id === parseInt(id, 10));
      if (!company) {
        alert("Company not found");
        return;
      }

      document.getElementById("edit_company_id").value = company.id;
      document.getElementById("edit_company_code").value = company.code;
      document.getElementById("edit_company_name").value = company.name;
      document.getElementById("edit_company_legal_name").value =
        company.legal_name || "";
      document.getElementById("edit_company_status").value = company.status;
      document.getElementById("edit_company_timezone").value = company.timezone;
      document.getElementById("edit_company_currency").value = company.currency;
      document.getElementById("edit_company_country").value =
        company.country_code;
    },

    /**
     * Handle edit company form submission
     */
    handleEditCompany: function (form) {
      const self = this;
      const id = document.getElementById("edit_company_id").value;
      const formData = new FormData(form);

      const body = {
        code: formData.get("edit_company_code"),
        name: formData.get("edit_company_name"),
        legal_name: formData.get("edit_company_legal_name"),
        status: formData.get("edit_company_status"),
        timezone: formData.get("edit_company_timezone"),
        currency: formData.get("edit_company_currency"),
        country_code: formData.get("edit_company_country"),
      };

      apiRequest("PUT", API_BASE + "/" + id, body)
        .then(function (response) {
          if (response.success) {
            alert("Company updated successfully");
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("edit_company")
            );
            if (modal) modal.hide();
            self.loadCompanies();
          } else {
            alert("Error: " + (response.error?.message || "unknown error"));
          }
        })
        .catch(function (err) {
          console.error("Failed to update company:", err);
          alert(
            "Error updating company: " +
              (err.data?.error?.message || err.message)
          );
        });
    },

    /**
     * Show delete confirmation modal
     */
    showDeleteConfirm: function (id) {
      const self = this;
      const company = this.companies.find((c) => c.id === parseInt(id, 10));
      if (!company) {
        alert("Company not found");
        return;
      }

      document.getElementById("delete_company_name").textContent = company.name;
      document.getElementById("delete_confirm_btn").onclick = function () {
        self.handleDeleteCompany(id);
      };

      const modal = new bootstrap.Modal(document.getElementById("delete_modal"));
      modal.show();
    },

    /**
     * Handle delete company
     */
    handleDeleteCompany: function (id) {
      const self = this;

      apiRequest("DELETE", API_BASE + "/" + id, null)
        .then(function (response) {
          if (response.success) {
            alert("Company deleted successfully");
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("delete_modal")
            );
            if (modal) modal.hide();
            self.loadCompanies();
          } else {
            alert("Error: " + (response.error?.message || "unknown error"));
          }
        })
        .catch(function (err) {
          console.error("Failed to delete company:", err);
          alert(
            "Error deleting company: " +
              (err.data?.error?.message || err.message)
          );
        });
    },
  };

  // Initialize on DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      CompaniesManager.init();
    });
  } else {
    CompaniesManager.init();
  }

  // Export for global access
  window.CompaniesManager = CompaniesManager;
})(window, document);
