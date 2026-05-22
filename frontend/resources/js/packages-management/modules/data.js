import { API_BASE, API_ADDONS_BASE, PAGE_SIZE, FEATURE_LIMIT_INPUT_CODE, apiRequest, esc, formatCurrency, getDefaultFeatureCatalog, getFeatureLibrary, getRuntimeFeatureDisplayName, getIncludedPackageFeatures, isPackageFeatureIncluded, getAddonClassificationMode, setAddonClassificationMode, getServerAddonClassificationMode, getFeatureClassificationOverrides } from "../shared.js";

const dataMethods = {
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
      // Directly bind action buttons to ensure clicks work even if global delegation is interrupted
      Array.from(container.querySelectorAll('[data-edit-package]')).forEach((btn) => {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          const id = btn.getAttribute('data-edit-package');
          try {
            this.editPackage(id);
          } catch (err) {
            console.error(err);
          }
        }.bind(this));
      });

      Array.from(container.querySelectorAll('[data-delete-package]')).forEach((btn) => {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          const id = btn.getAttribute('data-delete-package');
          try {
            this.deletePackage(id);
          } catch (err) {
            console.error(err);
          }
        }.bind(this));
      });

      Array.from(container.querySelectorAll('[data-view-features]')).forEach((btn) => {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          const id = btn.getAttribute('data-view-features');
          try {
            this.showFeaturesModal(id);
          } catch (err) {
            console.error(err);
          }
        }.bind(this));
      });

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
      const serverMode = getServerAddonClassificationMode();
      const addonMode = serverMode !== null ? serverMode : getAddonClassificationMode();

      let html = `
        <div class="card">
          <div class="card-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <h5 class="mb-0">Package Add-ons</h5>
                <small class="text-muted">Global add-on catalog for pricing extras</small>
              </div>
              <div class="d-flex align-items-center gap-2">
                ${serverMode === null ? (`<div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="addon_mode_auto_toggle" ${addonMode === 'auto' ? 'checked' : ''}>
                  <label class="form-check-label small text-muted" for="addon_mode_auto_toggle">Auto classify (runtime)</label>
                </div>
                ${addonMode === "manual" ? '<button class="btn btn-sm btn-primary" id="btn_add_addon"><i class="ti ti-circle-plus me-1"></i>Add Add-on</button>' : ''}`) : (`<div class="small text-muted">Addon mode: <strong>${serverMode === 'auto' ? 'Auto (central)' : 'Manual (central)'}</strong></div>`)}
              </div>
            </div>
            <div class="row g-2">
              <div class="col-md-6">
                <input type="text" class="form-control form-control-sm" id="search_addons" placeholder="Search add-ons..." value="${esc(this.addonSearch)}">
              </div>
            </div>
          </div>
              ${/* Build effective addons list: merge server-provided addons with runtime-classified features when in auto mode */''}
              ${(() => {
                const serverAddons = Array.isArray(this.addons) ? this.addons : [];
                let addonsForRender = serverAddons.slice();

                // Include features classified as 'addon' either when in auto mode (runtime)
                // or when server is authoritative manual (DB overrides). Mark source accordingly.
                if (addonMode === 'auto' || serverMode === 'manual') {
                  try {
                    const runtimeLib = typeof getDefaultFeatureCatalog === 'function' ? getDefaultFeatureCatalog() : getFeatureLibrary();
                    const featureLib = getFeatureLibrary() || [];
                    const overrides = typeof getFeatureClassificationOverrides === 'function' ? getFeatureClassificationOverrides() : {};

                    const runtimeAddons = [];
                    if (Array.isArray(featureLib) && featureLib.length) {
                      (featureLib || []).forEach(function (group) {
                        (Array.isArray(group.features) ? group.features : []).forEach(function (f) {
                          const code = f && f.code ? String(f.code).trim() : null;
                          if (!code) return;
                          if (overrides && overrides[code] === 'addon') {
                            runtimeAddons.push({
                              id: null,
                              code: code,
                              name: f.name || code,
                              description: f.description || '',
                              pricePerUnit: 0,
                              unitName: '-',
                              status: 'active',
                              __runtime: addonMode === 'auto',
                              __override: serverMode === 'manual',
                            });
                          }
                        });
                      });
                    } else {
                      // fallback to default catalog codes when runtime groups aren't available
                      const fallbackCodes = typeof getDefaultFeatureCatalog === 'function' ? getDefaultFeatureCatalog() : [];
                      (Array.isArray(fallbackCodes) ? fallbackCodes : []).forEach(function (codeRaw) {
                        const code = String(codeRaw || '').trim();
                        if (!code) return;
                        if (overrides && overrides[code] === 'addon') {
                          runtimeAddons.push({
                            id: null,
                            code: code,
                            name: typeof getRuntimeFeatureDisplayName === 'function' ? getRuntimeFeatureDisplayName(code, code) : code,
                            description: '',
                            pricePerUnit: 0,
                            unitName: '-',
                            status: 'active',
                            __runtime: addonMode === 'auto',
                            __override: serverMode === 'manual',
                          });
                        }
                      });
                    }

                    // merge, avoiding duplicates by code
                    runtimeAddons.forEach(function (r) {
                      const exists = addonsForRender.some(function (s) { return String(s.code) === String(r.code); });
                      if (!exists) addonsForRender.push(r);
                    });
                  } catch (e) {
                    // ignore runtime merge errors
                    console.warn('Failed to merge runtime addons', e);
                  }
                }

                if (!addonsForRender.length) {
                  return '<div class="card-body text-center text-muted py-4">No package add-ons found</div>';
                }

                return `
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${addonsForRender.map((addon) => `
                        <tr data-addon-code="${esc(addon.code)}" ${addon.__runtime ? 'data-runtime-addon="1"' : ''}>
                          <td>
                            <div class="fw-medium">${esc(addon.code)}</div>
                            <small class="text-muted">${esc(addon.description || "-")}</small>
                          </td>
                          <td>${esc(addon.name)} ${addon.__runtime ? '<span class="badge bg-info text-dark ms-2">Auto</span>' : (addon.__override ? '<span class="badge bg-secondary text-dark ms-2">DB</span>' : '')}</td>
                          <td>${formatCurrency(addon.pricePerUnit)}</td>
                          <td>
                            <span class="badge ${addon.status === "active" ? "text-bg-success" : "text-bg-warning"} d-inline-flex align-items-center badge-xs">
                              <i class="ti ti-point-filled me-1"></i>${esc(addon.status)}
                            </span>
                          </td>
                          <td>
                            <div class="action-icon d-inline-flex">
                              ${addonMode === "manual" && !addon.__runtime ? (`<button class="btn btn-icon btn-sm me-2" data-edit-addon="${esc(String(addon.id || addon.code))}" title="Edit"><i class="ti ti-edit"></i></button>`) : ''}
                            </div>
                          </td>
                        </tr>
                      `).join('')}
                    </tbody>
                  </table>
                </div>
              `;
              })()}
          <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing ${Math.min((this.currentAddonPage - 1) * PAGE_SIZE + 1, this.totalAddonItems || this.addons.length)}–${Math.min(this.currentAddonPage * PAGE_SIZE, this.totalAddonItems || this.addons.length)} of ${this.totalAddonItems || this.addons.length} add-ons</small>
            <nav aria-label="Add-on page navigation">
              <ul class="pagination pagination-sm mb-0" data-addon-pagination></ul>
            </nav>
          </div>
        </div>
      `;

      container.innerHTML = html;
      // Bind addon action buttons directly to avoid reliance on global delegation
      Array.from(container.querySelectorAll('[data-edit-addon]')).forEach((btn) => {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          const id = btn.getAttribute('data-edit-addon');
          try {
            if (!id || id === 'null' || id === 'undefined') {
              console.warn('Ignored invalid addon edit trigger, missing id');
            } else {
              this.editAddon(id);
            }
          } catch (err) {
            console.error(err);
          }
        }.bind(this));
      });

      // Delete action removed for add-ons (add-ons are derived/fetched; deletion not allowed from UI)

      this.renderAddonPagination();
      // Bind addon mode toggle
      const toggle = container.querySelector('#addon_mode_auto_toggle');
      if (toggle) {
        toggle.addEventListener('change', function (e) {
          const nextMode = e.target.checked ? 'auto' : 'manual';
          setAddonClassificationMode(nextMode);
          // re-render addons and the feature catalog so badges/actions update
          try {
            this.renderAddons();
            if (typeof this.renderFeatureCatalog === 'function') {
              this.renderFeatureCatalog(getDefaultFeatureCatalog());
            }
          } catch (err) {
            // ignore
          }
        }.bind(this));
      }
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

};

export default dataMethods;
