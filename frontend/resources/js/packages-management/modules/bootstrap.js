import { API_FEATURE_CATALOG, FEATURE_LIMIT_INPUT_CODE, apiRequest, getDefaultFeatureCatalog, getFeatureLibrary, getRuntimeFeatureDisplayName, isRecognizedRuntimeFeatureCode, setFeatureLibrary } from "../shared.js";

function blurFocusedElementWithin(container) {
  const activeElement = document.activeElement;
  if (
    container &&
    activeElement &&
    typeof activeElement.blur === "function" &&
    container.contains(activeElement)
  ) {
    activeElement.blur();
  }
}

function bindModalFocusGuard(modalId) {
  const modalEl = document.getElementById(modalId);
  if (!modalEl || modalEl.dataset.arcavFocusGuardBound === "true") {
    return;
  }

  modalEl.addEventListener("hide.bs.modal", function () {
    blurFocusedElementWithin(modalEl);
  });

  modalEl.dataset.arcavFocusGuardBound = "true";
}

function bindPackageModalScrollReset(manager) {
  const modalEl = document.getElementById("packageModal");
  if (!modalEl || modalEl.dataset.arcavScrollResetBound === "true") {
    return;
  }

  const reset = function () {
    if (manager && typeof manager.resetPackageModalScrollState === "function") {
      manager.resetPackageModalScrollState();
    }
  };

  modalEl.addEventListener("show.bs.modal", reset);
  modalEl.addEventListener("shown.bs.modal", reset);
  modalEl.addEventListener("hidden.bs.modal", reset);
  modalEl.dataset.arcavScrollResetBound = "true";
}

const bootstrapMethods = {
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
      bindPackageModalScrollReset(this);
      [
        "packageModal",
        "addonModal",
        "featuresModal",
        "featureCatalogModal",
        "modulePreviewModal",
        "featureMatrixModal",
        "deleteModal",
      ].forEach(bindModalFocusGuard);
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
            setFeatureLibrary(response.data);
            self.renderFeatureCatalog(getDefaultFeatureCatalog(response.data));
            return;
          }

          setFeatureLibrary([]);
          self.renderFeatureCatalog(getDefaultFeatureCatalog());
        })
        .catch(function (err) {
          console.warn("Failed to load package feature catalog from backend runtime source.", err);
          setFeatureLibrary([]);
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

      setFeatureLibrary([
        {
          module: "runtime",
          title: "Runtime Features",
          description: "Katalog dibentuk dinamis dari data package runtime saat endpoint feature catalog belum tersedia.",
          features: Array.from(featureLookup.values()),
        },
      ]);

      this.renderFeatureCatalog(getDefaultFeatureCatalog(getFeatureLibrary()));
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
          self.queueComplianceSnapshotRefresh();
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
        self.queueComplianceSnapshotRefresh();
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
          self.queueComplianceSnapshotRefresh();
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
};

export default bootstrapMethods;
