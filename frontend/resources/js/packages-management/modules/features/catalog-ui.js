import { FEATURE_LIMIT_INPUT_CODE, esc, getDefaultFeatureCatalog, getFeatureLibrary, getIncludedPackageFeatures, getAddonClassificationMode, getTierForCode } from "../../shared.js";

const featureCatalogUiMethods = {
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
    const coreCodes = new Set(this.getSelectedFeatureCodes('core'));
    const addonCodes = new Set(this.getSelectedFeatureCodes('addon'));
    const allSelected = new Set([...coreCodes, ...addonCodes]);
    const groups = this.buildFeatureGroups(featureCodes, allSelected);

    function renderFeatureItem(feature, groupIndex, featureIndex) {
      const safeCode = String(feature.code).replace(/[^a-zA-Z0-9_]/g, '_');
      const itemId = 'pkg_feat_' + safeCode;
      const tierName = 'pkg_feat_tier_' + safeCode;
      const isIncluded = allSelected.has(feature.code);
      const isAddon = addonCodes.has(feature.code);
      const limitValue = limitDrafts[feature.code] ?? "";
      const limitLabel = feature.limitLabel || "Limit";
      const limitPlaceholder = feature.limitPlaceholder || "Masukkan limit";
      const limitSuffix = feature.limitSuffix || "";

      const tierHtml = (
        '<div class="feat-tier-selector" id="' + itemId + '_tier_row"' + (isIncluded ? '' : ' style="display:none"') + '>' +
        '<div class="btn-group btn-group-sm" role="group" aria-label="Tier fitur">' +
        '<input type="radio" class="btn-check" name="' + esc(tierName) + '" id="' + itemId + '_core" value="core"' + (!isAddon ? ' checked' : '') + '>' +
        '<label class="btn feat-tier-btn-core" for="' + itemId + '_core">Core</label>' +
        '<input type="radio" class="btn-check" name="' + esc(tierName) + '" id="' + itemId + '_addon" value="addon"' + (isAddon ? ' checked' : '') + '>' +
        '<label class="btn feat-tier-btn-addon" for="' + itemId + '_addon">Addon</label>' +
        '</div></div>'
      );

      const limitHtml = (feature.requiresLimit && isIncluded)
        ? '<div class="mt-2 ps-4 limit-input-row"><label class="form-label small text-muted mb-1" for="' + esc(itemId + '_limit') + '">' + esc(limitLabel) + '</label><div class="input-group input-group-sm"><input class="form-control" type="number" min="1" step="1" id="' + esc(itemId + '_limit') + '" data-feature-limit-input data-feature-limit-code="' + esc(feature.code) + '" placeholder="' + esc(limitPlaceholder) + '" value="' + esc(limitValue) + '"' + (isIncluded ? '' : ' disabled') + '>' + (limitSuffix ? '<span class="input-group-text">' + esc(limitSuffix) + '</span>' : '') + '</div></div>'
        : '';

      return (
        '<div class="package-feature-item" data-feature-item data-feature-filter="' + esc((feature.name + ' ' + feature.code + ' ' + (feature.description || '')).toLowerCase()) + '">' +
        '<div class="d-flex align-items-center gap-2">' +
        '<input type="checkbox" class="form-check-input flex-shrink-0" name="package_feature_include" id="' + esc(itemId + '_chk') + '" value="' + esc(feature.code) + '" data-feature-name="' + esc(feature.name) + '"' + (isIncluded ? ' checked' : '') + '>' +
        '<label class="pkg-feat-label flex-grow-1" for="' + esc(itemId + '_chk') + '">' +
        '<span class="package-feature-item-title">' + esc(feature.name) + '</span>' +
        '<span class="package-feature-item-desc">' + esc(feature.description || '') + '</span>' +
        '</label>' +
        tierHtml +
        '</div>' +
        limitHtml +
        '</div>'
      );
    }

    function renderGroupsAccordion(groupList) {
      if (!groupList.length) {
        return '<p class="text-muted small p-2 fst-italic">Tidak ada fitur tersedia.</p>';
      }
      return '<div class="accordion" id="feat_catalog_accordion">' +
        groupList.map(function (group, idx) {
          const collapseId = 'feat_catalog_group_' + group.module + '_' + String(idx);
          return (
            '<div class="accordion-item mb-2" data-feature-group data-feature-group-key="' + esc(group.module) + '">' +
            '<h2 class="accordion-header">' +
            '<div class="package-feature-group-head">' +
            '<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#' + esc(collapseId) + '">' +
            '<div class="d-flex align-items-center justify-content-between w-100 pe-2">' +
            '<div><div class="fw-semibold small">' + esc(group.title) + '</div><small class="text-muted">' + esc(group.description || '') + '</small></div>' +
            '<span class="badge text-bg-light" data-feature-group-count="' + esc(group.module) + '">' + esc(String(group.features.length)) + ' fitur</span>' +
            '</div></button>' +
            '<button type="button" class="package-feature-module-preview" data-feature-preview-module="' + esc(group.module) + '">Preview</button>' +
            '</div></h2>' +
            '<div id="' + esc(collapseId) + '" class="accordion-collapse collapse show">' +
            '<div class="accordion-body pt-2">' +
            group.features.map(function (feature, fIdx) {
              return renderFeatureItem(feature, idx, fIdx);
            }).join('') +
            '</div></div></div>'
          );
        }).join('') +
        '</div>';
    }

    catalogRoot.innerHTML = renderGroupsAccordion(groups);
    this.featureLimitDrafts = limitDrafts;
    this.syncMaxEmployeesFeatureFromTopField();
    this.updateFeatureSelectionSummary();
    this.filterFeatureCatalog(document.getElementById("input_package_feature_search")?.value || "");
    this.runFeatureCatalogHealthcheck(false);
    this.queueComplianceSnapshotRefresh(true);
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
    const normalized = String(keyword || "").trim().toLowerCase();
    const root = document.getElementById("input_package_feature_chips");
    if (!root) return;
    const groups = Array.from(root.querySelectorAll('[data-feature-group]'));
    groups.forEach(function (group) {
      let visibleCount = 0;
      group.querySelectorAll('[data-feature-item]').forEach(function (item) {
        const haystack = String(item.getAttribute('data-feature-filter') || '');
        const isVisible = !normalized || haystack.indexOf(normalized) >= 0;
        item.classList.toggle('d-none', !isVisible);
        if (isVisible) visibleCount += 1;
      });
      group.classList.toggle('d-none', visibleCount === 0);
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
};

export default featureCatalogUiMethods;
