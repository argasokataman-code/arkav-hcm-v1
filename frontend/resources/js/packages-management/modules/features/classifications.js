import { API_FEATURE_CLASSIFICATIONS, API_FEATURE_CLASSIFICATIONS_BACKFILL, apiRequest, esc, getFeatureLibrary, getRuntimeFeatureDisplayName, getFeatureClassificationOverrides } from "../../shared.js";

const classificationsMethods = {
  showFeatureClassificationsModal: function () {
    const modalEl = document.getElementById("featureClassificationModal");
    if (!modalEl) return;

    const container = modalEl.querySelector("#feature_classifications_container");
    if (!container) return;

    container.innerHTML = '<div class="text-muted">Loading...</div>';

    const self = this;

    // Fetch DB overrides (admin-only endpoint)
    apiRequest("GET", API_FEATURE_CLASSIFICATIONS, null)
      .then(function (resp) {
        if (!resp || !resp.success) {
          container.innerHTML = '<div class="text-danger">Failed to load classifications.</div>';
          return;
        }

        const entries = Array.isArray(resp.data) ? resp.data : [];
        self.renderClassifications(container, entries);
        if (window.bootstrap) {
          window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
      })
      .catch(function (err) {
        console.error(err);
        container.innerHTML = '<div class="text-danger">Error loading classifications.</div>';
      });
  },

  renderClassifications: function (container, entries) {
    const self = this;
    // Build lookup for feature names and runtime tiers from runtime catalog
    const featureLibrary = getFeatureLibrary();
    const featureMap = new Map();
    const runtimeTierMap = new Map();
    (Array.isArray(featureLibrary) ? featureLibrary : []).forEach(function (group) {
      (Array.isArray(group.features) ? group.features : []).forEach(function (f) {
        if (f && f.code) {
          featureMap.set(f.code, f.name || f.title || f.code);
          runtimeTierMap.set(f.code, f.tier || 'addon');
        }
      });
    });

    // Also include any server-provided overrides not in the runtime catalog
    const serverOverrides = getFeatureClassificationOverrides() || {};
    Object.keys(serverOverrides || {}).forEach(function (code) {
      if (!featureMap.has(code)) {
        featureMap.set(code, code);
      }
    });

    // Map DB entries by feature_code for quick lookup
    const overridesMap = new Map();
    (Array.isArray(entries) ? entries : []).forEach(function (row) {
      if (row && row.feature_code) {
        overridesMap.set(row.feature_code, { id: row.id, tier: row.tier });
      }
    });

    // Build full feature code list (runtime + server overrides + db overrides)
    const codesSet = new Set();
    Array.from(featureMap.keys()).forEach((c) => codesSet.add(c));
    Object.keys(serverOverrides || {}).forEach((c) => codesSet.add(c));
    Array.from(overridesMap.keys()).forEach((c) => codesSet.add(c));

    const codes = Array.from(codesSet).sort();


    // Render create form + search + table (improved visuals)
    let html = '';
    html += '<div class="mb-3">';
    html += '<button type="button" class="btn btn-sm btn-outline-secondary" id="btn_backfill_classifications"><i class="ti ti-database-import me-1"></i>Backfill Catalog Defaults</button>';
    html += '</div>';
    html += '<div class="mb-3">';
    html += '<input id="feature_class_search" class="form-control" placeholder="Cari fitur (code atau nama)" />';
    html += '</div>';

    html += '<div class="table-responsive"><table class="table table-sm">';
    html += '<thead><tr><th>Feature Code</th><th>Name</th><th>Source</th><th>Tier</th></tr></thead><tbody>';

    codes.forEach(function (code) {
      const rowOverride = overridesMap.get(code);
      const idAttr = rowOverride && rowOverride.id ? rowOverride.id : '';
      const currentTier = rowOverride ? rowOverride.tier : (serverOverrides && serverOverrides[code]) || runtimeTierMap.get(code) || 'addon';
      const codeEsc = esc(code || '');
      const name = esc(featureMap.get(code) || getRuntimeFeatureDisplayName(code, code));

      // determine source: db override > server override > runtime catalog
      let source = 'runtime';
      if (rowOverride) source = 'db';
      else if (serverOverrides && serverOverrides[code]) source = 'server';

      const sourceBadge = (source === 'db') ? '<span class="badge bg-warning text-dark">DB</span>' : (source === 'server') ? '<span class="badge bg-info text-dark">Server</span>' : '<span class="badge bg-light text-muted">Runtime</span>';

      const tierBadge = (currentTier === 'mvp') ? '<span class="badge bg-success">Core</span>' : '<span class="badge bg-secondary">Add-on</span>';

      html += '<tr data-feature-code="' + codeEsc + '" data-class-id="' + (idAttr || '') + '">';
      html += '<td>' + codeEsc + '</td>';
      html += '<td>' + name + '</td>';
      html += '<td>' + sourceBadge + '</td>';
      html += '<td>';
      html += '<div class="d-flex align-items-center gap-2">';
      html += '<div class="form-check form-switch mb-0">';
      html += '<input class="form-check-input classification-toggle" type="checkbox" id="toggle_' + codeEsc + '" data-code="' + codeEsc + '"' + (currentTier === 'mvp' ? ' checked' : '') + ' aria-label="Toggle ' + codeEsc + '">';
      html += '</div>';
      html += '<span class="tier-badge-placeholder ms-2">' + tierBadge + '</span>';
      html += '</div>';
      html += '</td>';
      html += '</tr>';
    });

    html += '</tbody></table></div>';

    container.innerHTML = html;

    // Wire backfill button
    const backfillBtn = container.querySelector('#btn_backfill_classifications');
    if (backfillBtn) {
      backfillBtn.addEventListener('click', (e) => {
        e.preventDefault();
        backfillBtn.disabled = true;
        backfillBtn.textContent = 'Running backfill...';
        apiRequest('POST', API_FEATURE_CLASSIFICATIONS_BACKFILL, null)
          .then(function (resp) {
            backfillBtn.disabled = false;
            backfillBtn.innerHTML = '<i class="ti ti-database-import me-1"></i>Backfill Catalog Defaults';
            if (resp && resp.success) {
              const d = resp.data || {};
              alert('Backfill selesai: ' + (d.inserted || 0) + ' inserted, ' + (d.skipped || 0) + ' skipped (' + (d.total || 0) + ' total).\n\nHalaman akan reload.');
              // Reload modal contents
              self.showFeatureClassificationsModal();
            } else {
              alert('Backfill gagal: ' + (resp && resp.message ? resp.message : 'Unknown error'));
            }
          })
          .catch(function (err) {
            backfillBtn.disabled = false;
            backfillBtn.innerHTML = '<i class="ti ti-database-import me-1"></i>Backfill Catalog Defaults';
            console.error(err);
            alert('Backfill error: ' + err.message);
          });
      });
    }

    // Wire search input (debounced)
    const searchInput = container.querySelector('#feature_class_search');
    let searchTimer = null;
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
          const q = (searchInput.value || '').trim().toLowerCase();
          const rows = container.querySelectorAll('tbody tr');
          rows.forEach(function (r) {
            const code = (r.getAttribute('data-feature-code') || '').toLowerCase();
            const name = (r.children[1] && r.children[1].innerText || '').toLowerCase();
            const show = !q || code.indexOf(q) !== -1 || name.indexOf(q) !== -1;
            r.style.display = show ? '' : 'none';
          });
        }, 160);
      });
    }

    // Bind toggles: show confirmation modal before applying change
    container.querySelectorAll('.classification-toggle').forEach((el) => {
      el.addEventListener('change', async (ev) => {
        const code = el.getAttribute('data-code');
        const checked = !!el.checked;
        const desiredTier = checked ? 'mvp' : 'addon';
        const label = checked ? 'Core' : 'Add-on';

        const message = 'Apakah Anda yakin ingin mengubah klasifikasi fitur "' + code + '" menjadi ' + label + '?';
        const title = 'Konfirmasi perubahan klasifikasi';

        // Use ArcavUi confirm modal if available; otherwise cancel (do not use native confirm)
        const confirmed = (window.ArcavUi && typeof window.ArcavUi.confirm === 'function')
          ? await window.ArcavUi.confirm(message, title)
          : false;

        if (!confirmed) {
          // revert toggle
          el.checked = !checked;
          return;
        }

        // proceed with create/update
        const row = container.querySelector('tr[data-feature-code="' + code + '"]');
        const id = row ? row.getAttribute('data-class-id') : null;
        if (id && id.length) {
          this.handleUpdateClassification(id, desiredTier, row);
        } else {
          this.handleCreateClassificationForRow(code, desiredTier, row);
        }
      });
    });

    // (No inline reset buttons — toggles control classification)
  },

  handleCreateClassificationForRow: function (code, tier, rowElement) {
    const payload = { feature_code: code, tier: tier };
    const self = this;
    apiRequest('POST', API_FEATURE_CLASSIFICATIONS, payload)
      .then(function (resp) {
        if (resp && resp.success) {
          if (self.showSuccess) self.showSuccess('Classification added');
          // inline update for the specific row
          try {
            if (rowElement) {
              rowElement.setAttribute('data-class-id', resp.data && resp.data.id ? resp.data.id : '');
              const sourceCell = rowElement.children[2];
              if (sourceCell) sourceCell.innerHTML = '<span class="badge bg-warning text-dark">DB</span>';
              const tierPlaceholder = rowElement.querySelector('.tier-badge-placeholder');
              const badgeHtml = (tier === 'mvp') ? '<span class="badge bg-success">Core</span>' : '<span class="badge bg-secondary">Add-on</span>';
              if (tierPlaceholder) tierPlaceholder.innerHTML = badgeHtml;
              const toggle = rowElement.querySelector('.classification-toggle');
              if (toggle) toggle.checked = (tier === 'mvp');

              // No reset button UI; toggles store explicit addon/core override
            }
          } catch (e) {
            console.error('Inline update failed', e);
          }

          if (typeof self.loadFeatureCatalog === 'function') {
            self.loadFeatureCatalog().finally(function () {
              if (typeof self.loadAddons === 'function') {
                try { self.loadAddons(); } catch (e) { /* ignore */ }
              }
            });
          }
        } else {
          if (self.showError) self.showError(resp?.error?.message || 'Failed to add classification');
        }
      })
      .catch(function (err) {
        console.error(err);
        if (self.showError) self.showError('Error adding classification');
        // revert toggle if present
        if (rowElement) {
          const toggle = rowElement.querySelector('.classification-toggle');
          if (toggle) toggle.checked = !toggle.checked;
        }
      });
  },

  handleCreateClassification: function (container) {
    const codeInput = container.querySelector('#input_new_class_feature_code');
    const tierSelect = container.querySelector('#input_new_class_tier');
    if (!codeInput || !tierSelect) return;

    const code = (codeInput.value || '').trim();
    const tier = tierSelect.value || 'addon';
    if (!code) {
      if (this.showError) this.showError('Feature code is required');
      return;
    }

    const payload = { feature_code: code, tier: tier };
    const self = this;
    apiRequest('POST', API_FEATURE_CLASSIFICATIONS, payload)
      .then(function (resp) {
        if (resp && resp.success) {
          if (self.showSuccess) self.showSuccess('Classification added');
          codeInput.value = '';
          // refresh modal list
          self.showFeatureClassificationsModal();
          // refresh feature catalog so UI reflects overrides and reload addons
          if (typeof self.loadFeatureCatalog === 'function') {
            self.loadFeatureCatalog().finally(function () {
              if (typeof self.loadAddons === 'function') {
                try { self.loadAddons(); } catch (e) { /* ignore */ }
              }
            });
          }
        } else {
          if (self.showError) self.showError(resp?.error?.message || 'Failed to add classification');
        }
      })
      .catch(function (err) {
        console.error(err);
        if (self.showError) self.showError('Error adding classification');
      });
  },

  handleUpdateClassification: function (id, tier, rowElement) {
    const self = this;
    apiRequest('PUT', API_FEATURE_CLASSIFICATIONS + '/' + id, { tier: tier })
      .then(function (resp) {
        if (resp && resp.success) {
          if (self.showSuccess) self.showSuccess('Classification updated');
          try {
            if (rowElement) {
              const tierPlaceholder = rowElement.querySelector('.tier-badge-placeholder');
              const badgeHtml = (tier === 'mvp') ? '<span class="badge bg-success">Core</span>' : '<span class="badge bg-secondary">Add-on</span>';
              if (tierPlaceholder) tierPlaceholder.innerHTML = badgeHtml;
              const toggle = rowElement.querySelector('.classification-toggle');
              if (toggle) toggle.checked = (tier === 'mvp');
              const sourceCell = rowElement.children[2];
              if (sourceCell) sourceCell.innerHTML = '<span class="badge bg-warning text-dark">DB</span>';
            } else {
              self.showFeatureClassificationsModal();
            }
          } catch (e) {
            console.error('Inline update failed', e);
          }

          if (typeof self.loadFeatureCatalog === 'function') {
            self.loadFeatureCatalog().finally(function () {
              if (typeof self.loadAddons === 'function') {
                try { self.loadAddons(); } catch (e) { /* ignore */ }
              }
            });
          }
        } else {
          if (self.showError) self.showError(resp?.error?.message || 'Failed to update classification');
          if (rowElement) {
            const toggle = rowElement.querySelector('.classification-toggle');
            if (toggle) toggle.checked = !toggle.checked;
          }
        }
      })
      .catch(function (err) {
        console.error(err);
        if (self.showError) self.showError('Error updating classification');
        if (rowElement) {
          const toggle = rowElement.querySelector('.classification-toggle');
          if (toggle) toggle.checked = !toggle.checked;
        }
      });
  },

  handleDeleteClassification: async function (id, rowElement) {
    const self = this;
    if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === 'function') {
      const ok = await window.ArcavUi.confirmDelete('Hapus classification ini?','Delete Classification');
      if (!ok) return;
    } else {
      if (!confirm('Hapus classification ini?')) return;
    }

    apiRequest('DELETE', API_FEATURE_CLASSIFICATIONS + '/' + id, null)
      .then(function (resp) {
        if (resp && resp.success) {
          if (self.showSuccess) self.showSuccess('Classification deleted');
          try {
            if (rowElement) {
              rowElement.removeAttribute('data-class-id');

              const serverOverrides = getFeatureClassificationOverrides() || {};
              const code = rowElement.getAttribute('data-feature-code');
              const sourceCell = rowElement.children[2];
              if (sourceCell) {
                if (serverOverrides && serverOverrides[code]) {
                  sourceCell.innerHTML = '<span class="badge bg-info text-dark">Server</span>';
                } else {
                  sourceCell.innerHTML = '<span class="badge bg-light text-muted">Runtime</span>';
                }
              }

              // determine runtime tier fallback
              const runtimeLib = getFeatureLibrary();
              let runtimeTier = 'addon';
              for (const g of (Array.isArray(runtimeLib) ? runtimeLib : [])) {
                for (const f of (Array.isArray(g.features) ? g.features : [])) {
                  if (f && f.code === code) {
                    runtimeTier = f.tier || 'addon';
                    break;
                  }
                }
              }
              const tierPlaceholder = rowElement.querySelector('.tier-badge-placeholder');
              const badgeHtml = (runtimeTier === 'mvp') ? '<span class="badge bg-success">Core</span>' : '<span class="badge bg-secondary">Add-on</span>';
              if (tierPlaceholder) tierPlaceholder.innerHTML = badgeHtml;
              const toggle = rowElement.querySelector('.classification-toggle');
              if (toggle) toggle.checked = (runtimeTier === 'mvp');
            } else {
              self.showFeatureClassificationsModal();
            }
          } catch (e) {
            console.error('Inline delete update failed', e);
          }

          if (typeof self.loadFeatureCatalog === 'function') {
            self.loadFeatureCatalog().finally(function () {
              if (typeof self.loadAddons === 'function') {
                try { self.loadAddons(); } catch (e) { /* ignore */ }
              }
            });
          }
        } else {
          if (self.showError) self.showError(resp?.error?.message || 'Failed to delete classification');
        }
      })
      .catch(function (err) {
        console.error(err);
        if (self.showError) self.showError('Error deleting classification');
      });
  },
};

export default classificationsMethods;
