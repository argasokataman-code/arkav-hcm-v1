(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/dashboard";
  const PAGE_SIZE = 20;

  function redirectTo(url) {
    try {
      window.__ARCAV_LAST_REDIRECT__ = url;
    } catch (err) {
      console.warn("Failed to record redirect target", err);
    }

    if (window.location && typeof window.location.replace === "function") {
      window.location.replace(url);
    }
  }

  function redirectForAccessError(err) {
    const status = Number(err?.status || 0);
    const errorCode = String(err?.data?.error?.code || "");

    if (status === 401) {
      redirectTo("/lock-screen");
      return true;
    }

    if (status === 403 || errorCode === "ADMIN_REQUIRED") {
      redirectTo("/employee-dashboard");
      return true;
    }

    return false;
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
    const token = localStorage.getItem("saas_api_token");
    if (token) {
      headers["Authorization"] = "Bearer " + token;
    } else {
      console.warn("No API token available for request to " + url);
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
            return { success: false, error: { message: "Invalid JSON response" } };
          })
          .then(function (data) {
            if (!res.ok) {
              const errorMsg = data?.error?.message || data?.message || res.statusText;
              const error = {
                status: res.status,
                message: errorMsg,
                data: data,
              };

              redirectForAccessError(error);
              console.error("API error (" + res.status + "):", errorMsg, data);
              return Promise.reject(error);
            }
            return data;
          });
      })
      .catch(function (err) {
        console.error("API request failed:", method, url, err);
        throw err;
      });
  }

  // Get API token from server
  function getApiToken() {
    return new Promise(function (resolve, reject) {
      fetch("/api-token", {
        method: "GET",
        headers: {
          Accept: "application/json",
        },
        credentials: "include", // Changed from "same-origin" to "include"
      })
        .then(function (res) {
          // Handle redirects (302, etc.) - means not authenticated
          if (res.status === 302 || res.status === 401) {
            redirectTo("/lock-screen");
            reject(new Error("Not authenticated. Please login first."));
            return;
          }
          if (res.status === 403) {
            redirectTo("/employee-dashboard");
            reject(new Error("Admin access required."));
            return;
          }
          return res.json();
        })
        .then(function (data) {
          if (data && data.success && data.data && data.data.token) {
            localStorage.setItem("saas_api_token", data.data.token);
            resolve(data.data.token);
          } else {
            reject(new Error("Failed to get API token: " + JSON.stringify(data)));
          }
        })
        .catch(function (err) {
          console.error("Error getting API token:", err);
          reject(err);
        });
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

  // Format currency
  function formatCurrency(amount) {
    if (!amount) return "Rp 0";
    return "Rp " + parseInt(amount).toLocaleString("id-ID");
  }

  // Format percentage
  function formatPercentage(value) {
    return (parseFloat(value) || 0).toFixed(2) + "%";
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

  // Main DashboardManager object
  const DashboardManager = {
    currentPage: 1,
    totalPages: 1,
    kpis: {},
    companies: [],
    auditLogs: [],
    currentFilter: "all",

    /**
     * Initialize the dashboard
     */
    init: function () {
      const self = this;
      
      // First, get the API token
      getApiToken()
        .then(function () {
          self.bindEvents();
          self.loadDashboard();
        })
        .catch(function (err) {
          const errorMsg = err?.message || "Failed to initialize dashboard";
          console.error("Dashboard init error:", err);
          self.showError(errorMsg);
          
          // Show loading placeholders as error indicators
          document.querySelectorAll("#subscription_status, #revenue_by_plan").forEach(function(el) {
            el.innerHTML = '<p class="text-danger"><strong>Error:</strong> ' + esc(errorMsg) + '</p>';
          });
        });
    },

    /**
     * Load subscription status breakdown
     */
    loadSubscriptionStatus: function () {
      const self = this;
      const url = API_BASE + "/subscriptions/status";

      return apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.renderSubscriptionStatus(response.data);
          } else {
            const container = document.getElementById("subscription_status");
            if (container) container.innerHTML = '<p class="text-danger">No subscription data or error in response</p>';
          }
        })
        .catch(function (err) {
          console.error("Error loading subscription status:", err);
          const container = document.getElementById("subscription_status");
          if (container) {
            const errorMsg = err?.message || JSON.stringify(err);
            container.innerHTML = '<p class="text-danger">Error: ' + esc(errorMsg) + '</p>';
          }
        });
    },

    /**
     * Render subscription status breakdown
     */
    renderSubscriptionStatus: function (data) {
      const container = document.getElementById("subscription_status");
      if (!container) return;

      if (!data || Object.keys(data).length === 0) {
        container.innerHTML = '<p class="text-muted">No subscription data available</p>';
        return;
      }

      let html = '<div class="table-responsive"><table class="table table-sm"><tbody>';
      for (const [status, info] of Object.entries(data)) {
        const badgeColor = status === 'active' ? 'success' : status === 'cancelled' ? 'danger' : 'warning';
        html += `
          <tr>
            <td><span class="badge bg-${badgeColor}">${esc(status.charAt(0).toUpperCase() + status.slice(1))}</span></td>
            <td class="text-end"><strong>${info.count || 0}</strong> subscriptions</td>
            <td class="text-end">${formatCurrency(info.revenue || 0)}</td>
          </tr>
        `;
      }
      html += "</tbody></table></div>";
      container.innerHTML = html;
    },

    /**
     * Load revenue by plan breakdown
     */
    loadRevenueByPlan: function () {
      const self = this;
      const url = API_BASE + "/revenue/by-plan";

      return apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.renderRevenueByPlan(response.data);
          } else {
            const container = document.getElementById("revenue_by_plan");
            if (container) container.innerHTML = '<p class="text-danger">No plan data or error in response</p>';
          }
        })
        .catch(function (err) {
          console.error("Error loading revenue by plan:", err);
          const container = document.getElementById("revenue_by_plan");
          if (container) {
            const errorMsg = err?.message || JSON.stringify(err);
            container.innerHTML = '<p class="text-danger">Error: ' + esc(errorMsg) + '</p>';
          }
        });
    },

    /**
     * Render revenue by plan
     */
    renderRevenueByPlan: function (data) {
      const container = document.getElementById("revenue_by_plan");
      if (!container) return;

      if (!data || !Array.isArray(data) || data.length === 0) {
        container.innerHTML = '<p class="text-muted">No plan data available</p>';
        return;
      }

      let html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Plan</th><th class="text-end">Subscriptions</th><th class="text-end">Revenue</th></tr></thead><tbody>';
      data.forEach((plan) => {
        html += `
          <tr>
            <td><strong>${esc(plan.packageName || 'Unknown')}</strong></td>
            <td class="text-end">${plan.subscriptionCount || 0}</td>
            <td class="text-end">${formatCurrency(plan.revenue || 0)}</td>
          </tr>
        `;
      });
      html += "</tbody></table></div>";
      container.innerHTML = html;
    },

    /**
     * Load top performing companies
     */
    loadTopCompanies: function () {
      const self = this;
      const url = API_BASE + "/companies/top-performers";

      return apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data && response.data.length > 0) {
            self.topCompanies = response.data;
            self.renderTopCompanies();
          }
        })
        .catch(function (err) {
          console.error("Error loading top companies:", err);
        });
    },

    /**
     * Render top companies widget
     */
    renderTopCompanies: function () {
      const kpiContainer = document.getElementById("kpi_container");
      if (!kpiContainer || !this.topCompanies || this.topCompanies.length === 0) return;

      // Find and update or create top companies card
      let topCompaniesCard = document.getElementById("top_companies_kpi");
      if (!topCompaniesCard) {
        topCompaniesCard = document.createElement("div");
        topCompaniesCard.id = "top_companies_kpi";
        topCompaniesCard.className = "col-lg-3 col-md-6 d-flex";
        kpiContainer.appendChild(topCompaniesCard);
      }

      const topCompany = this.topCompanies[0];
      topCompaniesCard.innerHTML = `
        <div class="card flex-fill">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <p class="fs-12 fw-medium mb-1">Top Company</p>
                <h6 class="mb-0">${esc(topCompany.name)}</h6>
                <small class="text-muted">${formatCurrency(topCompany.totalRevenue)}</small>
              </div>
              <span class="avatar avatar-lg bg-primary flex-shrink-0">
                <i class="ti ti-star fs-16"></i>
              </span>
            </div>
          </div>
        </div>
      `;
    },

    /**
     * Load user statistics
     */
    loadUserStats: function () {
      const self = this;
      const url = API_BASE + "/users";

      return apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.userStats = response.data;
            self.renderUserStats();
          }
        })
        .catch(function (err) {
          console.error("Error loading user stats:", err);
        });
    },

    /**
     * Render user statistics
     */
    renderUserStats: function () {
      const kpiContainer = document.getElementById("kpi_container");
      if (!kpiContainer || !this.userStats) return;

      let userStatsCard = document.getElementById("user_stats_kpi");
      if (!userStatsCard) {
        userStatsCard = document.createElement("div");
        userStatsCard.id = "user_stats_kpi";
        userStatsCard.className = "col-lg-3 col-md-6 d-flex";
        kpiContainer.appendChild(userStatsCard);
      }

      userStatsCard.innerHTML = `
        <div class="card flex-fill">
          <div class="card-body">
            <p class="fs-12 fw-medium mb-2">User Verification Rate</p>
            <div class="progress mb-2" style="height: 6px;">
              <div class="progress-bar bg-success" style="width: ${this.userStats.verificationRate || 0}%"></div>
            </div>
            <small class="text-muted">
              ${this.userStats.verifiedUsers || 0} verified / ${this.userStats.totalUsers || 0} total
              (${formatPercentage(this.userStats.verificationRate || 0)})
            </small>
            <p class="mt-2 mb-0 fs-12">
              <span class="badge bg-light text-dark">${this.userStats.newUsersThisMonth || 0} new this month</span>
            </p>
          </div>
        </div>
      `;
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      // Audit filter dropdown change
      const auditFilterSelect = document.getElementById("audit_filter_select");
      if (auditFilterSelect) {
        auditFilterSelect.addEventListener("change", function () {
          self.currentFilter = this.value;
          self.currentPage = 1;
          self.loadAuditLogs();
        });
      }

      // Tab switching
      document.addEventListener("click", function (e) {
        if (e.target.matches("[data-dashboard-tab]")) {
          e.preventDefault();
          const tab = e.target.getAttribute("data-dashboard-tab");
          self.switchTab(tab);
        }

        // Pagination
        if (e.target.matches("[data-page]")) {
          e.preventDefault();
          const page = parseInt(e.target.getAttribute("data-page"));
          self.currentPage = page;
          self.loadCompanies();
        }

        // Audit filter
        if (e.target.matches("[data-audit-filter]")) {
          e.preventDefault();
          const filter = e.target.getAttribute("data-audit-filter");
          self.currentFilter = filter;
          self.currentPage = 1;
          self.loadAuditLogs();
        }

        // View metric trend
        if (e.target.matches("[data-metric-trend]")) {
          e.preventDefault();
          const metric = e.target.getAttribute("data-metric-trend");
          self.showMetricTrend(metric);
        }

        // View company details
        if (e.target.matches("[data-view-company]")) {
          e.preventDefault();
          const companyId = e.target.getAttribute("data-view-company");
          self.showCompanyDetails(companyId);
        }
      });
    },

    /**
     * Load complete dashboard data
     */
    loadDashboard: function () {
      const self = this;

      Promise.all([
        this.loadKPIs(),
        this.loadCompanies(),
        this.loadAuditLogs(),
        this.loadRevenueData(),
        this.loadSubscriptionStatus(),
        this.loadRevenueByPlan(),
        this.loadTopCompanies(),
        this.loadUserStats(),
      ])
        .then(() => {
          self.renderDashboard();
        })
        .catch((err) => {
          console.error("Error loading dashboard:", err);
          self.showError("Error loading dashboard data");
        });
    },

    /**
     * Load KPIs
     */
    loadKPIs: function () {
      const self = this;
      const url = API_BASE + "/kpi";

      return apiRequest("GET", url, null).then(function (response) {
        if (response.success && response.data) {
          self.kpis = response.data;
          self.renderKPIs();
        } else {
          self.showError("Failed to load KPIs");
        }
      });
    },

    /**
     * Render KPI cards
     */
    renderKPIs: function () {
      const kpiContainer = document.getElementById("kpi_container");
      if (!kpiContainer) return;

      kpiContainer.innerHTML = `
        <div class="col-lg-3 col-md-6 d-flex">
          <div class="card flex-fill">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="fs-12 fw-medium mb-1">Total Companies</p>
                  <h4>${this.kpis.totalCompanies || 0}</h4>
                </div>
                <span class="avatar avatar-lg bg-primary flex-shrink-0">
                  <i class="ti ti-building fs-16"></i>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 d-flex">
          <div class="card flex-fill">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="fs-12 fw-medium mb-1">Total Users</p>
                  <h4>${this.kpis.totalUsers || 0}</h4>
                </div>
                <span class="avatar avatar-lg bg-info flex-shrink-0">
                  <i class="ti ti-users fs-16"></i>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 d-flex">
          <div class="card flex-fill">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="fs-12 fw-medium mb-1">Monthly Revenue (MRR)</p>
                  <h5>${formatCurrency(this.kpis.mrr || 0)}</h5>
                </div>
                <span class="avatar avatar-lg bg-success flex-shrink-0">
                  <i class="ti ti-trending-up fs-16"></i>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 d-flex">
          <div class="card flex-fill">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="fs-12 fw-medium mb-1">Annual Revenue (ARR)</p>
                  <h5>${formatCurrency(this.kpis.arr || 0)}</h5>
                </div>
                <span class="avatar avatar-lg bg-warning flex-shrink-0">
                  <i class="ti ti-chart-bar fs-16"></i>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 d-flex">
          <div class="card flex-fill">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="fs-12 fw-medium mb-1">Active Subscriptions</p>
                  <h4>${this.kpis.activeSubscriptions || 0}</h4>
                </div>
                <span class="avatar avatar-lg bg-secondary flex-shrink-0">
                  <i class="ti ti-receipt fs-16"></i>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 d-flex">
          <div class="card flex-fill">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="fs-12 fw-medium mb-1">Churn Rate</p>
                  <h4>${formatPercentage(this.kpis.churnRate || 0)}</h4>
                </div>
                <span class="avatar avatar-lg bg-danger flex-shrink-0">
                  <i class="ti ti-alert-triangle fs-16"></i>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 d-flex">
          <div class="card flex-fill">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="fs-12 fw-medium mb-1">CLV</p>
                  <h5>${formatCurrency(this.kpis.customerLifetimeValue || 0)}</h5>
                </div>
                <span class="avatar avatar-lg bg-secondary flex-shrink-0">
                  <i class="ti ti-coin fs-16"></i>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-3 col-md-6 d-flex">
          <div class="card flex-fill">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="fs-12 fw-medium mb-1">NRR</p>
                  <h4>${formatPercentage(this.kpis.netRevenueRetention || 0)}</h4>
                </div>
                <span class="avatar avatar-lg bg-success flex-shrink-0">
                  <i class="ti ti-trending-up fs-16"></i>
                </span>
              </div>
            </div>
          </div>
        </div>
      `;
    },

    /**
     * Load companies
     */
    loadCompanies: function () {
      const self = this;
      const url =
        API_BASE +
        "/companies?page=" +
        this.currentPage +
        "&per_page=" +
        PAGE_SIZE;

      return apiRequest("GET", url, null).then(function (response) {
        if (response.success && response.data) {
          self.companies = response.data;
          self.totalPages = response.pagination ? response.pagination.last_page : 1;
          self.renderCompanies();
        } else {
          self.showError("Failed to load companies");
        }
      });
    },

    /**
     * Render companies table
     */
    renderCompanies: function () {
      const tbody = document.querySelector("#companies_table tbody");
      if (!tbody) return;

      tbody.innerHTML = "";

      if (this.companies.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="5" class="text-center py-3">No companies found</td></tr>';
        return;
      }

      this.companies.forEach((company) => {
        const row = document.createElement("tr");
        row.innerHTML = `
          <td><strong>${esc(company.name)}</strong></td>
          <td>${company.userCount || 0}</td>
          <td>${company.subscriptionCount || 0}</td>
          <td>${formatCurrency(company.totalRevenue || 0)}</td>
          <td>
            <button class="btn btn-sm btn-info" data-view-company="${company.id}" title="View Details">
              <i class="ti ti-eye"></i>
            </button>
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
     * Load revenue data
     */
    loadRevenueData: function () {
      const self = this;
      const url = API_BASE + "/revenue/monthly";

      return apiRequest("GET", url, null).then(function (response) {
        if (response.success && response.data) {
          self.renderRevenueChart(response.data);
        } else {
          self.showError("Failed to load revenue data");
        }
      });
    },

    /**
     * Render revenue chart
     */
    renderRevenueChart: function (data) {
      const container = document.getElementById("revenue_chart");
      if (!container) return;

      let html = '<div class="table-responsive"><table class="table"><tbody>';
      data.forEach((item) => {
        html += `
          <tr>
            <td>${formatDate(item.month)}</td>
            <td>${formatCurrency(item.mrr)}</td>
          </tr>
        `;
      });
      html += "</tbody></table></div>";
      container.innerHTML = html;
    },

    /**
     * Load audit logs
     */
    loadAuditLogs: function () {
      const self = this;
      let url = API_BASE + "/audit-logs?page=1&per_page=" + PAGE_SIZE;

      if (self.currentFilter && self.currentFilter !== "all") {
        url += "&action=" + encodeURIComponent(self.currentFilter);
      }

      return apiRequest("GET", url, null).then(function (response) {
        if (response.success && response.data) {
          self.auditLogs = response.data;
          self.renderAuditLogs();
        } else {
          self.showError("Failed to load audit logs");
        }
      });
    },

    /**
     * Render audit logs
     */
    renderAuditLogs: function () {
      const tbody = document.querySelector("#audit_logs_table tbody");
      if (!tbody) return;

      tbody.innerHTML = "";

      if (this.auditLogs.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="5" class="text-center py-3">No audit logs found</td></tr>';
        return;
      }

      this.auditLogs.forEach((log) => {
        const row = document.createElement("tr");
        row.innerHTML = `
          <td>${esc(log.superAdminName)}</td>
          <td><span class="badge bg-info">${esc(log.actionLabel)}</span></td>
          <td>${esc(log.targetType)}</td>
          <td>${formatDate(log.createdAt)}</td>
          <td class="text-muted small">${esc(log.ipAddress)}</td>
        `;
        tbody.appendChild(row);
      });
    },

    /**
     * Show metric trend modal
     */
    showMetricTrend: function (metricKey) {
      const self = this;
      const url = API_BASE + "/kpi/" + metricKey;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            const data = response.data;
            let html = `
              <div class="mb-3">
                <p><strong>Current Value:</strong> ${formatCurrency(data.currentValue)}</p>
              </div>
              <h6>12-Month Trend:</h6>
              <div class="table-responsive">
                <table class="table table-sm">
                  <tbody>
            `;
            data.trend.forEach((item) => {
              html += `
                <tr>
                  <td>${formatDate(item.date)}</td>
                  <td>${formatCurrency(item.value)}</td>
                </tr>
              `;
            });
            html += `
                  </tbody>
                </table>
              </div>
            `;
            document.getElementById("trend_content").innerHTML = html;
            const modal = new bootstrap.Modal(
              document.getElementById("trend_modal")
            );
            modal.show();
          } else {
            self.showError("Failed to load metric trend");
          }
        })
        .catch((err) => {
          console.error(err);
          self.showError("Error loading metric trend");
        });
    },

    /**
     * Show company details modal
     */
    showCompanyDetails: function (companyId) {
      const self = this;
      const url = API_BASE + "/companies/" + companyId + "/details";

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.renderCompanyDetailsModal(response.data);
          } else {
            self.showError("Failed to load company details");
          }
        })
        .catch((err) => {
          console.error(err);
          self.showError("Error loading company details");
        });
    },

    /**
     * Render company details modal
     */
    renderCompanyDetailsModal: function (company) {
      // Create or find modal
      let modal = document.getElementById("company_details_modal");
      if (!modal) {
        modal = document.createElement("div");
        modal.id = "company_details_modal";
        modal.className = "modal fade";
        modal.setAttribute("tabindex", "-1");
        document.body.appendChild(modal);
      }

      let html = `
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Company Details: ${esc(company.name)}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="row mb-3">
                <div class="col-md-6">
                  <p><strong>Company Code:</strong> ${esc(company.code)}</p>
                  <p><strong>Email:</strong> ${esc(company.email)}</p>
                  <p><strong>Country:</strong> ${esc(company.country || "-")}</p>
                  <p><strong>Industry:</strong> ${esc(company.industry || "-")}</p>
                </div>
                <div class="col-md-6">
                  <p><strong>Users:</strong> ${company.userCount || 0}</p>
                  <p><strong>Active Subscriptions:</strong> ${company.activeSubscriptions || 0}</p>
                  <p><strong>Total Revenue:</strong> ${formatCurrency(company.totalRevenue || 0)}</p>
                  <p><strong>Created:</strong> ${formatDate(company.createdAt)}</p>
                </div>
              </div>

              <h6>Subscription Status Breakdown:</h6>
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Status</th>
                      <th class="text-end">Count</th>
                      <th class="text-end">Revenue</th>
                    </tr>
                  </thead>
                  <tbody>
      `;

      for (const [status, info] of Object.entries(company.subscriptionsByStatus || {})) {
        html += `
          <tr>
            <td><span class="badge bg-info">${esc(status.charAt(0).toUpperCase() + status.slice(1))}</span></td>
            <td class="text-end">${info.count || 0}</td>
            <td class="text-end">${formatCurrency(info.revenue || 0)}</td>
          </tr>
        `;
      }

      html += `
                  </tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      `;

      modal.innerHTML = html;
      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();
    },

    /**
     * Switch dashboard tab
     */
    switchTab: function (tab) {
      // Hide all tabs
      document.querySelectorAll(".dashboard-tab").forEach((el) => {
        el.style.display = "none";
      });

      // Show selected tab
      const selectedTab = document.getElementById("tab_" + tab);
      if (selectedTab) {
        selectedTab.style.display = "block";
      }

      // Update active tab button
      document.querySelectorAll("[data-dashboard-tab]").forEach((btn) => {
        btn.classList.remove("active");
      });
      document
        .querySelector('[data-dashboard-tab="' + tab + '"]')
        ?.classList.add("active");
    },

    /**
     * Render dashboard
     */
    renderDashboard: function () {
      console.log("Dashboard rendered with all data");
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
        ${esc(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;
      document.body.appendChild(alertDiv);
      setTimeout(() => alertDiv.remove(), 5000);
    },
  };

  // Initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      DashboardManager.init();
    });
  } else {
    DashboardManager.init();
  }

  // Expose to global scope
  window.DashboardManager = DashboardManager;
})(window, document);
