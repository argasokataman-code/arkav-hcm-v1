(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/packages";
  const API_ADDONS_BASE = "/v1/saas/package-addons";
  const PAGE_SIZE = 10;
  let apiToken = null;
  const FEATURE_LIBRARY = [
    {
      module: "employee",
      title: "Employee Management",
      description: "Master data karyawan, struktur organisasi, dan administrasi HR dasar.",
      features: [
        { code: "employee_management", name: "Employee Directory", description: "List, profile, dan pencarian data karyawan." },
        { code: "employee_bulk_import", name: "Bulk Import", description: "Upload massal data employee via template." },
        { code: "employee_document_center", name: "Document Center", description: "Dokumen personal, kontrak, dan arsip employee." },
        { code: "employee_lifecycle", name: "Lifecycle Tracking", description: "Onboarding, mutation, promotion, sampai exit." },
      ],
    },
    {
      module: "attendance",
      title: "Attendance",
      description: "Tracking kehadiran, shift, timesheet, dan koreksi absensi.",
      features: [
        { code: "attendance", name: "Attendance Dashboard", description: "Dashboard check in/out harian untuk employee." },
        { code: "attendance_shift_scheduling", name: "Shift Scheduling", description: "Atur shift dan jam kerja tim." },
        { code: "attendance_geo_tracking", name: "Geo Tracking", description: "Capture koordinat saat punch in/out." },
        { code: "attendance_correction_flow", name: "Correction Workflow", description: "Ajukan dan approve koreksi absensi." },
      ],
    },
    {
      module: "payroll",
      title: "Payroll",
      description: "Komponen gaji, proses payroll, dan distribusi slip gaji.",
      features: [
        { code: "payroll", name: "Payroll Run", description: "Generate payroll periodik bulanan." },
        { code: "payroll_components", name: "Salary Components", description: "Atur allowance, deduction, dan formula dasar." },
        { code: "payroll_payslip", name: "Payslip Publishing", description: "Publikasi slip gaji digital ke employee." },
        { code: "payroll_thr", name: "THR Management", description: "Perhitungan dan approval THR periodik." },
      ],
    },
    {
      module: "leave",
      title: "Leave Management",
      description: "Pengajuan cuti/izin/sakit beserta policy dan approval.",
      features: [
        { code: "leave_management", name: "Leave Requests", description: "Pengajuan cuti, izin, sakit dari employee." },
        { code: "leave_approval_flow", name: "Approval Workflow", description: "Approval berjenjang manager hingga HR." },
        { code: "leave_balance_ledger", name: "Leave Balance Ledger", description: "Monitoring saldo dan mutasi cuti." },
        { code: "holiday_calendar", name: "Holiday Calendar", description: "Kelola hari libur nasional dan perusahaan." },
      ],
    },
    {
      module: "performance",
      title: "Performance",
      description: "KPI, goals, penilaian performa, dan feedback cycle.",
      features: [
        { code: "performance", name: "Performance Review", description: "Review performa periodik per employee." },
        { code: "performance_goal_tracking", name: "Goal Tracking", description: "Objective/KPI tracking lintas periode." },
        { code: "performance_calibration", name: "Calibration Panel", description: "Panel kalibrasi penilaian tim/department." },
      ],
    },
    {
      module: "platform",
      title: "Platform & Integrations",
      description: "Kontrol akses API, integrasi, dan support operasional.",
      features: [
        { code: "api_access", name: "API Access", description: "Akses endpoint integrasi public/internal." },
        { code: "sso_basic", name: "SSO Basic", description: "Single Sign On via provider umum." },
        { code: "audit_logs", name: "Audit Logs", description: "Riwayat aktivitas penting untuk compliance." },
        { code: "priority_support", name: "Priority Support", description: "Jalur support prioritas dengan SLA khusus." },
      ],
    },
  ];

  function getDefaultFeatureCatalog() {
    return FEATURE_LIBRARY.flatMap(function (group) {
      return (group.features || []).map(function (feature) {
        return feature.code;
      });
    });
  }

  const DEFAULT_FEATURE_CATALOG = getDefaultFeatureCatalog();

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

  // Format currency
  function formatCurrency(amount) {
    if (!amount) return "Rp 0";
    return "Rp " + parseInt(amount).toLocaleString("id-ID");
  }

  // Main PackagesManager object
  const PackagesManager = {
    isInitialized: false,
    currentPage: 1,
    totalPages: 1,
    packages: [],
    addons: [],
    currentEditId: null,
    currentAddonEditId: null,
    currentStatus: "all",
    currentSearch: "",
    currentAddonPage: 1,
    totalAddonPages: 1,
    addonStatus: "all",
    addonSearch: "",
    packageModalInstance: null,
    addonModalInstance: null,

    /**
     * Initialize the packages list page
     */
    init: function () {
      if (this.isInitialized) {
        return;
      }
      this.isInitialized = true;

      this.bindEvents();
      this.renderFeatureCatalog(DEFAULT_FEATURE_CATALOG);
      this.packageModalInstance = window.bootstrap
        ? window.bootstrap.Modal.getOrCreateInstance(document.getElementById("packageModal"))
        : null;
      this.addonModalInstance = window.bootstrap
        ? window.bootstrap.Modal.getOrCreateInstance(document.getElementById("addonModal"))
        : null;
      this.loadPackages();
      this.loadAddons();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      const form = document.getElementById("packageForm");
      if (form) {
        form.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleSavePackage(e.target);
        });
      }

      const addBtn = document.getElementById("btn_add_package");
      if (addBtn) {
        addBtn.addEventListener("click", function () {
          self.openCreateModal();
        });
      }

      const addAddonBtn = document.getElementById("btn_add_addon");
      if (addAddonBtn) {
        addAddonBtn.addEventListener("click", function () {
          self.openCreateAddonModal();
        });
      }

      const statusFilter = document.getElementById("filter_status");
      if (statusFilter) {
        statusFilter.addEventListener("change", function () {
          self.currentStatus = this.value || "all";
          self.currentPage = 1;
          self.loadPackages();
        });
      }

      const searchInput = document.getElementById("search_packages");
      if (searchInput) {
        let searchDebounceTimer = null;
        searchInput.addEventListener("input", function () {
          const nextValue = String(this.value || "").trim();
          window.clearTimeout(searchDebounceTimer);
          searchDebounceTimer = window.setTimeout(function () {
            self.currentSearch = nextValue;
            self.currentPage = 1;
            self.loadPackages();
          }, 250);
        });
      }

      const resetBtn = document.getElementById("btn_reset_filters");
      if (resetBtn) {
        resetBtn.addEventListener("click", function () {
          const select = document.getElementById("filter_status");
          if (select) {
            select.value = "all";
          }
          const search = document.getElementById("search_packages");
          if (search) {
            search.value = "";
          }
          self.currentStatus = "all";
          self.currentSearch = "";
          self.currentPage = 1;
          self.loadPackages();
        });
      }

      const featureSearchInput = document.getElementById("input_package_feature_search");
      if (featureSearchInput) {
        featureSearchInput.addEventListener("input", function () {
          self.filterFeatureCatalog(String(this.value || ""));
        });
      }

      const selectVisibleBtn = document.querySelector("[data-feature-select-visible]");
      if (selectVisibleBtn) {
        selectVisibleBtn.addEventListener("click", function () {
          self.toggleVisibleFeatures(true);
        });
      }

      const clearAllBtn = document.querySelector("[data-feature-clear-all]");
      if (clearAllBtn) {
        clearAllBtn.addEventListener("click", function () {
          self.toggleVisibleFeatures(false, true);
        });
      }

      // Pagination buttons
      document.addEventListener("click", function (e) {
        const pageLink = e.target.closest("[data-page]");
        if (pageLink) {
          e.preventDefault();
          const page = parseInt(pageLink.getAttribute("data-page"), 10);
          self.currentPage = page;
          self.loadPackages();
        }

        const addAddonBtn = e.target.closest("#btn_add_addon");
        if (addAddonBtn) {
          e.preventDefault();
          self.openCreateAddonModal();
        }

        const addonPageLink = e.target.closest("[data-addon-page]");
        if (addonPageLink) {
          e.preventDefault();
          const page = parseInt(addonPageLink.getAttribute("data-addon-page"), 10);
          self.currentAddonPage = page;
          self.loadAddons();
        }

        const editBtn = e.target.closest("[data-edit-package]");
        if (editBtn) {
          e.preventDefault();
          const id = editBtn.getAttribute("data-edit-package");
          self.editPackage(id);
        }

        const deleteBtn = e.target.closest("[data-delete-package]");
        if (deleteBtn) {
          e.preventDefault();
          const id = deleteBtn.getAttribute("data-delete-package");
          self.deletePackage(id);
        }

        const viewFeatureBtn = e.target.closest("[data-view-features]");
        if (viewFeatureBtn) {
          e.preventDefault();
          const id = viewFeatureBtn.getAttribute("data-view-features");
          self.showFeaturesModal(id);
        }

        const editAddonBtn = e.target.closest("[data-edit-addon]");
        if (editAddonBtn) {
          e.preventDefault();
          const id = editAddonBtn.getAttribute("data-edit-addon");
          self.editAddon(id);
        }

        const deleteAddonBtn = e.target.closest("[data-delete-addon]");
        if (deleteAddonBtn) {
          e.preventDefault();
          const id = deleteAddonBtn.getAttribute("data-delete-addon");
          self.deleteAddon(id);
        }

        const featureCheckbox = e.target.closest("input[type='checkbox'][name='package_feature_codes']");
        if (featureCheckbox) {
          self.updateFeatureSelectionSummary();
        }
      });

      const addonForm = document.getElementById("addonForm");
      if (addonForm) {
        addonForm.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleSaveAddon(e.target);
        });
      }
    },

    /**
     * Load packages from API
     */
    loadPackages: function () {
      const self = this;
      const params = new URLSearchParams({
        page: String(this.currentPage),
        per_page: String(PAGE_SIZE),
        status: this.currentStatus || "all",
      });

      if (this.currentSearch) {
        params.set("search", this.currentSearch);
      }

      const url = API_BASE + "?" + params.toString();

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.packages = response.data || [];
            self.totalPages = response.pagination ? response.pagination.last_page : 1;
            self.syncFeatureCatalogFromPackages(response.data || []);
            self.renderPackages();
          } else {
            self.showError("Failed to load packages");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading packages");
        });
    },

    /**
     * Load package add-ons from API
     */
    loadAddons: function () {
      const self = this;
      const params = new URLSearchParams({
        page: String(this.currentAddonPage),
        per_page: String(PAGE_SIZE),
        status: this.addonStatus || "all",
      });

      if (this.addonSearch) {
        params.set("search", this.addonSearch);
      }

      apiRequest("GET", API_ADDONS_BASE + "?" + params.toString(), null)
        .then(function (response) {
          if (response.success && response.data) {
            self.addons = response.data || [];
            self.totalAddonPages = response.pagination ? response.pagination.last_page : 1;
            self.renderAddons();
          } else {
            self.showError("Failed to load package add-ons");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading package add-ons");
        });
    },

    /**
     * Render packages table
     */
    renderPackages: function () {
      const container = document.querySelector("[data-packages-list-container]");
      if (!container) return;

      let html = '';
      if (this.packages.length === 0) {
        html = '<div class="card"><div class="card-body text-center text-muted py-4">No packages found</div></div>';
      } else {
        html = `
          <div class="card">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Package Name</th>
                    <th>Price</th>
                    <th>Billing Unit</th>
                    <th>Status</th>
                    <th>Features</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${this.packages.map(pkg => `
                    <tr>
                      <td>
                        <h6 class="fw-medium mb-0">${esc(pkg.name)}</h6>
                        <span class="fs-12 text-muted">${esc(pkg.code || "-")}</span>
                      </td>
                      <td>
                        <span class="d-block">${formatCurrency(pkg.monthlyPrice)}</span>
                        <small class="text-muted">Yearly: ${formatCurrency(pkg.yearlyPrice)}</small>
                      </td>
                      <td>${esc(pkg.billingUnit || "-")}</td>
                      <td>
                        <span class="badge ${pkg.status === "active" ? "badge-success" : pkg.status === "inactive" ? "badge-warning" : "badge-danger"} d-inline-flex align-items-center badge-xs">
                          <i class="ti ti-point-filled me-1"></i>${esc(pkg.status)}
                        </span>
                      </td>
                      <td>
                        <div class="d-flex flex-wrap gap-1">
                          ${(pkg.features || [])
                            .map((f) => `
                              <span class="badge bg-light text-dark small">
                                ${esc(f.name || f.code || f)}
                              </span>
                            `)
                            .join('') || '<span class="text-muted fs-12">No features</span>'}
                        </div>
                      </td>
                      <td>
                        <div class="action-icon d-inline-flex">
                          <button class="btn btn-icon btn-sm me-2" data-edit-package="${pkg.id}" title="Edit">
                            <i class="ti ti-edit"></i>
                          </button>
                          <button class="btn btn-icon btn-sm me-2" data-view-features="${pkg.id}" title="View Features">
                            <i class="ti ti-list-details"></i>
                          </button>
                          <button class="btn btn-icon btn-sm" data-delete-package="${pkg.id}" title="Delete">
                            <i class="ti ti-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
              <small class="text-muted">Showing ${this.packages.length} packages</small>
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0" data-package-pagination>
                <!--Pagination-->
                </ul>
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
      const container = document.querySelector("[data-package-pagination]");
      if (!container) return;

      container.innerHTML = "";

      if (this.currentPage > 1) {
        const li = document.createElement("li");
        li.className = "page-item";
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${
          this.currentPage - 1
        }">Previous</a>`;
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
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${
          this.currentPage + 1
        }">Next</a>`;
        container.appendChild(li);
      }
    },

    /**
     * Render add-ons table
     */
    renderAddons: function () {
      const container = document.querySelector("[data-package-addons-list-container]");
      if (!container) return;

      let html = `
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-0">Package Add-ons</h5>
              <small class="text-muted">Global add-on catalog for pricing extras</small>
            </div>
            <button class="btn btn-sm btn-primary" id="btn_add_addon">
              <i class="ti ti-circle-plus me-1"></i>Add Add-on
            </button>
          </div>
          ${this.addons.length === 0
            ? '<div class="card-body text-center text-muted py-4">No package add-ons found</div>'
            : `
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Price / Unit</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${this.addons.map((addon) => `
                    <tr>
                      <td>
                        <div class="fw-medium">${esc(addon.code)}</div>
                        <small class="text-muted">${esc(addon.description || "-")}</small>
                      </td>
                      <td>${esc(addon.name)}</td>
                      <td>${formatCurrency(addon.pricePerUnit)}</td>
                      <td>${esc(addon.unitName || "-")}</td>
                      <td>
                        <span class="badge ${addon.status === "active" ? "badge-success" : "badge-warning"} d-inline-flex align-items-center badge-xs">
                          <i class="ti ti-point-filled me-1"></i>${esc(addon.status)}
                        </span>
                      </td>
                      <td>
                        <div class="action-icon d-inline-flex">
                          <button class="btn btn-icon btn-sm me-2" data-edit-addon="${addon.id}" title="Edit">
                            <i class="ti ti-edit"></i>
                          </button>
                          <button class="btn btn-icon btn-sm" data-delete-addon="${addon.id}" title="Delete">
                            <i class="ti ti-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          `}
          <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing ${this.addons.length} add-ons</small>
            <nav aria-label="Add-on page navigation">
              <ul class="pagination pagination-sm mb-0" data-addon-pagination></ul>
            </nav>
          </div>
        </div>
      `;

      container.innerHTML = html;
      this.renderAddonPagination();
    },

    /**
     * Render add-on pagination
     */
    renderAddonPagination: function () {
      const container = document.querySelector("[data-addon-pagination]");
      if (!container) return;

      container.innerHTML = "";

      if (this.currentAddonPage > 1) {
        const li = document.createElement("li");
        li.className = "page-item";
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-addon-page="${this.currentAddonPage - 1}">Previous</a>`;
        container.appendChild(li);
      }

      for (let i = 1; i <= this.totalAddonPages; i++) {
        const li = document.createElement("li");
        li.className = "page-item" + (i === this.currentAddonPage ? " active" : "");
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-addon-page="${i}">${i}</a>`;
        container.appendChild(li);
      }

      if (this.currentAddonPage < this.totalAddonPages) {
        const li = document.createElement("li");
        li.className = "page-item";
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-addon-page="${this.currentAddonPage + 1}">Next</a>`;
        container.appendChild(li);
      }
    },

    handleSavePackage: function (form) {
      const self = this;
      const name = (document.getElementById("input_package_name")?.value || "").trim();
      const description = (document.getElementById("input_package_description")?.value || "").trim();
      const rawPrice = document.getElementById("input_package_price")?.value || "0";
      const billingCycle = document.getElementById("input_package_cycle")?.value || "monthly";
      const isActive = !!document.getElementById("input_package_active")?.checked;
      const selectedFeatureCodes = this.getSelectedFeatureCodes();

      if (!name) {
        self.showError("Package name is required");
        return;
      }

      if (!billingCycle) {
        self.showError("Billing cycle is required");
        return;
      }

      if (!selectedFeatureCodes.length) {
        self.showError("Pilih minimal 1 fitur untuk paket ini");
        return;
      }

      const price = parseFloat(rawPrice || "0");
      if (!Number.isFinite(price) || price < 0) {
        self.showError("Price must be a valid positive number");
        return;
      }

      const normalizedCode = name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "_")
        .replace(/^_+|_+$/g, "") || "package_" + Date.now();

      const existing = this.packages.find(function (pkg) {
        return String(pkg.id) === String(self.currentEditId);
      });

      const monthlyPrice = billingCycle === "monthly" ? price : Math.round((price / 12) * 100) / 100;
      const yearlyPrice = billingCycle === "yearly" ? price : Math.round((price * 12) * 100) / 100;

      const data = {
        code: existing?.code || normalizedCode,
        name: name,
        description: description || null,
        monthly_price: monthlyPrice,
        yearly_price: yearlyPrice,
        billing_unit: "company",
        status: isActive ? "active" : "inactive",
      };

      const method = this.currentEditId ? "PUT" : "POST";
      const url = this.currentEditId ? API_BASE + "/" + this.currentEditId : API_BASE;

      apiRequest(method, url, data)
        .then(function (response) {
          if (response.success && response.data?.id) {
            const packageId = response.data.id;
            return self
              .syncPackageFeatures(packageId, selectedFeatureCodes)
              .then(function () {
                return response;
              });
          }
          return response;
        })
        .then(function (response) {
          if (response.success) {
            self.showSuccess(self.currentEditId ? "Package updated successfully" : "Package added successfully");
            form.reset();
            self.currentEditId = null;
            self.resetPackageModalState();
            self.renderFeatureCatalog(DEFAULT_FEATURE_CATALOG);
            if (self.packageModalInstance) self.packageModalInstance.hide();
            self.currentPage = 1;
            self.loadPackages();
          } else {
            self.showError(response.error?.message || "Failed to save package");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error saving package");
        });
    },

    syncPackageFeatures: function (packageId, selectedFeatureCodes) {
      const self = this;
      const selected = new Set(selectedFeatureCodes || []);

      return apiRequest("GET", API_BASE + "/" + packageId, null)
        .then(function (response) {
          const existingFeatures = response?.data?.features || [];
          const existingByCode = {};
          existingFeatures.forEach(function (feature) {
            existingByCode[feature.code] = feature;
          });

          const addRequests = [];
          selected.forEach(function (code) {
            if (!existingByCode[code]) {
              addRequests.push(
                apiRequest("POST", API_BASE + "/" + packageId + "/features", {
                  feature_code: code,
                  feature_name: self.featureLabelFromCode(code),
                  limit: null,
                }).catch(function () {
                  return null;
                })
              );
            }
          });

          const removeRequests = [];
          existingFeatures.forEach(function (feature) {
            if (!selected.has(feature.code)) {
              removeRequests.push(
                apiRequest("DELETE", API_BASE + "/features/" + feature.id, null).catch(function () {
                  return null;
                })
              );
            }
          });

          return Promise.all(addRequests.concat(removeRequests));
        });
    },

    featureLabelFromCode: function (code) {
      let foundName = "";
      FEATURE_LIBRARY.some(function (group) {
        return (group.features || []).some(function (feature) {
          if (feature.code === code) {
            foundName = feature.name;
            return true;
          }
          return false;
        });
      });

      if (foundName) {
        return foundName;
      }

      return String(code || "")
        .split("_")
        .map(function (part) {
          return part.charAt(0).toUpperCase() + part.slice(1);
        })
        .join(" ");
    },

    getSelectedFeatureCodes: function () {
      const catalogRoot = document.getElementById("input_package_feature_chips");
      if (!catalogRoot) return [];
      return Array.from(
        catalogRoot.querySelectorAll("input[type='checkbox'][name='package_feature_codes']:checked")
      ).map(function (input) {
        return input.value;
      });
    },

    syncFeatureCatalogFromPackages: function (packages) {
      const fromPackages = [];
      (packages || []).forEach(function (pkg) {
        (pkg.features || []).forEach(function (feature) {
          const code = feature.code || String(feature.name || "").toLowerCase().replace(/[^a-z0-9]+/g, "_");
          if (code) {
            fromPackages.push(code);
          }
        });
      });

      const catalog = Array.from(new Set(DEFAULT_FEATURE_CATALOG.concat(fromPackages)));
      this.renderFeatureCatalog(catalog);
    },

    renderFeatureCatalog: function (featureCodes) {
      const catalogRoot = document.getElementById("input_package_feature_chips");
      if (!catalogRoot) return;

      const selectedCodes = new Set(this.getSelectedFeatureCodes());
      const incomingCodes = new Set(featureCodes || DEFAULT_FEATURE_CATALOG);
      const knownCodes = new Set(DEFAULT_FEATURE_CATALOG);

      const groups = FEATURE_LIBRARY.map(function (group) {
        return {
          module: group.module,
          title: group.title,
          description: group.description,
          features: (group.features || []).filter(function (feature) {
            return incomingCodes.has(feature.code) || selectedCodes.has(feature.code);
          }),
        };
      }).filter(function (group) {
        return group.features.length > 0;
      });

      const extraCodes = Array.from(incomingCodes).filter(function (code) {
        return !knownCodes.has(code);
      });

      if (extraCodes.length > 0) {
        groups.push({
          module: "custom",
          title: "Custom Features",
          description: "Fitur tambahan yang sudah pernah dipakai di paket sebelumnya.",
          features: extraCodes.map((code) => ({
            code: code,
            name: this.featureLabelFromCode(code),
            description: "Feature code custom dari konfigurasi package existing.",
          })),
        });
      }

      const accordionId = "package_feature_catalog_accordion";
      catalogRoot.innerHTML =
        '<div class="accordion" id="' +
        accordionId +
        '">' +
        groups
          .map(
            function (group, groupIndex) {
              const collapseId = "feature_group_" + group.module + "_" + String(groupIndex);
              return (
                '<div class="accordion-item mb-2" data-feature-group data-feature-group-key="' +
                esc(group.module) +
                '">' +
                '<h2 class="accordion-header" id="heading_' +
                esc(collapseId) +
                '">' +
                '<button class="accordion-button ' +
                (groupIndex === 0 ? "" : "collapsed") +
                '" type="button" data-bs-toggle="collapse" data-bs-target="#' +
                esc(collapseId) +
                '">' +
                '<div class="d-flex align-items-center justify-content-between w-100 pe-2">' +
                '<div><div class="fw-semibold">' +
                esc(group.title) +
                '</div><small class="text-muted">' +
                esc(group.description || "") +
                "</small></div>" +
                '<span class="badge text-bg-light" data-feature-group-count="' +
                esc(group.module) +
                '">' +
                esc(String(group.features.length)) +
                " fitur</span>" +
                "</div>" +
                "</button>" +
                "</h2>" +
                '<div id="' +
                esc(collapseId) +
                '" class="accordion-collapse collapse ' +
                (groupIndex === 0 ? "show" : "") +
                '">' +
                '<div class="accordion-body pt-2">' +
                group.features
                  .map(function (feature, featureIndex) {
                    const itemId =
                      "pkg_feature_checkbox_" +
                      String(groupIndex) +
                      "_" +
                      String(featureIndex) +
                      "_" +
                      String(feature.code).replace(/[^a-zA-Z0-9_\-]/g, "_");

                    return (
                      '<div class="package-feature-item" data-feature-item data-feature-filter="' +
                      esc((feature.name + " " + feature.code + " " + (feature.description || "")).toLowerCase()) +
                      '">' +
                      '<div class="form-check">' +
                      '<input class="form-check-input" type="checkbox" name="package_feature_codes" id="' +
                      esc(itemId) +
                      '" value="' +
                      esc(feature.code) +
                      '" data-feature-name="' +
                      esc(feature.name) +
                      '"' +
                      (selectedCodes.has(feature.code) ? " checked" : "") +
                      ">" +
                      '<label class="form-check-label" for="' +
                      esc(itemId) +
                      '">' +
                      '<span class="package-feature-item-title">' +
                      esc(feature.name) +
                      "</span>" +
                      '<span class="package-feature-item-desc">' +
                      esc(feature.description || "") +
                      "</span>" +
                      '<span class="text-muted small">Code: ' +
                      esc(feature.code) +
                      "</span>" +
                      "</label>" +
                      "</div>" +
                      "</div>"
                    );
                  })
                  .join("") +
                "</div>" +
                "</div>" +
                "</div>"
              );
            }.bind(this)
          )
          .join("") +
        "</div>";

      this.updateFeatureSelectionSummary();
      this.filterFeatureCatalog(document.getElementById("input_package_feature_search")?.value || "");
    },

    toggleVisibleFeatures: function (checked, clearAll) {
      const catalogRoot = document.getElementById("input_package_feature_chips");
      if (!catalogRoot) return;

      const selector = clearAll
        ? "input[type='checkbox'][name='package_feature_codes']"
        : ".package-feature-item:not(.d-none) input[type='checkbox'][name='package_feature_codes']";

      catalogRoot.querySelectorAll(selector).forEach(function (checkbox) {
        checkbox.checked = !!checked;
      });

      this.updateFeatureSelectionSummary();
    },

    filterFeatureCatalog: function (keyword) {
      const catalogRoot = document.getElementById("input_package_feature_chips");
      if (!catalogRoot) return;

      const normalized = String(keyword || "").trim().toLowerCase();
      const groups = Array.from(catalogRoot.querySelectorAll("[data-feature-group]"));

      groups.forEach(function (group) {
        let visibleCount = 0;
        group.querySelectorAll("[data-feature-item]").forEach(function (item) {
          const haystack = String(item.getAttribute("data-feature-filter") || "");
          const isVisible = !normalized || haystack.indexOf(normalized) >= 0;
          item.classList.toggle("d-none", !isVisible);
          if (isVisible) {
            visibleCount += 1;
          }
        });

        group.classList.toggle("d-none", visibleCount === 0);
        const moduleKey = group.getAttribute("data-feature-group-key");
        const counter = catalogRoot.querySelector('[data-feature-group-count="' + moduleKey + '"]');
        if (counter) {
          counter.textContent = String(visibleCount) + " fitur";
        }
      });
    },

    updateFeatureSelectionSummary: function () {
      const selected = this.getSelectedFeatureCodes();
      const countEl = document.querySelector("[data-feature-selected-count]");
      const previewEl = document.querySelector("[data-feature-selected-preview]");

      if (countEl) {
        countEl.textContent = String(selected.length);
      }

      if (!previewEl) {
        return;
      }

      if (selected.length === 0) {
        previewEl.innerHTML = '<span class="text-muted small">Belum ada fitur dipilih</span>';
        return;
      }

      const labels = selected.slice(0, 10).map(
        function (code) {
          return '<span class="package-feature-preview-chip">' + esc(this.featureLabelFromCode(code)) + "</span>";
        }.bind(this)
      );

      if (selected.length > 10) {
        labels.push('<span class="text-muted small">+' + esc(String(selected.length - 10)) + " lainnya</span>");
      }

      previewEl.innerHTML = labels.join("");
    },

    resetPackageModalState: function () {
      const title = document.getElementById("packageModalTitle");
      const submitBtn = document.querySelector("#packageForm button[type='submit']");
      if (title) title.textContent = "Add Package";
      if (submitBtn) submitBtn.textContent = "Save Package";
    },

    openCreateModal: function () {
      this.currentEditId = null;
      const form = document.getElementById("packageForm");
      if (form) form.reset();
      this.renderFeatureCatalog(DEFAULT_FEATURE_CATALOG);
      const featureSearchInput = document.getElementById("input_package_feature_search");
      if (featureSearchInput) {
        featureSearchInput.value = "";
      }
      this.resetPackageModalState();
    },

    openCreateAddonModal: function () {
      this.currentAddonEditId = null;
      const form = document.getElementById("addonForm");
      if (form) form.reset();
      const title = document.getElementById("addonModalTitle");
      const submitBtn = document.querySelector("#addonForm button[type='submit']");
      if (title) title.textContent = "Add Add-on";
      if (submitBtn) submitBtn.textContent = "Save Add-on";
      if (this.addonModalInstance) this.addonModalInstance.show();
    },

    handleSaveAddon: function (form) {
      const self = this;
      const code = (document.getElementById("input_addon_code")?.value || "").trim();
      const name = (document.getElementById("input_addon_name")?.value || "").trim();
      const description = (document.getElementById("input_addon_description")?.value || "").trim();
      const pricePerUnit = parseFloat(document.getElementById("input_addon_price")?.value || "0");
      const unitName = (document.getElementById("input_addon_unit")?.value || "").trim();
      const isActive = !!document.getElementById("input_addon_active")?.checked;

      if (!code || !name || !unitName) {
        self.showError("Addon code, name, and unit are required");
        return;
      }

      if (!Number.isFinite(pricePerUnit) || pricePerUnit < 0) {
        self.showError("Addon price must be a valid positive number");
        return;
      }

      const payload = {
        code: code,
        name: name,
        description: description || null,
        price_per_unit: pricePerUnit,
        unit_name: unitName,
        status: isActive ? "active" : "inactive",
      };

      const method = this.currentAddonEditId ? "PUT" : "POST";
      const url = this.currentAddonEditId ? API_ADDONS_BASE + "/" + this.currentAddonEditId : API_ADDONS_BASE;

      apiRequest(method, url, payload)
        .then(function (response) {
          if (response.success) {
            self.showSuccess(self.currentAddonEditId ? "Addon updated successfully" : "Addon added successfully");
            form.reset();
            self.currentAddonEditId = null;
            const title = document.getElementById("addonModalTitle");
            const submitBtn = document.querySelector("#addonForm button[type='submit']");
            if (title) title.textContent = "Add Add-on";
            if (submitBtn) submitBtn.textContent = "Save Add-on";
            if (self.addonModalInstance) self.addonModalInstance.hide();
            self.currentAddonPage = 1;
            self.loadAddons();
          } else {
            self.showError(response.error?.message || "Failed to save add-on");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error saving add-on");
        });
    },

    /**
     * Edit package
     */
    editPackage: function (id) {
      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            const pkg = response.data;
            const title = document.getElementById("packageModalTitle");
            const submitBtn = document.querySelector("#packageForm button[type='submit']");
            if (title) title.textContent = "Edit Package";
            if (submitBtn) submitBtn.textContent = "Update Package";

            document.getElementById("input_package_name").value = pkg.name || "";
            document.getElementById("input_package_description").value = pkg.description || "";
            document.getElementById("input_package_price").value = Number(pkg.monthlyPrice || 0);
            document.getElementById("input_package_cycle").value = "monthly";
            document.getElementById("input_package_active").checked = pkg.status === "active";

            const selectedCodes = (pkg.features || []).map(function (f) {
              return f.code;
            });
            self.renderFeatureCatalog(DEFAULT_FEATURE_CATALOG.concat(selectedCodes));
            const featureSearchInput = document.getElementById("input_package_feature_search");
            if (featureSearchInput) {
              featureSearchInput.value = "";
            }
            const chipsRoot = document.getElementById("input_package_feature_chips");
            if (chipsRoot) {
              chipsRoot
                .querySelectorAll("input[type='checkbox'][name='package_feature_codes']")
                .forEach(function (el) {
                  el.checked = selectedCodes.indexOf(el.value) !== -1;
                });
            }
            self.updateFeatureSelectionSummary();

            self.currentEditId = id;
            if (self.packageModalInstance) self.packageModalInstance.show();
          } else {
            self.showError("Failed to load package");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading package");
        });
    },

    deletePackage: async function (id) {
      if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== "function") {
        this.showError("Delete confirmation UI belum tersedia. Refresh halaman lalu coba lagi.");
        return;
      }

      const confirmed = await window.ArcavUi.confirmDelete(
        "Hapus package ini? Tindakan tidak dapat dibatalkan.",
        "Delete Package"
      );

      if (!confirmed) return;

      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("DELETE", url, null)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Package deleted successfully");
            self.loadPackages();
          } else {
            self.showError(response.error?.message || "Failed to delete package");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error deleting package");
        });
    },

    editAddon: function (id) {
      const self = this;
      apiRequest("GET", API_ADDONS_BASE + "/" + id, null)
        .then(function (response) {
          if (response.success && response.data) {
            const addon = response.data;
            const title = document.getElementById("addonModalTitle");
            const submitBtn = document.querySelector("#addonForm button[type='submit']");
            if (title) title.textContent = "Edit Add-on";
            if (submitBtn) submitBtn.textContent = "Update Add-on";

            document.getElementById("input_addon_code").value = addon.code || "";
            document.getElementById("input_addon_name").value = addon.name || "";
            document.getElementById("input_addon_description").value = addon.description || "";
            document.getElementById("input_addon_price").value = Number(addon.pricePerUnit || 0);
            document.getElementById("input_addon_unit").value = addon.unitName || "";
            document.getElementById("input_addon_active").checked = addon.status === "active";

            self.currentAddonEditId = id;
            if (self.addonModalInstance) self.addonModalInstance.show();
          } else {
            self.showError("Failed to load add-on");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading add-on");
        });
    },

    deleteAddon: async function (id) {
      if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== "function") {
        this.showError("Delete confirmation UI belum tersedia. Refresh halaman lalu coba lagi.");
        return;
      }

      const confirmed = await window.ArcavUi.confirmDelete(
        "Hapus add-on ini? Tindakan tidak dapat dibatalkan.",
        "Delete Add-on"
      );

      if (!confirmed) return;

      const self = this;
      apiRequest("DELETE", API_ADDONS_BASE + "/" + id, null)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Add-on deleted successfully");
            self.loadAddons();
          } else {
            self.showError(response.error?.message || "Failed to delete add-on");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error deleting add-on");
        });
    },

    showFeaturesModal: function (id) {
      const pkg = this.packages.find(function (row) {
        return String(row.id) === String(id);
      });
      if (!pkg) return;

      const body = document.getElementById("features_container");
      if (!body) return;
      body.innerHTML =
        '<h6 class="mb-3">' + esc(pkg.name) + '</h6>' +
        '<div class="d-flex flex-wrap gap-2">' +
        ((pkg.features || []).map(function (f) {
          return '<span class="badge bg-light text-dark">' + esc(f.name || f.code || "Feature") + '</span>';
        }).join("") || '<span class="text-muted">No features yet</span>') +
        '</div>';

      if (window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById("featuresModal")).show();
      }
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
      PackagesManager.init();
    });
  } else {
    PackagesManager.init();
  }

  // Expose to global scope
  window.PackagesManager = PackagesManager;
})(window, document);
