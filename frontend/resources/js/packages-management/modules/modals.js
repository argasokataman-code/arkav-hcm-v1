import { API_BASE, API_ADDONS_BASE, FEATURE_LIMIT_INPUT_CODE, apiRequest, esc, getDefaultFeatureCatalog, getFeatureLibrary, getIncludedPackageFeatures, isPackageFeatureIncluded, getModuleForCode } from "../shared.js";

const WIZARD_TOTAL_STEPS = 3;

const modalMethods = {
    // ─── Package Wizard ────────────────────────────────────────────────────────

    initPackageWizard: function () {
      const self = this;
      document.getElementById('pkg_wizard_next')?.addEventListener('click', function () {
        self._wizardNext();
      });
      document.getElementById('pkg_wizard_back')?.addEventListener('click', function () {
        self._wizardBack();
      });
    },

    _wizardGoToStep: function (step) {
      const fieldsets = document.querySelectorAll('[data-pkg-step]');
      const navItems = document.querySelectorAll('[data-pkg-wizard-nav]');

      fieldsets.forEach(function (fs) {
        const n = parseInt(fs.getAttribute('data-pkg-step'), 10);
        if (n === step) {
          fs.style.display = 'block';
        } else {
          fs.style.display = 'none';
        }
      });

      navItems.forEach(function (li) {
        const n = parseInt(li.getAttribute('data-pkg-wizard-nav'), 10);
        li.classList.remove('active', 'activated');
        if (n < step) li.classList.add('activated');
        else if (n === step) li.classList.add('active');
      });

      const backBtn = document.getElementById('pkg_wizard_back');
      const nextBtn = document.getElementById('pkg_wizard_next');
      const saveBtn = document.getElementById('pkg_wizard_save');
      if (backBtn) backBtn.style.display = step > 1 ? '' : 'none';
      if (nextBtn) nextBtn.classList.toggle('d-none', step === WIZARD_TOTAL_STEPS);
      if (saveBtn) saveBtn.classList.toggle('d-none', step !== WIZARD_TOTAL_STEPS);

      if (step === WIZARD_TOTAL_STEPS) {
        this._buildPackageReviewSummary();
      }

      this._currentWizardStep = step;
      this.resetPackageModalScrollState();
    },

    _wizardNext: function () {
      const cur = this._currentWizardStep || 1;
      if (cur === 1) {
        const name = (document.getElementById('input_package_name')?.value || '').trim();
        const price = (document.getElementById('input_package_price')?.value || '').trim();
        const cycle = document.getElementById('input_package_cycle')?.value || '';
        if (!name) { this.showError('Package name wajib diisi.'); return; }
        if (!price) { this.showError('Harga paket wajib diisi.'); return; }
        if (!cycle) { this.showError('Billing cycle wajib dipilih.'); return; }
      }
      if (cur === 2) {
        const selected = typeof this.getSelectedFeatureCodes === 'function'
          ? this.getSelectedFeatureCodes()
          : [];
        const coreSelected = typeof this.getSelectedFeatureCodes === 'function'
          ? this.getSelectedFeatureCodes('core')
          : [];
        if (!selected.length) {
          this.showError('Pilih minimal 1 fitur (Core atau Addon) untuk paket ini.');
          return;
        }
        if (!coreSelected.length) {
          this.showError('Pilih minimal 1 fitur Core untuk paket ini.');
          return;
        }
      }
      this._wizardGoToStep(Math.min(cur + 1, WIZARD_TOTAL_STEPS));
    },

    _wizardBack: function () {
      this._wizardGoToStep(Math.max((this._currentWizardStep || 1) - 1, 1));
    },

    _buildPackageReviewSummary: function () {
      const el = document.getElementById('pkg_review_summary');
      if (!el) return;

      const name = (document.getElementById('input_package_name')?.value || '').trim() || '-';
      const desc = (document.getElementById('input_package_description')?.value || '').trim();
      const price = (document.getElementById('input_package_price')?.value || '0');
      const cycle = document.getElementById('input_package_cycle')?.value || '-';
      const isActive = !!document.getElementById('input_package_active')?.checked;
      const maxEmp = (document.getElementById('input_package_max_employees')?.value || '').trim();

      const cycleLabel = cycle === 'monthly' ? 'Bulanan' : cycle === 'yearly' ? 'Tahunan' : cycle;

      el.innerHTML = [
        `<div class="d-flex justify-content-between align-items-start py-2 border-bottom">
          <span class="text-muted small fw-medium">Nama</span>
          <span class="fw-semibold small text-end ms-3">${esc(name)}</span>
        </div>`,
        desc ? `<div class="d-flex justify-content-between align-items-start py-2 border-bottom">
          <span class="text-muted small fw-medium">Deskripsi</span>
          <span class="small text-end ms-3 text-muted" style="max-width:60%">${esc(desc)}</span>
        </div>` : '',
        `<div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <span class="text-muted small fw-medium">Harga</span>
          <span class="fw-semibold small">Rp ${Number(price).toLocaleString('id-ID')} <span class="text-muted fw-normal">/ ${esc(cycleLabel)}</span></span>
        </div>`,
        `<div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <span class="text-muted small fw-medium">Maks. Employee</span>
          <span class="fw-semibold small">${maxEmp ? Number(maxEmp).toLocaleString('id-ID') + ' org' : 'Unlimited'}</span>
        </div>`,
        `<div class="d-flex justify-content-between align-items-center py-2">
          <span class="text-muted small fw-medium">Status</span>
          <span class="badge ${isActive ? 'text-bg-success' : 'text-bg-warning'}">${isActive ? 'Active' : 'Inactive'}</span>
        </div>`,
      ].join('');

      // Build tier-split feature chips
      const self = this;
      function buildChips(codes, containerId, countId) {
        const container = document.getElementById(containerId);
        const countEl = document.getElementById(countId);
        if (!container) return;
        if (countEl) countEl.textContent = String(codes.length);
        if (!codes.length) {
          container.innerHTML = '<span class="text-muted small fst-italic">Tidak ada</span>';
          return;
        }
        container.innerHTML = codes.map(function (code) {
          const label = typeof self.featureLabelFromCode === 'function' ? self.featureLabelFromCode(code) : code;
          return '<span class="package-feature-preview-chip">' + esc(label) + '</span>';
        }).join('');
      }

      const coreCodes = typeof this.getSelectedFeatureCodes === 'function' ? this.getSelectedFeatureCodes('core') : [];
      const addonCodes = typeof this.getSelectedFeatureCodes === 'function' ? this.getSelectedFeatureCodes('addon') : [];
      buildChips(coreCodes, 'pkg_review_core_chips', 'pkg_review_core_count');
      buildChips(addonCodes, 'pkg_review_addon_chips', 'pkg_review_addon_count');

      // Update total count badge
      const totalCountEl = document.querySelector('[data-feature-selected-count]');
      if (totalCountEl) totalCountEl.textContent = String(coreCodes.length + addonCodes.length);
    },

    // ─── Modal lifecycle ───────────────────────────────────────────────────────

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
      if (title) title.textContent = "Add Package";
      this.currentEditSnapshot = null;
      this.currentPricingDirty = false;
      this.featureLimitDrafts = {};
      this.featureHealthcheckSummary = null;
      this.packageComplianceSnapshot = null;
      const maxEmployeesInput = document.getElementById("input_package_max_employees");
      if (maxEmployeesInput) {
        maxEmployeesInput.value = "";
      }
      // Hide addon assignment section for new packages
      const addonSection = document.getElementById('pkg_addon_assignment_section');
      if (addonSection) addonSection.style.display = 'none';
      this._wizardGoToStep(1);
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
            const submitBtn = document.getElementById('pkg_wizard_save');
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

            const selectedByTier = { core: [], addon: [] };
            (pkg.features || []).forEach(function (f) {
              if (isPackageFeatureIncluded(f)) {
                const t = f.tier === 'addon' ? 'addon' : 'core';
                selectedByTier[t].push(f.code);
              }
            });
            self.renderFeatureCatalog(
              getDefaultFeatureCatalog().concat(
                selectedByTier.core.concat(selectedByTier.addon)
              )
            );
            const featureSearchInput = document.getElementById("input_package_feature_search");
            if (featureSearchInput) {
              featureSearchInput.value = "";
            }
            // Set include checkboxes + tier radios for each included feature
            const allFeatureCodes = selectedByTier.core.concat(selectedByTier.addon);
            document.querySelectorAll("input[name='package_feature_include']").forEach(function (el) {
              const isIncluded = allFeatureCodes.indexOf(el.value) !== -1;
              el.checked = isIncluded;
              if (isIncluded) {
                const safeCode = el.value.replace(/[^a-zA-Z0-9_]/g, '_');
                const isAddon = selectedByTier.addon.indexOf(el.value) !== -1;
                const tierValue = isAddon ? 'addon' : 'core';
                const tierRadio = document.querySelector('input[name="pkg_feat_tier_' + safeCode + '"][value="' + tierValue + '"]');
                if (tierRadio) tierRadio.checked = true;
                const tierRow = document.getElementById('pkg_feat_' + safeCode + '_tier_row');
                if (tierRow) tierRow.style.display = 'block';
              }
              self.handleFeatureCheckboxChange(el);
            });
            self.updateFeatureSelectionSummary();

            self.currentEditId = id;
            self.loadPackageAddonAssignments(pkg.id || id);
            self._wizardGoToStep(1);
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

      // Group features by module
      const MODULE_ORDER = ['employee', 'attendance', 'leave', 'payroll', 'performance', 'lifecycle', 'saas'];
      const MODULE_LABELS = {
        employee:    'Employee',
        attendance:  'Attendance',
        leave:       'Leave',
        payroll:     'Payroll',
        performance: 'Performance',
        lifecycle:   'Lifecycle',
        saas:        'System & AI',
      };
      const MODULE_ICONS = {
        employee:    'ti ti-users',
        attendance:  'ti ti-calendar-check',
        leave:       'ti ti-beach',
        payroll:     'ti ti-coin',
        performance: 'ti ti-chart-bar',
        lifecycle:   'ti ti-arrows-exchange',
        saas:        'ti ti-settings',
      };

      const byModule = {};
      MODULE_ORDER.forEach(function (m) { byModule[m] = []; });

      included.forEach(function (feature) {
        const code = typeof feature === 'string' ? feature : feature?.code;
        if (!code) return;
        const module = getModuleForCode(code);
        if (!byModule[module]) byModule[module] = [];
        byModule[module].push(feature);
      });

      const coreCount = included.filter(function (f) {
        return !(typeof f === 'object' && f !== null && f.tier === 'addon');
      }).length;
      const addonCount = included.length - coreCount;

      // Build HTML
      let html = '';

      // Header summary row
      html += '<div class="d-flex align-items-center gap-3 mb-4">';
      if (pkg.color) {
        html += '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' + esc(pkg.color) + ';flex-shrink:0"></span>';
      }
      html += '<div>';
      html += '<div class="fw-bold fs-6">' + esc(pkg.name) + '</div>';
      html += '<div class="text-muted small">' + esc(pkg.code || '') + '</div>';
      html += '</div>';
      html += '<div class="ms-auto d-flex gap-2">';
      html += '<span class="badge" style="background:#f2f4f7;color:#344054;font-weight:500;font-size:0.72rem">' + included.length + ' total</span>';
      if (coreCount > 0) html += '<span class="badge" style="background:#ecfdf3;color:#027a48;font-weight:500;font-size:0.72rem">' + coreCount + ' core</span>';
      if (addonCount > 0) html += '<span class="badge" style="background:#eff8ff;color:#175cd3;font-weight:500;font-size:0.72rem">' + addonCount + ' add-on</span>';
      html += '</div>';
      html += '</div>';

      // Module sections
      const activeModules = MODULE_ORDER.filter(function (m) { return byModule[m] && byModule[m].length > 0; });
      if (activeModules.length === 0) {
        html += '<div class="text-muted small text-center py-4">No features assigned yet.</div>';
      } else {
        html += '<div class="d-flex flex-column gap-4">';
        activeModules.forEach(function (module) {
          const features = byModule[module];
          const icon = MODULE_ICONS[module] || 'ti ti-puzzle';
          const label = MODULE_LABELS[module] || module;

          html += '<div>';
          html += '<div class="d-flex align-items-center gap-2 mb-2">';
          html += '<i class="' + icon + ' text-muted" style="font-size:0.9rem"></i>';
          html += '<span style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#98a2b3">' + esc(label) + '</span>';
          html += '<span class="text-muted" style="font-size:0.7rem">(' + features.length + ')</span>';
          html += '</div>';
          html += '<div class="d-flex flex-wrap gap-2">';

          features.forEach(function (feature) {
            const code = typeof feature === 'string' ? feature : feature?.code;
            const displayName = typeof self.featureLabelFromCode === 'function'
              ? self.featureLabelFromCode(code)
              : code.split('_').map(function (p) { return p.charAt(0).toUpperCase() + p.slice(1); }).join(' ');
            const isAddon = (typeof feature === 'object' && feature !== null && feature.tier === 'addon');
            const limit = typeof feature === 'object' ? feature?.limit : null;

            let label = esc(displayName);
            if (code === FEATURE_LIMIT_INPUT_CODE && limit !== null && limit !== undefined && limit !== '') {
              label = esc(displayName) + ': <strong>' + Number(limit).toLocaleString('id-ID') + ' org</strong>';
            }

            if (isAddon) {
              html += '<span style="display:inline-flex;align-items:center;gap:4px;font-size:0.72rem;font-weight:500;color:#175cd3;background:#eff8ff;border:1px solid #b2ddff;border-radius:5px;padding:3px 8px">';
              html += '<i class="ti ti-puzzle" style="font-size:0.75rem"></i>' + label;
              html += '</span>';
            } else {
              html += '<span style="font-size:0.72rem;font-weight:500;color:#344054;background:#f2f4f7;border:1px solid #e4e7ec;border-radius:5px;padding:3px 8px">';
              html += label;
              html += '</span>';
            }
          });

          html += '</div>';
          html += '</div>';
        });
        html += '</div>';
      }

      body.innerHTML = html;

      if (window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById("featuresModal")).show();
      }
    },

    /**
     * Show success message
     */

    // ─── Package Add-on Assignments ──────────────────────────────────────────

    loadPackageAddonAssignments: function (packageUuid) {
      const self = this;
      const section = document.getElementById('pkg_addon_assignment_section');
      const listEl = document.getElementById('pkg_addon_assign_list');
      const countEl = document.getElementById('pkg_addon_assign_count');

      if (!section || !listEl) return;
      section.style.display = 'block';
      listEl.innerHTML = '<span class="text-muted small fst-italic">Memuat...</span>';
      if (countEl) countEl.textContent = '0';

      // Load currently assigned addons for this package
      apiRequest('GET', API_BASE + '/' + packageUuid + '/addon-assignments', null)
        .then(function (res) {
          const assigned = new Set((res.data || []).map(function (a) { return Number(a.id); }));
          self.renderPackageAddonAssignments(packageUuid, assigned);
        })
        .catch(function () {
          if (listEl) listEl.innerHTML = '<span class="text-danger small">Gagal memuat. Refresh halaman.</span>';
        });
    },

    renderPackageAddonAssignments: function (packageUuid, assignedIds) {
      const listEl = document.getElementById('pkg_addon_assign_list');
      const countEl = document.getElementById('pkg_addon_assign_count');
      if (!listEl) return;

      const allAddons = Array.isArray(this.addons) ? this.addons : [];
      if (!allAddons.length) {
        listEl.innerHTML = '<span class="text-muted small fst-italic">Belum ada add-on di katalog global.</span>';
        if (countEl) countEl.textContent = '0';
        return;
      }

      if (countEl) countEl.textContent = String(assignedIds.size);

      listEl.innerHTML = allAddons.map(function (addon) {
        const addonId = Number(addon.id);
        const checked = assignedIds.has(addonId) ? 'checked' : '';
        const safeId = 'pkg_addon_chk_' + addonId;
        return '<label class="d-flex align-items-center gap-2 border rounded px-3 py-2 cursor-pointer" style="font-size:.82rem;cursor:pointer" for="' + safeId + '">'
          + '<input type="checkbox" class="form-check-input m-0 flex-shrink-0" id="' + safeId + '" '
          + 'data-addon-assign-toggle="' + addonId + '" '
          + 'data-addon-assign-package="' + esc(packageUuid) + '" '
          + (checked ? 'checked' : '') + '>'
          + '<span><span class="fw-medium">' + esc(addon.name || addon.code) + '</span>'
          + '<span class="text-muted ms-2">' + esc(String(addon.code || '').toUpperCase()) + '</span></span>'
          + '</label>';
      }).join('');
    },

    handleAddonAssignmentToggle: function (checkbox) {
      const self = this;
      const addonId = checkbox.getAttribute('data-addon-assign-toggle');
      const packageUuid = checkbox.getAttribute('data-addon-assign-package');
      if (!addonId || !packageUuid) return;

      const isChecked = checkbox.checked;
      checkbox.disabled = true;

      const method = isChecked ? 'POST' : 'DELETE';
      const url = isChecked
        ? API_BASE + '/' + packageUuid + '/addon-assignments'
        : API_BASE + '/' + packageUuid + '/addon-assignments/' + addonId;
      const body = isChecked ? { addon_id: addonId } : null;

      apiRequest(method, url, body)
        .then(function () {
          checkbox.disabled = false;
          // Update count badge
          const listEl = document.getElementById('pkg_addon_assign_list');
          const countEl = document.getElementById('pkg_addon_assign_count');
          if (countEl && listEl) {
            const checkedCount = listEl.querySelectorAll('input[type=checkbox]:checked').length;
            countEl.textContent = String(checkedCount);
          }
        })
        .catch(function () {
          // Revert on failure
          checkbox.checked = !isChecked;
          checkbox.disabled = false;
          self.showError('Gagal menyimpan perubahan add-on. Coba lagi.');
        });
    },
};

export default modalMethods;
