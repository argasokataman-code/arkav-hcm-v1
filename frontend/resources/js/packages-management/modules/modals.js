import { API_BASE, API_ADDONS_BASE, FEATURE_LIMIT_INPUT_CODE, apiRequest, esc, getDefaultFeatureCatalog, getFeatureLibrary, getIncludedPackageFeatures, isPackageFeatureIncluded } from "../shared.js";

const modalMethods = {
    resetPackageModalScrollState: function () {
      [
        "#packageModal .modal-body",
        "#packageModal .package-modal-panel",
        "#packageModal .package-feature-catalog",
        "#packageModal [data-package-compliance-snapshot]",
      ].forEach(function (selector) {
        document.querySelectorAll(selector).forEach(function (element) {
          element.scrollTop = 0;
        });
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
      this.packageComplianceSnapshot = null;
      const maxEmployeesInput = document.getElementById("input_package_max_employees");
      if (maxEmployeesInput) {
        maxEmployeesInput.value = "";
      }
      this.resetPackageModalScrollState();
      this.queueComplianceSnapshotRefresh(true);
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
      this.queueComplianceSnapshotRefresh(true);
    },

    openCreateAddonModal: function () {
      this.currentAddonEditId = null;
      const form = document.getElementById("addonForm");
      if (form) form.reset();
      this._setAddonCodeLock(false);
      const title = document.getElementById("addonModalTitle");
      const submitBtn = document.querySelector("#addonForm button[type='submit']");
      if (title) title.textContent = "Add Add-on";
      if (submitBtn) submitBtn.textContent = "Save Add-on";
      if (this.addonModalInstance) this.addonModalInstance.show();
    },

    _setAddonCodeLock: function (locked) {
      const codeInput = document.getElementById("input_addon_code");
      const lockNote = document.getElementById("addon_code_locked_note");
      if (codeInput) {
        codeInput.readOnly = locked;
        codeInput.classList.toggle("bg-light", locked);
      }
      if (lockNote) lockNote.classList.toggle("d-none", !locked);
    },

    handleSaveAddon: function (form) {
      const self = this;
      const code = (document.getElementById("input_addon_code")?.value || "").trim();
      const name = (document.getElementById("input_addon_name")?.value || "").trim();
      const description = (document.getElementById("input_addon_description")?.value || "").trim();
      const pricePerUnit = parseFloat(document.getElementById("input_addon_price")?.value || "0");
      const isActive = !!document.getElementById("input_addon_active")?.checked;

      if (!code || !name) {
        self.showError("Addon code and name are required");
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
        status: isActive ? "active" : "inactive",
      };

      const method = this.currentAddonEditId ? "PUT" : "POST";
      const url = this.currentAddonEditId
        ? API_ADDONS_BASE + "/" + encodeURIComponent(String(this.currentAddonEditId).trim())
        : API_ADDONS_BASE;

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
            self.resetPackageModalScrollState();
            self.queueComplianceSnapshotRefresh(true);
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
      if (!id) {
        self.showError("Invalid add-on identifier");
        return;
      }

      // Normalize identifier and defensively encode it for URL usage
      const normalized = String(id).trim();
      if (!normalized || normalized === 'null' || normalized === 'undefined') {
        self.showError("Invalid add-on identifier");
        return;
      }

      // Try to resolve add-on from already-loaded data or rendered DOM to avoid unnecessary API calls
      try {
        const localList = Array.isArray(self.addons) ? self.addons : [];
        const foundLocal = localList.find(function (a) {
          return String(a.id) === normalized || String(a.code) === normalized;
        });

        if (foundLocal) {
          const addon = foundLocal;
          const title = document.getElementById("addonModalTitle");
          const submitBtn = document.querySelector("#addonForm button[type='submit']");
          if (title) title.textContent = "Edit Add-on";
          if (submitBtn) submitBtn.textContent = "Update Add-on";

          document.getElementById("input_addon_code").value = addon.code || "";
          document.getElementById("input_addon_name").value = addon.name || "";
          document.getElementById("input_addon_description").value = addon.description || "";
          document.getElementById("input_addon_price").value = Number(addon.pricePerUnit || 0);
          document.getElementById("input_addon_active").checked = addon.status === "active";
          self._setAddonCodeLock(true);

          self.currentAddonEditId = normalized;
          if (self.addonModalInstance) self.addonModalInstance.show();
          return;
        }

        // Fallback: locate the rendered table row for this addon and extract values
        const rows = Array.from(document.querySelectorAll('[data-package-addons-list-container] table tbody tr'));
        const tr = rows.find(function (r) {
          return String(r.getAttribute('data-addon-code') || '') === normalized ||
                 Array.from(r.querySelectorAll('[data-edit-addon]')).some(function (b) { return String(b.getAttribute('data-edit-addon') || '') === normalized; });
        });

        if (tr) {
          const cols = tr.querySelectorAll('td');
          const code = tr.getAttribute('data-addon-code') || normalized;
          const descEl = cols[0] ? cols[0].querySelector('small') : null;
          const description = descEl ? (descEl.textContent || '').trim() : '';
          const nameCell = cols[1] ? cols[1].childNodes[0] : null;
          const name = nameCell ? (nameCell.textContent || '').trim() : '';
          const priceText = cols[2] ? (cols[2].textContent || '') : '';
          const priceNum = parseFloat((priceText || '').replace(/[^0-9\-\.]/g, '')) || 0;
          const unit = cols[3] ? (cols[3].textContent || '').trim() : '-';
          const statusBadge = cols[4] ? (cols[4].textContent || '').trim() : 'active';

          const addon = {
            id: null,
            code: code,
            name: name || code,
            description: description,
            pricePerUnit: priceNum,
            unitName: unit || '-',
            status: statusBadge || 'active'
          };

          const title = document.getElementById("addonModalTitle");
          const submitBtn = document.querySelector("#addonForm button[type='submit']");
          if (title) title.textContent = "Edit Add-on";
          if (submitBtn) submitBtn.textContent = "Update Add-on";

          document.getElementById("input_addon_code").value = addon.code || "";
          document.getElementById("input_addon_name").value = addon.name || "";
          document.getElementById("input_addon_description").value = addon.description || "";
          document.getElementById("input_addon_price").value = Number(addon.pricePerUnit || 0);
          document.getElementById("input_addon_active").checked = addon.status === "active";
          self._setAddonCodeLock(true);

          self.currentAddonEditId = normalized;
          if (self.addonModalInstance) self.addonModalInstance.show();
          return;
        }
      } catch (e) {
        // ignore local lookup errors and fallback to API
        console.warn('Local addon lookup failed', e);
      }

      // If not found locally, attempt API fetch (normal behavior)
      const encodedId = encodeURIComponent(normalized);
      console.info('Fetching add-on', encodedId);

      apiRequest("GET", API_ADDONS_BASE + "/" + encodedId, null)
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
            document.getElementById("input_addon_active").checked = addon.status === "active";
            self._setAddonCodeLock(true);

            self.currentAddonEditId = normalized;
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

      if (!id) {
        this.showError("Invalid add-on identifier");
        return;
      }

      const normalized = String(id).trim();
      if (!normalized || normalized === 'null' || normalized === 'undefined') {
        this.showError("Invalid add-on identifier");
        return;
      }

      const encodedId = encodeURIComponent(normalized);
      const self = this;
      apiRequest("DELETE", API_ADDONS_BASE + "/" + encodedId, null)
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
      const self = this;
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
          return '<span class="badge bg-light text-dark">' + esc(self.describeFeatureBadge(f)) + '</span>';
        }).join("") || '<span class="text-muted">No features yet</span>') +
        '</div>';

      if (window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById("featuresModal")).show();
      }
    },

    /**
     * Show success message
     */
};

export default modalMethods;
