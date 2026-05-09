(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/packages";
  const API_FEATURE_CATALOG = "/v1/saas/packages/feature-catalog";
  const API_FEATURE_CATALOG_HEALTHCHECK = "/v1/saas/packages/feature-catalog/healthcheck";
  const API_ADDONS_BASE = "/v1/saas/package-addons";
  const PAGE_SIZE = 10;
  const FEATURE_LIMIT_INPUT_CODE = "max_employees";
  let apiToken = null;
  let featureLibrary = [];
  const FALLBACK_FEATURE_LIBRARY = [];

  function getFeatureLibrary() {
    return featureLibrary.length ? featureLibrary : FALLBACK_FEATURE_LIBRARY;
  }

  function getRuntimeFeatureDisplayName(code, fallbackName) {
    const map = {
      max_employees: "Maximum Employees",
      employee_management: "Employee Directory",
      employee_document_center: "Document Center",
      employee_lifecycle: "Lifecycle Tracking",
      attendance: "Attendance Dashboard",
      attendance_shift_scheduling: "Shift Scheduling",
      leave_management: "Leave Requests",
      leave_approval_flow: "Approval Workflow",
      holiday_calendar: "Holiday Calendar",
      payroll: "Payroll Run",
      payroll_components: "Compensation Components",
      payroll_thr: "THR Management",
      performance_goal_tracking: "Advanced Goal Tracking",
      trial_billing_dashboard: "Trial Billing Dashboard",
      tax_governance: "Tax Governance",
      allowance_governance: "Allowance Governance",
      bpjs_governance: "BPJS Governance",
      spt_masa_pph21: "SPT Masa PPh 21",
      overtime: "Overtime",
      calendar_events: "Calendar Events",
      promotion: "Promotion",
      resignation: "Resignation",
      termination: "Termination",
      data_privacy: "Data Privacy",
      notes: "Notes",
      faq: "FAQ",
    };

    return map[code] || fallbackName || code;
  }

  function isRecognizedRuntimeFeatureCode(code) {
    if (!code) return false;

    if (code === "max_employees") return true;
    if (code === "holiday_calendar") return true;
    if (code === "performance") return true;
    if (code === "goal_tracking") return true;
    if (code === "training") return true;
    if (code === "asset_management") return true;
    if (code === "tickets") return true;
    if (code === "notifications") return true;
    if (code === "trial_billing_dashboard") return true;
    if (code === "tax_governance") return true;
    if (code === "allowance_governance") return true;
    if (code === "bpjs_governance") return true;
    if (code === "spt_masa_pph21") return true;
    if (code === "overtime") return true;
    if (code === "calendar_events") return true;
    if (code === "promotion") return true;
    if (code === "resignation") return true;
    if (code === "termination") return true;
    if (code === "data_privacy") return true;
    if (code === "notes") return true;
    if (code === "faq") return true;

    if (code.startsWith("employee_")) return true;
    if (code.startsWith("attendance")) return true;
    if (code.startsWith("leave_")) return true;
    if (code.startsWith("payroll")) return true;
    if (code.startsWith("performance_")) return true;

    return false;
  }

  function getDefaultFeatureCatalog(libraryOverride) {
    const source = Array.isArray(libraryOverride) && libraryOverride.length
      ? libraryOverride
      : getFeatureLibrary();

    return source.flatMap(function (group) {
      return (group.features || []).map(function (feature) {
        return feature.code;
      });
    });
  }

  function isPackageFeatureIncluded(feature) {
    if (!feature) return false;
    if (typeof feature === "string") return true;

    const hasExplicitZeroLimit = feature.limit !== null && feature.limit !== undefined && Number(feature.limit) === 0;
    return feature.isIncluded !== false && !hasExplicitZeroLimit;
  }

  function getIncludedPackageFeatures(features, options) {
    const safeFeatures = Array.isArray(features) ? features : [];
    const included = safeFeatures.filter(isPackageFeatureIncluded);

    if (!options || options.catalogOnly !== true) {
      return included;
    }

    const catalogSet = new Set(getDefaultFeatureCatalog());
    return included.filter(function (feature) {
      const code = typeof feature === "string" ? feature : feature.code;
      return !!code && catalogSet.has(code);
    });
  }

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
    totalItems: 0,
    packages: [],
    addons: [],
    currentEditId: null,
    currentEditSnapshot: null,
    currentPricingDirty: false,
    currentAddonEditId: null,
    currentStatus: "all",
    currentSearch: "",
    currentAddonPage: 1,
    totalAddonPages: 1,
    totalAddonItems: 0,
    addonStatus: "all",
    addonSearch: "",
    featureLimitDrafts: {},
    featureHealthcheckSummary: null,
    compareSelectionLimit: 3,
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
      this.renderFeatureCatalog(getDefaultFeatureCatalog());
      this.packageModalInstance = window.bootstrap
        ? window.bootstrap.Modal.getOrCreateInstance(document.getElementById("packageModal"))
        : null;
      this.addonModalInstance = window.bootstrap
        ? window.bootstrap.Modal.getOrCreateInstance(document.getElementById("addonModal"))
        : null;
      const self = this;
      this.loadFeatureCatalog().finally(function () {
        self.loadPackages();
        self.loadAddons();
      });
    },

    loadFeatureCatalog: function () {
      const self = this;

      return apiRequest("GET", API_FEATURE_CATALOG, null)
        .then(function (response) {
          if (response.success && Array.isArray(response.data) && response.data.length) {
            featureLibrary = response.data;
            self.renderFeatureCatalog(getDefaultFeatureCatalog(response.data));
            return;
          }

          featureLibrary = [];
          self.renderFeatureCatalog(getDefaultFeatureCatalog());
        })
        .catch(function (err) {
          console.warn("Failed to load package feature catalog from backend runtime source.", err);
          featureLibrary = [];
          self.renderFeatureCatalog(getDefaultFeatureCatalog());
        });
    },

    hydrateRuntimeFeatureCatalogFromPackages: function (packages) {
      const runtimePackages = Array.isArray(packages) ? packages : [];
      const featureLookup = new Map();

      runtimePackages.forEach(function (pkg) {
        const features = Array.isArray(pkg && pkg.features) ? pkg.features : [];
        features.forEach(function (feature) {
          const code = String(feature && feature.code ? feature.code : "").trim();
          if (!code || featureLookup.has(code) || !isRecognizedRuntimeFeatureCode(code)) {
            return;
          }

          const featureName = getRuntimeFeatureDisplayName(code, String(feature && feature.name ? feature.name : "").trim());
          const fallbackDescription = code === FEATURE_LIMIT_INPUT_CODE
            ? "Batasi jumlah employee aktif yang bisa dikelola dalam paket ini."
            : "Fitur runtime dari package aktif.";

          featureLookup.set(code, {
            code: code,
            name: featureName,
            description: fallbackDescription,
            requiresLimit: code === FEATURE_LIMIT_INPUT_CODE,
            limitLabel: code === FEATURE_LIMIT_INPUT_CODE ? "Jumlah employee" : null,
            limitPlaceholder: code === FEATURE_LIMIT_INPUT_CODE ? "Contoh: 50" : null,
            limitSuffix: code === FEATURE_LIMIT_INPUT_CODE ? "org" : null,
          });
        });
      });

      if (!featureLookup.size) {
        return;
      }

      featureLibrary = [
        {
          module: "runtime",
          title: "Runtime Features",
          description: "Katalog dibentuk dinamis dari data package runtime saat endpoint feature catalog belum tersedia.",
          features: Array.from(featureLookup.values()),
        },
      ];

      this.renderFeatureCatalog(getDefaultFeatureCatalog(featureLibrary));
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

      const priceInput = document.getElementById("input_package_price");
      if (priceInput) {
        priceInput.addEventListener("input", function () {
          self.currentPricingDirty = true;
        });
      }

      const cycleInput = document.getElementById("input_package_cycle");
      if (cycleInput) {
        cycleInput.addEventListener("change", function () {
          self.currentPricingDirty = true;
        });
      }

      const maxEmployeesInput = document.getElementById("input_package_max_employees");
      if (maxEmployeesInput) {
        maxEmployeesInput.addEventListener("input", function () {
          self.syncMaxEmployeesFeatureFromTopField();
          self.updateFeatureSelectionSummary();
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

      document.addEventListener("click", function (e) {
        const healthcheckBtn = e.target.closest("[data-feature-healthcheck-trigger]");
        if (!healthcheckBtn) {
          const modulePreviewBtn = e.target.closest("[data-feature-preview-module]");
          if (modulePreviewBtn) {
            e.preventDefault();
            self.showModulePreviewModal(modulePreviewBtn.getAttribute("data-feature-preview-module"));
            return;
          }

          const listAllBtn = e.target.closest("[data-open-feature-catalog]");
          if (listAllBtn) {
            e.preventDefault();
            self.showFeatureCatalogModal();
            return;
          }

          const compareBtn = e.target.closest("[data-compare-packages-trigger]");
          if (compareBtn) {
            e.preventDefault();
            self.showFeatureMatrixModal();
          }

          return;
        }

        e.preventDefault();
        self.runFeatureCatalogHealthcheck(true);
      });

      document.addEventListener("change", function (e) {
        const selectAll = e.target.closest("#select-all-packages");
        if (selectAll) {
          self.togglePackageCompareSelection(!!selectAll.checked);
          return;
        }

        const packageCheckbox = e.target.closest("[data-package-compare-id]");
        if (!packageCheckbox) {
          return;
        }

        if (packageCheckbox.checked && self.getSelectedPackageIdsForCompare().length > self.compareSelectionLimit) {
          packageCheckbox.checked = false;
          self.showError("Maksimal bandingkan 3 package sekaligus.");
        }

        self.syncCompareSelectAllState();
      });

      document.addEventListener("input", function (e) {
        const featureLimitInput = e.target.closest("[data-feature-limit-input]");
        if (!featureLimitInput) {
          return;
        }

        const code = featureLimitInput.getAttribute("data-feature-limit-code") || "";
        self.featureLimitDrafts[code] = featureLimitInput.value;
        if (code === FEATURE_LIMIT_INPUT_CODE) {
          self.syncTopFieldFromMaxEmployeesFeature();
        }
        self.updateFeatureSelectionSummary();
      });

      // Addon search (rendered dynamically inside renderAddons, use delegation)
      let addonSearchDebounce = null;
      document.addEventListener("input", function (e) {
        if (e.target && e.target.id === "search_addons") {
          const nextValue = String(e.target.value || "").trim();
          window.clearTimeout(addonSearchDebounce);
          addonSearchDebounce = window.setTimeout(function () {
            self.addonSearch = nextValue;
            self.currentAddonPage = 1;
            self.loadAddons();
          }, 250);
        }
      });

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
          self.handleFeatureCheckboxChange(featureCheckbox);
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
     * Show skeleton placeholder in a container
     */
    showSkeleton: function (containerSelector, rows) {
      const container = document.querySelector(containerSelector);
      if (!container) return;
      const rowsHtml = Array.from({ length: rows || 5 }, function () {
        return `<tr>${Array.from({ length: 6 }, function () {
          return `<td><div class="placeholder-glow"><span class="placeholder col-10 rounded"></span></div></td>`;
        }).join('')}</tr>`;
      }).join('');
      container.innerHTML = `
        <div class="card">
          <div class="table-responsive">
            <table class="table"><tbody>${rowsHtml}</tbody></table>
          </div>
        </div>
      `;
    },

    /**
     * Load packages from API
     */
    loadPackages: function () {
      const self = this;
      this.showSkeleton("[data-packages-list-container]", 5);
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
            self.totalItems = response.pagination ? response.pagination.total : self.packages.length;

            if (!getFeatureLibrary().length) {
              self.hydrateRuntimeFeatureCatalogFromPackages(self.packages);
            }

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
      this.showSkeleton("[data-package-addons-list-container]", 3);
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
            self.totalAddonItems = response.pagination ? response.pagination.total : self.addons.length;
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

      const self = this;

      let html = '';
      if (this.packages.length === 0) {
        html = '<div class="card"><div class="card-body text-center text-muted py-4">No packages found</div></div>';
      } else {
        const startRow = (this.currentPage - 1) * PAGE_SIZE + 1;
        const endRow = Math.min(this.currentPage * PAGE_SIZE, this.totalItems || this.packages.length);
        const totalRow = this.totalItems || this.packages.length;
        function statusBadge(status) {
          const s = String(status || "").toLowerCase();
          const tone = s === "active" ? "success" : s === "inactive" ? "warning" : "danger";
          return `
            <span class="badge text-bg-${tone} d-inline-flex align-items-center badge-xs">
              <i class="ti ti-point-filled me-1"></i>${esc(s || "-")}
            </span>
          `;
        }

        function priceCell(pkg) {
          const monthly = formatCurrency(pkg.monthlyPrice);
          const yearly = formatCurrency(pkg.yearlyPrice);
          return `
            <div class="d-flex flex-column">
              <span class="fw-medium">${monthly}</span>
              <span class="fs-12 text-muted">Yearly: ${yearly}</span>
            </div>
          `;
        }

        function featuresCell(pkg) {
          const included = getIncludedPackageFeatures(pkg.features, { catalogOnly: true });
          if (!included.length) {
            return '<span class="text-muted fs-12">No features</span>';
          }
          const preview = included.slice(0, 4);
          const rest = Math.max(0, included.length - preview.length);
          return `
            <div class="d-flex flex-column gap-1">
              <div class="fs-12 text-muted">Included: <strong>${included.length}</strong></div>
              <div class="d-flex flex-wrap gap-1">
                ${preview.map((f) => `
                  <span class="badge bg-light text-dark small">
                    ${esc(self.describeFeatureBadge(f))}
                  </span>
                `).join('')}
                ${rest ? `<span class="badge bg-secondary small">+${rest}</span>` : ''}
              </div>
            </div>
          `;
        }

        function subscribersCell(pkg) {
          const activeCount = Number(pkg.activeSubscriptionsCount || 0);
          const totalCount = Number(pkg.totalSubscriptionsCount || 0);

          return `
            <div class="d-flex flex-column">
              <span class="fw-medium">${activeCount.toLocaleString("id-ID")}</span>
              <span class="fs-12 text-muted">Total riwayat: ${totalCount.toLocaleString("id-ID")}</span>
            </div>
          `;
        }

        html = `
          <div class="card">
            <div class="custom-datatable-filter table-responsive">
              <table class="table">
                <thead class="thead-light">
                  <tr>
                    <th class="no-sort">
                      <div class="form-check form-check-md">
                        <input class="form-check-input" type="checkbox" id="select-all-packages">
                      </div>
                    </th>
                    <th>Package Name</th>
                    <th>Price</th>
                    <th>Billing Unit</th>
                    <th>Status</th>
                    <th>Active Subscribers</th>
                    <th>Features</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${this.packages.map(pkg => `
                    <tr>
                      <td>
                        <div class="form-check form-check-md">
                          <input class="form-check-input" type="checkbox" data-package-compare-id="${esc(String(pkg.id))}">
                        </div>
                      </td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          ${pkg.color ? `<span class="d-inline-block rounded-circle flex-shrink-0" style="width:10px;height:10px;background:${esc(pkg.color)};" title="${esc(pkg.color)}"></span>` : ''}
                          <div>
                            <h6 class="fw-medium mb-0">${esc(pkg.name)}</h6>
                            <p class="fs-12 fw-normal text-muted mb-0">${esc(pkg.code || "-")}</p>
                          </div>
                        </div>
                      </td>
                      <td>${priceCell(pkg)}</td>
                      <td>${esc(pkg.billingUnit || "-")}</td>
                      <td>${statusBadge(pkg.status)}</td>
                      <td>${subscribersCell(pkg)}</td>
                      <td>${featuresCell(pkg)}</td>
                      <td>
                        <div class="action-icon d-inline-flex align-items-center">
                          <button class="btn btn-sm btn-white me-2" data-edit-package="${pkg.id}" title="Edit">
                            <i class="ti ti-edit"></i>
                          </button>
                          <button class="btn btn-sm btn-white me-2" data-view-features="${pkg.id}" title="View Features">
                            <i class="ti ti-list-details"></i>
                          </button>
                          <button class="btn btn-sm btn-white" data-delete-package="${pkg.id}" title="Delete">
                            <i class="ti ti-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
            <div class="px-3 py-2 border-top small text-muted">
              Centang package yang ingin dibandingkan (2-3 package), lalu klik <strong>Compare Selected</strong>.
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
              <small class="text-muted">Showing ${startRow}–${endRow} of ${totalRow} packages</small>
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
      this.syncCompareSelectAllState();
    },

    getSelectedPackageIdsForCompare: function () {
      return Array.from(document.querySelectorAll("[data-package-compare-id]:checked")).map(function (input) {
        return String(input.getAttribute("data-package-compare-id") || "").trim();
      }).filter(Boolean);
    },

    togglePackageCompareSelection: function (checked) {
      const checkboxes = Array.from(document.querySelectorAll("[data-package-compare-id]"));
      if (!checkboxes.length) {
        return;
      }

      if (!checked) {
        checkboxes.forEach(function (input) {
          input.checked = false;
        });
        this.syncCompareSelectAllState();
        return;
      }

      checkboxes.forEach(function (input, index) {
        input.checked = index < this.compareSelectionLimit;
      }.bind(this));
      this.syncCompareSelectAllState();
    },

    syncCompareSelectAllState: function () {
      const selectAll = document.getElementById("select-all-packages");
      if (!selectAll) {
        return;
      }

      const checkboxes = Array.from(document.querySelectorAll("[data-package-compare-id]"));
      if (!checkboxes.length) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
        return;
      }

      const checkedCount = checkboxes.filter(function (input) {
        return input.checked;
      }).length;
      selectAll.checked = checkedCount > 0 && checkedCount === Math.min(checkboxes.length, this.compareSelectionLimit);
      selectAll.indeterminate = checkedCount > 0 && !selectAll.checked;
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
          <div class="card-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <h5 class="mb-0">Package Add-ons</h5>
                <small class="text-muted">Global add-on catalog for pricing extras</small>
              </div>
              <button class="btn btn-sm btn-primary" id="btn_add_addon">
                <i class="ti ti-circle-plus me-1"></i>Add Add-on
              </button>
            </div>
            <div class="row g-2">
              <div class="col-md-6">
                <input type="text" class="form-control form-control-sm" id="search_addons" placeholder="Search add-ons..." value="${esc(this.addonSearch)}">
              </div>
            </div>
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
                        <span class="badge ${addon.status === "active" ? "text-bg-success" : "text-bg-warning"} d-inline-flex align-items-center badge-xs">
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
            <small class="text-muted">Showing ${Math.min((this.currentAddonPage - 1) * PAGE_SIZE + 1, this.totalAddonItems || this.addons.length)}–${Math.min(this.currentAddonPage * PAGE_SIZE, this.totalAddonItems || this.addons.length)} of ${this.totalAddonItems || this.addons.length} add-ons</small>
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
      const maxEmployeesRaw = (document.getElementById("input_package_max_employees")?.value || "").trim();
      let selectedFeatureConfigs = this.getSelectedFeatureConfigs();

      selectedFeatureConfigs = selectedFeatureConfigs.filter(function (feature) {
        return feature.code !== FEATURE_LIMIT_INPUT_CODE;
      });

      if (maxEmployeesRaw !== "") {
        if (!/^\d+$/.test(maxEmployeesRaw)) {
          self.showError("Maksimal employee harus berupa angka bulat positif.");
          return;
        }

        const maxEmployeesLimit = Number(maxEmployeesRaw);
        if (!Number.isInteger(maxEmployeesLimit) || maxEmployeesLimit < 1) {
          self.showError("Maksimal employee minimal 1.");
          return;
        }

        selectedFeatureConfigs.push({
          code: FEATURE_LIMIT_INPUT_CODE,
          name: self.featureLabelFromCode(FEATURE_LIMIT_INPUT_CODE),
          limit: maxEmployeesLimit,
          limitError: "",
        });
      }

      const selectedFeatureCodes = selectedFeatureConfigs.map(function (feature) {
        return feature.code;
      });

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

      const invalidFeatureLimit = selectedFeatureConfigs.find(function (feature) {
        return feature.limitError;
      });

      if (invalidFeatureLimit) {
        self.showError(invalidFeatureLimit.limitError);
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

      const snapshot = this.currentEditSnapshot;
      let monthlyPrice = billingCycle === "monthly" ? price : Math.round((price / 12) * 100) / 100;
      let yearlyPrice = billingCycle === "yearly" ? price : Math.round((price * 12) * 100) / 100;

      if (this.currentEditId && snapshot && !this.currentPricingDirty) {
        monthlyPrice = snapshot.monthlyPrice;
        yearlyPrice = snapshot.yearlyPrice;
      }

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
              .syncPackageFeatures(packageId, selectedFeatureConfigs)
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
            self.currentEditSnapshot = null;
            self.currentPricingDirty = false;
            self.resetPackageModalState();
            self.renderFeatureCatalog(getDefaultFeatureCatalog());
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
      const selectedFeatureMap = {};
      (selectedFeatureCodes || []).forEach(function (feature) {
        if (feature && feature.code) {
          selectedFeatureMap[feature.code] = feature;
        }
      });

      const selected = new Set(Object.keys(selectedFeatureMap));

      return apiRequest("GET", API_BASE + "/" + packageId, null)
        .then(function (response) {
          const existingFeatures = response?.data?.features || [];

          if (!getFeatureLibrary().length) {
            self.hydrateRuntimeFeatureCatalogFromPackages([{ features: existingFeatures }]);
          }

          const catalogFeatureSet = new Set(getDefaultFeatureCatalog());
          const existingByCode = {};
          existingFeatures.forEach(function (feature) {
            existingByCode[feature.code] = feature;
          });

          const addRequests = [];
          selected.forEach(function (code) {
            if (!existingByCode[code]) {
              const featureConfig = selectedFeatureMap[code] || {};
              addRequests.push(
                apiRequest("POST", API_BASE + "/" + packageId + "/features", {
                  feature_code: code,
                  feature_name: self.featureLabelFromCode(code),
                  limit: featureConfig.limit,
                }).catch(function () {
                  return null;
                })
              );
            }
          });

          const updateRequests = [];
          existingFeatures.forEach(function (feature) {
            const selectedFeature = selectedFeatureMap[feature.code];
            if (!selectedFeature) {
              return;
            }

            const desiredLimit = selectedFeature.limit === null || selectedFeature.limit === undefined
              ? null
              : Number(selectedFeature.limit);
            const currentLimit = feature.limit === null || feature.limit === undefined
              ? null
              : Number(feature.limit);
            const desiredName = self.featureLabelFromCode(feature.code);
            const currentName = feature.name || "";

            if (desiredLimit !== currentLimit || desiredName !== currentName) {
              updateRequests.push(
                apiRequest("PUT", API_BASE + "/features/" + feature.id, {
                  feature_name: desiredName,
                  limit: desiredLimit,
                }).catch(function () {
                  return null;
                })
              );
            }
          });

          const removeRequests = [];
          existingFeatures.forEach(function (feature) {
            if (!selected.has(feature.code) && catalogFeatureSet.has(feature.code)) {
              removeRequests.push(
                apiRequest("DELETE", API_BASE + "/features/" + feature.id, null).catch(function () {
                  return null;
                })
              );
            }
          });

          return Promise.all(addRequests.concat(updateRequests, removeRequests));
        });
    },

    featureMetaFromCode: function (code) {
      let found = null;
      getFeatureLibrary().some(function (group) {
        return (group.features || []).some(function (feature) {
          if (feature.code === code) {
            found = feature;
            return true;
          }
          return false;
        });
      });

      return found;
    },

    featureLabelFromCode: function (code) {
      const meta = this.featureMetaFromCode(code);
      const foundName = meta?.name || "";

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

    collectFeatureLimitDrafts: function () {
      const catalogRoot = document.getElementById("input_package_feature_chips");
      const drafts = Object.assign({}, this.featureLimitDrafts || {});
      if (!catalogRoot) return drafts;

      catalogRoot.querySelectorAll("[data-feature-limit-input]").forEach(function (input) {
        const code = input.getAttribute("data-feature-limit-code") || "";
        drafts[code] = input.value;
      });

      return drafts;
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

    getSelectedFeatureConfigs: function () {
      const catalogRoot = document.getElementById("input_package_feature_chips");
      if (!catalogRoot) return [];

      return Array.from(
        catalogRoot.querySelectorAll("input[type='checkbox'][name='package_feature_codes']:checked")
      ).map(
        function (input) {
          const code = input.value;
          const featureMeta = this.featureMetaFromCode(code);
          const featureConfig = {
            code: code,
            name: input.getAttribute("data-feature-name") || this.featureLabelFromCode(code),
            limit: null,
            limitError: "",
          };

          if (featureMeta?.requiresLimit) {
            const limitInput = catalogRoot.querySelector('[data-feature-limit-code="' + code + '"]');
            const rawValue = String(limitInput?.value || "").trim();

            if (rawValue === "") {
              featureConfig.limitError = featureMeta.limitLabel + " wajib diisi untuk " + featureConfig.name + ".";
              return featureConfig;
            }

            if (!/^\d+$/.test(rawValue)) {
              featureConfig.limitError = featureMeta.limitLabel + " harus berupa angka bulat positif.";
              return featureConfig;
            }

            const limit = Number(rawValue);
            if (!Number.isInteger(limit) || limit < 1) {
              featureConfig.limitError = featureMeta.limitLabel + " minimal 1.";
              return featureConfig;
            }

            featureConfig.limit = limit;
          }

          return featureConfig;
        }.bind(this)
      );
    },

    handleFeatureCheckboxChange: function (checkbox) {
      const code = checkbox?.value || "";
      if (!code) {
        return;
      }

      this.featureLimitDrafts = this.collectFeatureLimitDrafts();

      const limitInput = document.querySelector('[data-feature-limit-code="' + code + '"]');
      if (!limitInput) {
        return;
      }

      limitInput.disabled = !checkbox.checked;
      if (checkbox.checked && !String(limitInput.value || "").trim()) {
        limitInput.focus();
      }

      if (code === FEATURE_LIMIT_INPUT_CODE) {
        this.syncTopFieldFromMaxEmployeesFeature();
      }
    },

    getMaxEmployeesFeatureControls: function () {
      return {
        checkbox: document.querySelector("input[type='checkbox'][name='package_feature_codes'][value='" + FEATURE_LIMIT_INPUT_CODE + "']"),
        limitInput: document.querySelector("[data-feature-limit-code='" + FEATURE_LIMIT_INPUT_CODE + "']"),
      };
    },

    syncMaxEmployeesFeatureFromTopField: function () {
      const topInput = document.getElementById("input_package_max_employees");
      if (!topInput) {
        return;
      }

      const rawValue = String(topInput.value || "").trim();
      this.featureLimitDrafts[FEATURE_LIMIT_INPUT_CODE] = rawValue;

      const controls = this.getMaxEmployeesFeatureControls();
      if (!controls.checkbox || !controls.limitInput) {
        return;
      }

      const hasValue = rawValue !== "";

      controls.checkbox.checked = hasValue;
      controls.limitInput.disabled = !hasValue;

      if (hasValue) {
        controls.limitInput.value = rawValue;
        this.featureLimitDrafts[FEATURE_LIMIT_INPUT_CODE] = rawValue;
      } else {
        controls.limitInput.value = "";
        this.featureLimitDrafts[FEATURE_LIMIT_INPUT_CODE] = "";
      }
    },

    syncTopFieldFromMaxEmployeesFeature: function () {
      const topInput = document.getElementById("input_package_max_employees");
      if (!topInput) {
        return;
      }

      const controls = this.getMaxEmployeesFeatureControls();
      if (!controls.checkbox || !controls.limitInput) {
        return;
      }

      if (!controls.checkbox.checked) {
        topInput.value = "";
        return;
      }

      topInput.value = String(controls.limitInput.value || "").trim();
    },

    describeFeatureBadge: function (feature) {
      const code = typeof feature === "string" ? feature : feature?.code;
      const label = this.featureLabelFromCode(code || feature?.name || "Feature");
      const limit = typeof feature === "string" ? null : feature?.limit;
      if (code === FEATURE_LIMIT_INPUT_CODE && limit !== null && limit !== undefined && limit !== "") {
        return label + ": " + String(limit) + " org";
      }

      return label;
    },

    buildFeatureGroups: function (featureCodes, selectedCodesSet) {
      const selectedCodes = selectedCodesSet instanceof Set ? selectedCodesSet : new Set();
      const defaultCatalog = getDefaultFeatureCatalog();
      const incomingCodes = new Set(featureCodes || defaultCatalog);

      return getFeatureLibrary().map(function (group) {
        return {
          module: group.module,
          title: group.title,
          description: group.description,
          features: (group.features || []).filter(function (feature) {
            if (feature.code === FEATURE_LIMIT_INPUT_CODE) {
              return false;
            }
            return incomingCodes.has(feature.code) || selectedCodes.has(feature.code);
          }),
        };
      }).filter(function (group) {
        return group.features.length > 0;
      });
    },

    buildFeatureCoverageIndex: function () {
      const coverage = {};
      (this.packages || []).forEach(function (pkg) {
        const pkgName = String(pkg?.name || "").trim() || "(tanpa nama)";
        getIncludedPackageFeatures(pkg.features, { catalogOnly: true }).forEach(function (feature) {
          const code = String(feature?.code || feature || "").trim();
          if (!code) {
            return;
          }
          if (!coverage[code]) {
            coverage[code] = [];
          }
          coverage[code].push(pkgName);
        });
      });

      return coverage;
    },

    renderFeatureCatalog: function (featureCodes) {
      const catalogRoot = document.getElementById("input_package_feature_chips");
      if (!catalogRoot) return;

      const limitDrafts = Object.assign({}, this.featureLimitDrafts || {}, this.collectFeatureLimitDrafts());
      const selectedCodes = new Set(this.getSelectedFeatureCodes());
      const groups = this.buildFeatureGroups(featureCodes, selectedCodes);

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
                '<div class="package-feature-group-head">' +
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
                '<button type="button" class="package-feature-module-preview" data-feature-preview-module="' +
                esc(group.module) +
                '">Preview</button>' +
                "</div>" +
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
                    const limitValue = limitDrafts[feature.code] ?? "";
                    const limitLabel = feature.limitLabel || "Limit";
                    const limitPlaceholder = feature.limitPlaceholder || "Masukkan limit";
                    const limitSuffix = feature.limitSuffix || "";

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
                      (feature.requiresLimit
                        ? '<div class="mt-2 ps-4">' +
                          '<label class="form-label small text-muted mb-1" for="' +
                          esc(itemId + "_limit") +
                          '">' +
                          esc(limitLabel) +
                          '</label>' +
                          '<div class="input-group input-group-sm">' +
                          '<input class="form-control" type="number" min="1" step="1" id="' +
                          esc(itemId + "_limit") +
                          '" data-feature-limit-input data-feature-limit-code="' +
                          esc(feature.code) +
                          '" placeholder="' +
                          esc(limitPlaceholder) +
                          '" value="' +
                          esc(limitValue) +
                          '"' +
                          (selectedCodes.has(feature.code) ? "" : " disabled") +
                          '>' +
                          (limitSuffix
                            ? '<span class="input-group-text">' + esc(limitSuffix) + '</span>'
                            : '') +
                          '</div>' +
                        '</div>'
                        : "") +
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

      this.featureLimitDrafts = limitDrafts;
      this.syncMaxEmployeesFeatureFromTopField();
      this.updateFeatureSelectionSummary();
      this.filterFeatureCatalog(document.getElementById("input_package_feature_search")?.value || "");
      this.runFeatureCatalogHealthcheck(false);
    },

    showModulePreviewModal: function (moduleKey) {
      const groups = this.buildFeatureGroups(getDefaultFeatureCatalog(), new Set());
      const group = groups.find(function (item) {
        return item.module === moduleKey;
      });

      if (!group) {
        this.showError("Modul tidak ditemukan pada katalog runtime.");
        return;
      }

      const titleEl = document.getElementById("modulePreviewTitle");
      const bodyEl = document.getElementById("module_preview_container");
      if (!bodyEl) {
        return;
      }

      if (titleEl) {
        titleEl.textContent = "Preview Module: " + String(group.title);
      }

      bodyEl.innerHTML =
        '<p class="text-muted small mb-3">' + esc(group.description || "") + '</p>' +
        '<div class="feature-catalog-list">' +
        group.features.map(function (feature) {
          return (
            '<div class="package-feature-item mb-2">' +
              '<div class="d-flex justify-content-between align-items-start gap-2">' +
                '<div>' +
                  '<div class="package-feature-item-title">' + esc(feature.name) + '</div>' +
                  '<div class="package-feature-item-desc">' + esc(feature.description || "") + '</div>' +
                '</div>' +
                '<span class="badge bg-light text-dark">' + esc(feature.code) + '</span>' +
              '</div>' +
            '</div>'
          );
        }).join("") +
        '</div>';

      if (window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById("modulePreviewModal")).show();
      }
    },

    showFeatureCatalogModal: function () {
      const bodyEl = document.getElementById("feature_catalog_container");
      if (!bodyEl) {
        return;
      }

      const groups = this.buildFeatureGroups(getDefaultFeatureCatalog(), new Set());
      const coverage = this.buildFeatureCoverageIndex();
      const totalFeatures = groups.reduce(function (sum, group) {
        return sum + group.features.length;
      }, 0);

      bodyEl.innerHTML =
        '<div class="d-flex justify-content-between align-items-center mb-3">' +
          '<div class="text-muted small">Total feature code aktif: <strong>' + esc(String(totalFeatures)) + '</strong></div>' +
          '<div class="text-muted small">Jumlah package aktif di list: <strong>' + esc(String((this.packages || []).length)) + '</strong></div>' +
        '</div>' +
        '<div class="feature-catalog-list">' +
          groups.map(function (group) {
            return (
              '<div class="feature-catalog-module-card p-3">' +
                '<div class="d-flex justify-content-between align-items-start mb-2 gap-2">' +
                  '<div>' +
                    '<h6 class="mb-1">' + esc(group.title) + '</h6>' +
                    '<p class="mb-0 text-muted small">' + esc(group.description || "") + '</p>' +
                  '</div>' +
                  '<span class="badge text-bg-light">' + esc(String(group.features.length)) + ' fitur</span>' +
                '</div>' +
                '<div class="table-responsive">' +
                  '<table class="table table-sm align-middle mb-0">' +
                    '<thead><tr><th>Feature</th><th>Code</th><th>Ada di package</th></tr></thead>' +
                    '<tbody>' +
                      group.features.map(function (feature) {
                        const inPackages = coverage[feature.code] || [];
                        return (
                          '<tr>' +
                            '<td><div class="fw-semibold">' + esc(feature.name) + '</div><div class="text-muted small">' + esc(feature.description || "") + '</div></td>' +
                            '<td><span class="badge bg-light text-dark">' + esc(feature.code) + '</span></td>' +
                            '<td>' +
                              (inPackages.length
                                ? inPackages.map(function (pkgName) {
                                    return '<span class="badge bg-light text-dark me-1 mb-1">' + esc(pkgName) + '</span>';
                                  }).join("")
                                : '<span class="text-muted small">Belum terpasang di package mana pun</span>') +
                            '</td>' +
                          '</tr>'
                        );
                      }).join("") +
                    '</tbody>' +
                  '</table>' +
                '</div>' +
              '</div>'
            );
          }).join("") +
        '</div>';

      if (window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById("featureCatalogModal")).show();
      }
    },

    showFeatureMatrixModal: function () {
      const selectedIds = this.getSelectedPackageIdsForCompare();
      if (selectedIds.length < 2) {
        this.showError("Pilih minimal 2 package untuk membandingkan fitur.");
        return;
      }

      const selectedPackages = (this.packages || []).filter(function (pkg) {
        return selectedIds.indexOf(String(pkg.id)) >= 0;
      });

      if (selectedPackages.length < 2) {
        this.showError("Package terpilih tidak ditemukan di halaman saat ini. Refresh list lalu coba lagi.");
        return;
      }

      const bodyEl = document.getElementById("feature_matrix_container");
      if (!bodyEl) {
        return;
      }

      const groups = this.buildFeatureGroups(getDefaultFeatureCatalog(), new Set());
      const featureRows = groups.flatMap(function (group) {
        return group.features.map(function (feature) {
          return {
            moduleTitle: group.title,
            code: feature.code,
            name: feature.name,
            description: feature.description || "",
          };
        });
      });

      bodyEl.innerHTML =
        '<p class="text-muted small mb-3">Perbandingan fitur antar package berdasarkan katalog runtime yang tampil di halaman ini.</p>' +
        '<div class="table-responsive feature-matrix-table">' +
          '<table class="table table-sm table-bordered align-middle">' +
            '<thead class="table-light">' +
              '<tr>' +
                '<th style="min-width:280px;">Feature</th>' +
                selectedPackages.map(function (pkg) {
                  return '<th>' + esc(pkg.name) + '</th>';
                }).join("") +
              '</tr>' +
            '</thead>' +
            '<tbody>' +
              featureRows.map(function (row) {
                return (
                  '<tr>' +
                    '<td>' +
                      '<div class="fw-semibold">' + esc(row.name) + '</div>' +
                      '<div class="text-muted small">' + esc(row.moduleTitle) + ' • ' + esc(row.code) + '</div>' +
                    '</td>' +
                    selectedPackages.map(function (pkg) {
                      const hasFeature = getIncludedPackageFeatures(pkg.features, { catalogOnly: true }).some(function (feature) {
                        return String(feature?.code || feature || "") === row.code;
                      });
                      return '<td class="text-center">' + (hasFeature ? '<span class="text-success fw-semibold">✓</span>' : '<span class="text-muted">-</span>') + '</td>';
                    }).join("") +
                  '</tr>'
                );
              }).join("") +
            '</tbody>' +
          '</table>' +
        '</div>';

      if (window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById("featureMatrixModal")).show();
      }
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

      catalogRoot.querySelectorAll("input[type='checkbox'][name='package_feature_codes']").forEach(
        function (checkbox) {
          this.handleFeatureCheckboxChange(checkbox);
        }.bind(this)
      );

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
      const maxEmployeesValue = (document.getElementById("input_package_max_employees")?.value || "").trim();
      const hasMaxEmployees = maxEmployeesValue !== "";
      const effectiveSelected = hasMaxEmployees
        ? [FEATURE_LIMIT_INPUT_CODE].concat(selected.filter(function (code) {
          return code !== FEATURE_LIMIT_INPUT_CODE;
        }))
        : selected.filter(function (code) {
          return code !== FEATURE_LIMIT_INPUT_CODE;
        });
      const countEl = document.querySelector("[data-feature-selected-count]");
      const previewEl = document.querySelector("[data-feature-selected-preview]");

      if (countEl) {
        countEl.textContent = String(effectiveSelected.length);
      }

      if (!previewEl) {
        return;
      }

      if (effectiveSelected.length === 0) {
        previewEl.innerHTML = '<span class="text-muted small">Belum ada fitur dipilih</span>';
        return;
      }

      const labels = effectiveSelected.slice(0, 10).map(
        function (code) {
          const featureMeta = this.featureMetaFromCode(code);
          let label = this.featureLabelFromCode(code);
          if (featureMeta?.requiresLimit) {
            const limitValue = String(this.collectFeatureLimitDrafts()[code] || "").trim();
            if (limitValue) {
              label += ": " + limitValue + " " + (featureMeta.limitSuffix || "").trim();
            }
          }
          return '<span class="package-feature-preview-chip">' + esc(label.trim()) + "</span>";
        }.bind(this)
      );

      if (effectiveSelected.length > 10) {
        labels.push('<span class="text-muted small">+' + esc(String(effectiveSelected.length - 10)) + " lainnya</span>");
      }

      previewEl.innerHTML = labels.join("");
    },

    renderFeatureHealthcheckStatus: function (payload) {
      const statusEl = document.querySelector("[data-feature-healthcheck-status]");
      if (!statusEl) {
        return;
      }

      if (!payload || payload.loading) {
        statusEl.className = "small text-muted mt-2";
        statusEl.textContent = "Healthcheck: memeriksa sinkronisasi route/docs/catalog...";
        return;
      }

      if (payload.error) {
        statusEl.className = "small text-danger mt-2";
        statusEl.textContent = "Healthcheck tidak tersedia: " + payload.error;
        return;
      }

      const counts = payload.counts || {};
      const routeOnly = Number(counts.route_only || 0);
      const docsOnly = Number(counts.docs_only || 0);
      const hasDrift = !!payload.has_drift;

      statusEl.className = hasDrift ? "small text-warning mt-2" : "small text-success mt-2";
      statusEl.textContent = hasDrift
        ? "Healthcheck drift terdeteksi (route-only: " + routeOnly + ", docs-only: " + docsOnly + ")."
        : "Healthcheck OK: route/docs/catalog sinkron.";
    },

    runFeatureCatalogHealthcheck: function (force) {
      const self = this;
      if (self.featureHealthcheckSummary && !force) {
        self.renderFeatureHealthcheckStatus(self.featureHealthcheckSummary);
        return Promise.resolve(self.featureHealthcheckSummary);
      }

      self.renderFeatureHealthcheckStatus({ loading: true });

      return apiRequest("GET", API_FEATURE_CATALOG_HEALTHCHECK)
        .then(function (response) {
          if (!response || response.success !== true || !response.data) {
            throw new Error("Invalid healthcheck response");
          }

          self.featureHealthcheckSummary = response.data;
          self.renderFeatureHealthcheckStatus(self.featureHealthcheckSummary);
          return self.featureHealthcheckSummary;
        })
        .catch(function (err) {
          const message = err?.data?.error?.message || err?.message || "request failed";
          self.featureHealthcheckSummary = { error: message };
          self.renderFeatureHealthcheckStatus(self.featureHealthcheckSummary);
          return self.featureHealthcheckSummary;
        });
    },

    resetPackageModalState: function () {
      const title = document.getElementById("packageModalTitle");
      const submitBtn = document.querySelector("#packageForm button[type='submit']");
      if (title) title.textContent = "Add Package";
      if (submitBtn) submitBtn.textContent = "Save Package";
      this.currentEditSnapshot = null;
      this.currentPricingDirty = false;
      this.featureLimitDrafts = {};
      this.featureHealthcheckSummary = null;
      const maxEmployeesInput = document.getElementById("input_package_max_employees");
      if (maxEmployeesInput) {
        maxEmployeesInput.value = "";
      }
    },

    openCreateModal: function () {
      this.currentEditId = null;
      const form = document.getElementById("packageForm");
      if (form) form.reset();
      this.renderFeatureCatalog(getDefaultFeatureCatalog());
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

            if (!getFeatureLibrary().length) {
              self.hydrateRuntimeFeatureCatalogFromPackages([{ features: pkg.features || [] }]);
            }

            const title = document.getElementById("packageModalTitle");
            const submitBtn = document.querySelector("#packageForm button[type='submit']");
            if (title) title.textContent = "Edit Package";
            if (submitBtn) submitBtn.textContent = "Update Package";

            document.getElementById("input_package_name").value = pkg.name || "";
            document.getElementById("input_package_description").value = pkg.description || "";
            document.getElementById("input_package_price").value = Number(pkg.monthlyPrice || 0);
            document.getElementById("input_package_cycle").value = "monthly";
            document.getElementById("input_package_active").checked = pkg.status === "active";
            self.currentEditSnapshot = {
              monthlyPrice: Number(pkg.monthlyPrice || 0),
              yearlyPrice: Number(pkg.yearlyPrice || 0),
            };
            self.currentPricingDirty = false;
            self.featureLimitDrafts = (pkg.features || []).reduce(function (acc, feature) {
              if (feature?.code && feature.limit !== null && feature.limit !== undefined) {
                acc[feature.code] = String(feature.limit);
              }
              return acc;
            }, {});

            const maxEmployeesFeature = (pkg.features || []).find(function (feature) {
              return feature.code === FEATURE_LIMIT_INPUT_CODE;
            });
            const maxEmployeesInput = document.getElementById("input_package_max_employees");
            if (maxEmployeesInput) {
              maxEmployeesInput.value = maxEmployeesFeature?.limit !== null && maxEmployeesFeature?.limit !== undefined
                ? String(maxEmployeesFeature.limit)
                : "";
            }

            const selectedCodes = (pkg.features || []).map(function (f) {
              return isPackageFeatureIncluded(f) ? f.code : null;
            }).filter(Boolean);
            self.renderFeatureCatalog(getDefaultFeatureCatalog().concat(selectedCodes));
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
                  self.handleFeatureCheckboxChange(el);
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

          const errorCode = err?.data?.error?.code;
          const errorMessage =
            err?.data?.error?.message ||
            err?.data?.message ||
            "Error deleting package";

          if (errorCode === "PACKAGE_IN_USE") {
            if (window.ArcavUi && typeof window.ArcavUi.showInfo === "function") {
              window.ArcavUi.showInfo("Package Masih Digunakan", errorMessage);
            }
            self.showError(errorMessage);
            self.loadPackages();
            return;
          }

          self.showError(errorMessage);
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

      const included = getIncludedPackageFeatures(pkg.features, { catalogOnly: true });
      body.innerHTML =
        '<h6 class="mb-3">' + esc(pkg.name) + '</h6>' +
        '<div class="text-muted small mb-2">Included: <strong>' + String(included.length) + '</strong></div>' +
        '<div class="d-flex flex-wrap gap-2">' +
        (included.map(function (f) {
          return '<span class="badge bg-light text-dark">' + esc(PackagesManager.describeFeatureBadge(f)) + '</span>';
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
      const safeType = type === "success" ? "success" : "danger";
      alertDiv.className = `alert alert-${safeType} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
      alertDiv.style.zIndex = 9999;
      alertDiv.appendChild(document.createTextNode(String(message || "")));
      const closeBtn = document.createElement("button");
      closeBtn.type = "button";
      closeBtn.className = "btn-close";
      closeBtn.setAttribute("data-bs-dismiss", "alert");
      alertDiv.appendChild(closeBtn);
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
