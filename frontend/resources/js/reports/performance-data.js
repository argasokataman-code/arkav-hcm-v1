/* global bootstrap */

(function () {
  'use strict';

  const BASE = '/v1/hcm/performance';
  const performanceUtils = window.ArcavPerformanceUtils || {};
  const esc = performanceUtils.esc || ((s) => String(s ?? ''));
  const notify = performanceUtils.notify || (() => {});
  const getAuthHeaders = performanceUtils.getAuthHeaders || (() => ({}));
  const apiRequest = performanceUtils.apiRequest || (async function () { throw new Error('Performance utils not loaded'); });
  const apiErrorMessage = performanceUtils.apiErrorMessage || ((err) => err?.message || 'Terjadi kesalahan. Silakan coba lagi.');

  // -------------------------
  // Performance Indicator (templates + items)
  // -------------------------
  const templatesTbody = document.querySelector('[data-arcav-perf-templates-tbody]');
  const templateSearch = document.querySelector('[data-arcav-perf-template-search]');
  const templateReload = document.querySelector('[data-arcav-perf-template-reload]');

  const templateForm = document.querySelector('[data-arcav-perf-template-form]');
  const templateModalEl = document.getElementById('arcav_perf_template_modal');
  const templateModal = templateModalEl ? bootstrap.Modal.getOrCreateInstance(templateModalEl) : null;
  const templateModalTitle = document.querySelector('[data-arcav-perf-template-modal-title]');
  const templateIdEl = document.querySelector('[data-arcav-perf-template-id]');
  const itemsTbody = document.querySelector('[data-arcav-perf-items-tbody]');
  const templateDepartmentSelect = document.querySelector('[data-arcav-perf-template-department]');
  const templateDesignationSelect = document.querySelector('[data-arcav-perf-template-designation]');

  const templateDetailModalEl = document.getElementById('arcav_perf_template_detail_modal');
  const templateDetailModal = templateDetailModalEl ? bootstrap.Modal.getOrCreateInstance(templateDetailModalEl) : null;
  const templateDetailName = document.querySelector('[data-arcav-perf-template-detail-name]');
  const templateDetailDepartment = document.querySelector('[data-arcav-perf-template-detail-department]');
  const templateDetailDesignation = document.querySelector('[data-arcav-perf-template-detail-designation]');
  const templateDetailStatus = document.querySelector('[data-arcav-perf-template-detail-status]');
  const templateDetailItemsTbody = document.querySelector('[data-arcav-perf-template-detail-items-tbody]');

  const itemForm = document.querySelector('[data-arcav-perf-item-form]');
  const itemModalEl = document.getElementById('arcav_perf_item_modal');
  const itemModal = itemModalEl ? bootstrap.Modal.getOrCreateInstance(itemModalEl) : null;
  const itemModalTitle = document.querySelector('[data-arcav-perf-item-modal-title]');
  const itemTemplateIdEl = document.querySelector('[data-arcav-perf-item-template-id]');
  const itemIdEl = document.querySelector('[data-arcav-perf-item-id]');

  let templates = [];
  let activeTemplate = null;
  let activeItems = [];
  let departmentOptionsLoaded = false;
  let designationOptionsLoaded = false;

  async function loadDepartmentsOptions() {
    if (!templateDepartmentSelect || departmentOptionsLoaded) return;
    templateDepartmentSelect.setAttribute('disabled', 'disabled');
    templateDepartmentSelect.innerHTML = '<option value="">Loading departments...</option>';
    try {
      const res = await apiRequest('GET', '/v1/hcm/departments');
      const rows = Array.isArray(res?.data) ? res.data : [];
      templateDepartmentSelect.innerHTML = '<option value="">— All departments —</option>';
      rows
        .filter((d) => d?.isActive !== false)
        .forEach((d) => {
          const opt = document.createElement('option');
          opt.value = String(d.name || '');
          opt.textContent = String(d.name || '');
          templateDepartmentSelect.appendChild(opt);
        });
      departmentOptionsLoaded = true;
      templateDepartmentSelect.removeAttribute('disabled');
    } catch (e) {
      templateDepartmentSelect.innerHTML = '<option value="">— (failed to load) —</option>';
      templateDepartmentSelect.removeAttribute('disabled');
      notify('error', apiErrorMessage(e));
    }
  }

  async function loadDesignationsOptions() {
    if (!templateDesignationSelect || designationOptionsLoaded) return;
    templateDesignationSelect.setAttribute('disabled', 'disabled');
    templateDesignationSelect.innerHTML = '<option value="">Loading designations...</option>';
    try {
      const res = await apiRequest('GET', '/v1/hcm/designations');
      const rows = Array.isArray(res?.data) ? res.data : [];
      templateDesignationSelect.innerHTML = '<option value="">— All designations —</option>';
      rows
        .filter((d) => d?.isActive !== false)
        .forEach((d) => {
          const opt = document.createElement('option');
          opt.value = String(d.name || '');
          opt.textContent = String(d.name || '');
          templateDesignationSelect.appendChild(opt);
        });
      designationOptionsLoaded = true;
      templateDesignationSelect.removeAttribute('disabled');
    } catch (e) {
      templateDesignationSelect.innerHTML = '<option value="">— (failed to load) —</option>';
      templateDesignationSelect.removeAttribute('disabled');
      notify('error', apiErrorMessage(e));
    }
  }

  function badgeActive(isActive) {
    if (isActive) {
      return '<span class="badge badge-success d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>Active</span>';
    }
    return '<span class="badge badge-danger d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>Inactive</span>';
  }

  function renderTemplates() {
    if (!templatesTbody) return;
    const q = (templateSearch?.value || '').trim().toLowerCase();
    const rows = templates.filter((t) => {
      if (!q) return true;
      return (
        String(t.name).toLowerCase().includes(q) ||
        String(t.department || '').toLowerCase().includes(q) ||
        String(t.designation || '').toLowerCase().includes(q)
      );
    });

    if (rows.length === 0) {
      templatesTbody.innerHTML =
        '<tr><td colspan="5" class="text-center text-muted py-4">Belum ada template.</td></tr>';
      return;
    }

    templatesTbody.innerHTML = rows
      .map((t) => {
        return `
          <tr>
            <td>
              <div class="fw-medium">${esc(t.name)}</div>
              <div class="text-muted fs-12">ID: ${t.id}</div>
            </td>
            <td>${esc(t.department || '—')}</td>
            <td>${esc(t.designation || '—')}</td>
            <td>${badgeActive(!!t.isActive)}</td>
            <td class="text-end">
              <div class="d-inline-flex gap-2">
                <button type="button" class="btn btn-sm btn-white" data-action="view" data-id="${t.id}">
                  <i class="ti ti-eye me-1"></i>View
                </button>
                <button type="button" class="btn btn-sm btn-white" data-action="edit" data-id="${t.id}">
                  <i class="ti ti-pencil me-1"></i>Template
                </button>
                <button type="button" class="btn btn-sm btn-danger" data-action="delete" data-id="${t.id}">
                  Delete
                </button>
              </div>
            </td>
          </tr>
        `;
      })
      .join('');
  }

  async function openTemplateDetail(t) {
    if (!templateDetailModal || !templateDetailItemsTbody) return;
    if (!t) return;
    if (templateDetailName) templateDetailName.textContent = t?.name || '—';
    if (templateDetailDepartment) templateDetailDepartment.textContent = t?.department || '—';
    if (templateDetailDesignation) templateDetailDesignation.textContent = t?.designation || '—';
    if (templateDetailStatus) templateDetailStatus.textContent = t?.isActive ? 'Active' : 'Inactive';
    templateDetailItemsTbody.innerHTML =
      '<tr><td colspan="4" class="text-center text-muted py-3">Memuat items...</td></tr>';
    templateDetailModal.show();

    try {
      const res = await apiRequest('GET', `${BASE}/indicator-templates/${t.id}/items`);
      const rows = Array.isArray(res?.data) ? res.data : [];
      if (!rows.length) {
        templateDetailItemsTbody.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted py-3">Belum ada item.</td></tr>';
        return;
      }
      templateDetailItemsTbody.innerHTML = rows
        .map((i) => {
          const weight = i.section === 'kpi' ? (i.weight ?? 0) : '—';
          const scale = i.section === 'behavioral' ? `${i.ratingScaleMin}-${i.ratingScaleMax}` : '0-100';
          return `
            <tr>
              <td><span class="badge badge-soft-primary">${esc(i.section)}</span></td>
              <td class="text-break">
                <div class="fw-medium">${esc(i.title)}</div>
                ${i.description ? `<div class="text-muted fs-12">${esc(i.description)}</div>` : ''}
              </td>
              <td>${esc(weight)}</td>
              <td>${esc(scale)}</td>
            </tr>
          `;
        })
        .join('');
    } catch (e) {
      const msg = apiErrorMessage(e);
      templateDetailItemsTbody.innerHTML =
        `<tr><td colspan="4" class="text-center text-danger py-3">Gagal memuat items.<div class="text-muted fs-12 mt-1">${esc(msg)}</div></td></tr>`;
    }
  }

  async function loadTemplates() {
    if (!templatesTbody) return;
    templatesTbody.innerHTML =
      '<tr><td colspan="5" class="text-center text-muted py-4">Memuat templates...</td></tr>';
    try {
      const res = await apiRequest('GET', `${BASE}/indicator-templates`);
      templates = Array.isArray(res?.data) ? res.data : [];
      renderTemplates();
    } catch (e) {
      const msg = apiErrorMessage(e);
      templatesTbody.innerHTML =
        `<tr><td colspan="5" class="text-center text-danger py-4">Gagal memuat templates.<div class="text-muted fs-12 mt-1">${esc(msg)}</div></td></tr>`;
      notify('error', apiErrorMessage(e));
    }
  }

  function renderItems() {
    if (!itemsTbody) return;
    if (!activeTemplate) {
      itemsTbody.innerHTML =
        '<tr><td colspan="5" class="text-center text-muted py-3">Simpan template dulu untuk mulai tambah item.</td></tr>';
      return;
    }
    if (!activeItems.length) {
      itemsTbody.innerHTML =
        '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada item.</td></tr>';
      return;
    }
    itemsTbody.innerHTML = activeItems
      .map((i) => {
        const weight = i.section === 'kpi' ? (i.weight ?? 0) : '—';
        const scale = i.section === 'behavioral' ? `${i.ratingScaleMin}-${i.ratingScaleMax}` : '0-100';
        return `
          <tr>
            <td><span class="badge badge-soft-primary">${esc(i.section)}</span></td>
            <td class="text-break">
              <div class="fw-medium">${esc(i.title)}</div>
              ${i.description ? `<div class="text-muted fs-12">${esc(i.description)}</div>` : ''}
            </td>
            <td>${esc(weight)}</td>
            <td>${esc(scale)}</td>
            <td class="text-end">
              <div class="d-inline-flex gap-2">
                <button type="button" class="btn btn-sm btn-white" data-item-action="edit" data-item-id="${i.id}">
                  Edit
                </button>
                <button type="button" class="btn btn-sm btn-danger" data-item-action="delete" data-item-id="${i.id}">
                  Delete
                </button>
              </div>
            </td>
          </tr>
        `;
      })
      .join('');
  }

  async function loadItems(templateId) {
    if (!itemsTbody) return;
    itemsTbody.innerHTML =
      '<tr><td colspan="5" class="text-center text-muted py-3">Memuat items...</td></tr>';
    try {
      const res = await apiRequest('GET', `${BASE}/indicator-templates/${templateId}/items`);
      activeItems = Array.isArray(res?.data) ? res.data : [];
      renderItems();
    } catch (e) {
      activeItems = [];
      renderItems();
      notify('error', apiErrorMessage(e));
    }
  }

  const templateItemsSection = document.querySelector('[data-arcav-perf-template-items-section]');

  function openTemplateModal(mode, template, focus = 'info') {
    if (!templateForm || !templateModal) return;
    templateForm.reset();
    activeTemplate = template || null;
    activeItems = [];
    renderItems();

    const name = templateForm.querySelector('[name="name"]');
    const dep = templateForm.querySelector('[name="department"]');
    const des = templateForm.querySelector('[name="designation"]');
    const isActive = templateForm.querySelector('[name="isActive"]');

    loadDepartmentsOptions();
    loadDesignationsOptions();

    if (mode === 'edit' && template) {
      templateModalTitle.textContent = 'Edit Template';
      templateIdEl.value = template.id;
      name.value = template.name || '';
      dep.value = template.department || '';
      des.value = template.designation || '';
      isActive.checked = !!template.isActive;
      loadItems(template.id);
    } else {
      templateModalTitle.textContent = 'Add Template';
      templateIdEl.value = '';
      isActive.checked = true;
    }
    templateModal.show();

    // UX: "Items" button should jump to items section.
    setTimeout(() => {
      if (focus === 'items' && templateItemsSection) {
        templateItemsSection.scrollIntoView({ block: 'start', behavior: 'smooth' });
      } else if (focus === 'info' && name) {
        try {
          name.focus();
        } catch (_) {}
      }
    }, 200);
  }

  // Ensure dropdown options are loaded on open.
  if (templateModalEl) {
    templateModalEl.addEventListener('shown.bs.modal', () => {
      loadDepartmentsOptions();
      loadDesignationsOptions();
    });
  }

  function openItemModal(mode, section, item) {
    if (!itemForm || !itemModal) return;
    itemForm.reset();
    itemModalTitle.textContent = mode === 'edit' ? 'Edit Item' : 'Add Item';
    itemTemplateIdEl.value = activeTemplate?.id || '';
    itemIdEl.value = item?.id || '';

    const sel = itemForm.querySelector('[name="section"]');
    const title = itemForm.querySelector('[name="title"]');
    const description = itemForm.querySelector('[name="description"]');
    const weight = itemForm.querySelector('[name="weight"]');
    const sortOrder = itemForm.querySelector('[name="sortOrder"]');
    const min = itemForm.querySelector('[name="ratingScaleMin"]');
    const max = itemForm.querySelector('[name="ratingScaleMax"]');

    sel.value = item?.section || section || 'kpi';
    title.value = item?.title || '';
    description.value = item?.description || '';
    weight.value = item?.weight ?? '';
    sortOrder.value = item?.sortOrder ?? 0;
    min.value = item?.ratingScaleMin ?? 1;
    max.value = item?.ratingScaleMax ?? 5;

    itemModal.show();
  }

  async function saveTemplate(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const fd = new FormData(form);
    const payload = {
      name: (fd.get('name') || '').toString(),
      department: (fd.get('department') || '').toString() || null,
      designation: (fd.get('designation') || '').toString() || null,
      isActive: !!fd.get('isActive'),
    };
    const id = (templateIdEl?.value || '').trim();
    try {
      if (id) {
        await apiRequest('PUT', `${BASE}/indicator-templates/${id}`, payload);
      } else {
        const res = await apiRequest('POST', `${BASE}/indicator-templates`, payload);
        templateIdEl.value = String(res?.data?.id || '');
      }
      notify('success', 'Template tersimpan.');
      await loadTemplates();
      const newId = (templateIdEl?.value || '').trim();
      if (newId) {
        const t = templates.find((x) => String(x.id) === String(newId));
        activeTemplate = t || activeTemplate;
        await loadItems(Number(newId));
      }
    } catch (e2) {
      notify('error', apiErrorMessage(e2));
    }
  }

  async function deleteTemplate(id) {
    if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== 'function') {
      notify('error', 'Confirm modal tidak tersedia. (ArcavUi.confirmDelete)');
      return;
    }
    const ok = await window.ArcavUi.confirmDelete(
      'Template akan terhapus beserta items-nya (jika tidak dipakai review).',
      'Hapus template?'
    );
    if (!ok) return;
    try {
      await apiRequest('DELETE', `${BASE}/indicator-templates/${id}`);
      notify('success', 'Template terhapus.');
      await loadTemplates();
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  async function saveItem(e) {
    e.preventDefault();
    if (!activeTemplate?.id) {
      notify('error', 'Simpan template dulu.');
      return;
    }
    const form = e.currentTarget;
    const fd = new FormData(form);
    const itemId = (itemIdEl?.value || '').trim();
    const payload = {
      section: (fd.get('section') || 'kpi').toString(),
      title: (fd.get('title') || '').toString(),
      description: (fd.get('description') || '').toString() || null,
      weight: fd.get('weight') !== null && fd.get('weight') !== '' ? Number(fd.get('weight')) : null,
      sortOrder: Number(fd.get('sortOrder') || 0),
      ratingScaleMin: Number(fd.get('ratingScaleMin') || 1),
      ratingScaleMax: Number(fd.get('ratingScaleMax') || 5),
    };
    try {
      if (itemId) {
        await apiRequest('PUT', `${BASE}/indicator-items/${itemId}`, payload);
      } else {
        await apiRequest('POST', `${BASE}/indicator-templates/${activeTemplate.id}/items`, payload);
      }
      itemModal?.hide();
      notify('success', 'Item tersimpan.');
      await loadItems(activeTemplate.id);
    } catch (e2) {
      notify('error', apiErrorMessage(e2));
    }
  }

  async function deleteItem(itemId) {
    if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== 'function') {
      notify('error', 'Confirm modal tidak tersedia. (ArcavUi.confirmDelete)');
      return;
    }
    const ok = await window.ArcavUi.confirmDelete(
      'Item akan terhapus dari template.',
      'Hapus item?'
    );
    if (!ok) return;
    try {
      await apiRequest('DELETE', `${BASE}/indicator-items/${itemId}`);
      notify('success', 'Item terhapus.');
      await loadItems(activeTemplate.id);
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  // Bindings
  if (templateReload) templateReload.addEventListener('click', loadTemplates);
  if (templateSearch) templateSearch.addEventListener('input', renderTemplates);
  if (templateForm) templateForm.addEventListener('submit', saveTemplate);
  if (itemForm) itemForm.addEventListener('submit', saveItem);

  // Add item buttons in template modal
  document.querySelectorAll('[data-arcav-perf-add-item]').forEach((btn) => {
    btn.addEventListener('click', () => {
      openItemModal('create', btn.getAttribute('data-section') || 'kpi', null);
    });
  });

  // Row actions
  if (templatesTbody) {
    templatesTbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      const action = btn.getAttribute('data-action');
      const t = templates.find((x) => String(x.id) === String(id));
      if (action === 'view') openTemplateDetail(t);
      if (action === 'edit') openTemplateModal('edit', t, 'info');
      if (action === 'delete') deleteTemplate(id);
    });
  }

  if (itemsTbody) {
    itemsTbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-item-action]');
      if (!btn) return;
      const itemId = btn.getAttribute('data-item-id');
      const action = btn.getAttribute('data-item-action');
      const it = activeItems.find((x) => String(x.id) === String(itemId));
      if (action === 'edit') openItemModal('edit', it?.section, it);
      if (action === 'delete') deleteItem(itemId);
    });
  }

  // -------------------------
  // Performance Cycles + Reviews (Appraisal/Review pages)
  // -------------------------
  const cyclesTbody = document.querySelector('[data-arcav-perf-cycles-tbody]');
  const cycleReload = document.querySelector('[data-arcav-perf-cycle-reload]');
  const cycleForm = document.querySelector('[data-arcav-perf-cycle-form]');
  const cycleModalEl = document.getElementById('arcav_perf_cycle_modal');
  const cycleModal = cycleModalEl ? bootstrap.Modal.getOrCreateInstance(cycleModalEl) : null;
  const cycleModalTitle = document.querySelector('[data-arcav-perf-cycle-modal-title]');
  const cycleIdEl = document.querySelector('[data-arcav-perf-cycle-id]');
  const cycleStatusWrap = document.querySelector('[data-arcav-perf-cycle-status-wrap]');

  const reviewsTbody = document.querySelector('[data-arcav-perf-reviews-tbody]');
  const reviewScope = document.querySelector('[data-arcav-perf-review-scope]');
  const reviewReload = document.querySelector('[data-arcav-perf-review-reload]');

  const reviewDetailWrap = document.querySelector('[data-arcav-perf-review-detail]');
  const reviewRefreshDetailBtn = document.querySelector('[data-arcav-perf-review-refresh-detail]');
  const reviewPrimaryBtn = document.querySelector('[data-arcav-perf-review-primary-action]');
  const reviewSecondaryBtn = document.querySelector('[data-arcav-perf-review-secondary-action]');

  const reviewCreateForm = document.querySelector('[data-arcav-perf-review-create-form]');
  const reviewCreateModalEl = document.getElementById('arcav_perf_review_create_modal');
  const reviewCreateModal = reviewCreateModalEl ? bootstrap.Modal.getOrCreateInstance(reviewCreateModalEl) : null;
  const reviewCreateCycleSelect = document.querySelector('[data-arcav-perf-review-cycle]');
  const reviewCreateTemplateSelect = document.querySelector('[data-arcav-perf-review-template]');

  let cycles = [];
  let selectedReviewId = null;
  let me = null;
  let canManagePerformance = false;

  async function loadMe() {
    try {
      const res = await apiRequest('GET', '/v1/identity/auth/me');
      me = res?.data || null;
      // Gunakan granular permission: performance.manage atau performance.admin
      canManagePerformance = !!(me && me.permissions && (me.permissions['performance.manage'] || me.permissions['performance.admin']));
    } catch (_) {
      me = null;
      canManagePerformance = false;
    }

    if (reviewScope && !canManagePerformance) {
      const optAll = reviewScope.querySelector('option[value="all"]');
      if (optAll) optAll.remove();
      if (reviewScope.value === 'all') reviewScope.value = 'me';
    }

    // Hide admin-only entry points in UI (backend still enforces).
    const cycleModalBtn = document.querySelector('[data-bs-target="#arcav_perf_cycle_modal"]');
    const createReviewBtn = document.querySelector('[data-bs-target="#arcav_perf_review_create_modal"]');
    if (cycleModalBtn) cycleModalBtn.style.display = canManagePerformance ? '' : 'none';
    if (createReviewBtn) createReviewBtn.style.display = canManagePerformance ? '' : 'none';

    // Guard admin-only pages to avoid confusing "Gagal memuat..." states.
    // Performance Indicator & Appraisal are admin-only.
    const isIndicatorPage = !!templatesTbody;
    const isAppraisalPage = !!cyclesTbody;
    if ((isIndicatorPage || isAppraisalPage) && !canManagePerformance) {
      window.location.href = '/performance-review';
    }
  }

  function statusBadge(status) {
    const map = {
      draft: ['badge badge-soft-warning', 'draft'],
      submitted: ['badge badge-soft-primary', 'submitted'],
      manager_reviewed: ['badge badge-soft-info', 'manager reviewed'],
      finalized: ['badge badge-soft-success', 'finalized'],
      active: ['badge badge-success', 'active'],
      closed: ['badge badge-danger', 'closed'],
    };
    const [cls, label] = map[status] || ['badge badge-soft-secondary', status];
    return `<span class="${cls}">${esc(label)}</span>`;
  }

  async function loadCycles() {
    if (!cyclesTbody) return;
    cyclesTbody.innerHTML =
      '<tr><td colspan="4" class="text-center text-muted py-4">Memuat cycles...</td></tr>';
    try {
      const res = await apiRequest('GET', `${BASE}/cycles`);
      cycles = Array.isArray(res?.data) ? res.data : [];
      if (!cycles.length) {
        cyclesTbody.innerHTML =
          '<tr><td colspan="4" class="text-center text-muted py-4">Belum ada cycle.</td></tr>';
        return;
      }
      cyclesTbody.innerHTML = cycles
        .map((c) => {
          return `
            <tr>
              <td>
                <div class="fw-medium">${esc(c.name)}</div>
                <div class="text-muted fs-12">ID: ${c.id}</div>
              </td>
              <td>${esc(c.periodStart)} → ${esc(c.periodEnd)}</td>
              <td>${statusBadge(c.status)}</td>
              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <button type="button" class="btn btn-sm btn-white" data-cycle-action="edit" data-cycle-id="${c.id}">Edit</button>
                  <button type="button" class="btn btn-sm btn-white" data-cycle-action="activate" data-cycle-id="${c.id}">Activate</button>
                  <button type="button" class="btn btn-sm btn-danger" data-cycle-action="close" data-cycle-id="${c.id}">Close</button>
                </div>
              </td>
            </tr>
          `;
        })
        .join('');
      fillReviewCreateCycles();
    } catch (e) {
      const msg = apiErrorMessage(e);
      cyclesTbody.innerHTML =
        `<tr><td colspan="4" class="text-center text-danger py-4">Gagal memuat cycles.<div class="text-muted fs-12 mt-1">${esc(msg)}</div></td></tr>`;
      notify('error', apiErrorMessage(e));
    }
  }

  function fillReviewCreateCycles() {
    if (!reviewCreateCycleSelect) return;
    const options = cycles.map((c) => `<option value="${c.id}">${esc(c.name)} (${esc(c.status)})</option>`);
    reviewCreateCycleSelect.innerHTML = options.join('');
  }

  function fillReviewCreateTemplates() {
    if (!reviewCreateTemplateSelect) return;
    const options = templates.map((t) => `<option value="${t.id}">${esc(t.name)}</option>`);
    reviewCreateTemplateSelect.innerHTML = options.join('');
  }

  function openCycleModal(mode, cycle) {
    if (!cycleForm || !cycleModal) return;
    cycleForm.reset();
    const name = cycleForm.querySelector('[name="name"]');
    const ps = cycleForm.querySelector('[name="periodStart"]');
    const pe = cycleForm.querySelector('[name="periodEnd"]');
    const status = cycleForm.querySelector('[name="status"]');

    if (mode === 'edit' && cycle) {
      cycleModalTitle.textContent = 'Edit Cycle';
      cycleIdEl.value = cycle.id;
      name.value = cycle.name || '';
      ps.value = cycle.periodStart || '';
      pe.value = cycle.periodEnd || '';
      if (cycleStatusWrap) cycleStatusWrap.style.display = '';
      status.value = cycle.status || 'draft';
    } else {
      cycleModalTitle.textContent = 'Add Cycle';
      cycleIdEl.value = '';
      if (cycleStatusWrap) cycleStatusWrap.style.display = 'none';
      status.value = 'draft';
    }
    cycleModal.show();
  }

  async function saveCycle(e) {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const payload = {
      name: (fd.get('name') || '').toString(),
      periodStart: (fd.get('periodStart') || '').toString(),
      periodEnd: (fd.get('periodEnd') || '').toString(),
      status: (fd.get('status') || 'draft').toString(),
    };
    const id = (cycleIdEl?.value || '').trim();
    try {
      if (id) {
        await apiRequest('PUT', `${BASE}/cycles/${id}`, payload);
      } else {
        await apiRequest('POST', `${BASE}/cycles`, payload);
      }
      cycleModal?.hide();
      notify('success', 'Cycle tersimpan.');
      await loadCycles();
    } catch (e2) {
      notify('error', apiErrorMessage(e2));
    }
  }

  async function activateCycle(id) {
    try {
      await apiRequest('POST', `${BASE}/cycles/${id}/activate`);
      notify('success', 'Cycle diaktifkan.');
      await loadCycles();
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  async function closeCycle(id) {
    try {
      await apiRequest('POST', `${BASE}/cycles/${id}/close`);
      notify('success', 'Cycle ditutup.');
      await loadCycles();
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  function reviewRow(r) {
    const mode = reviewsTbody?.closest('table')?.getAttribute('data-arcav-perf-reviews-mode') || 'full';
    const cycleName = r.cycle?.name || '—';
    const empName = r.employee?.name || `#${r.employee?.id || ''}`;
    const mgrName = r.manager?.name || '—';
    const final = r.finalTotalScore != null ? Number(r.finalTotalScore).toFixed(2) : '—';
    return `
      <tr>
        <td>${esc(cycleName)}</td>
        <td class="text-break">${esc(empName)}</td>
        ${mode === 'full' ? `<td>${esc(mgrName)}</td>` : ''}
        <td>${statusBadge(r.status)}</td>
        ${mode === 'full' ? `<td>${esc(final)}</td>` : ''}
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-white" data-review-action="open" data-review-id="${r.id}">Open</button>
        </td>
      </tr>
    `;
  }

  async function loadReviews() {
    if (!reviewsTbody) return;
    const scope = (reviewScope?.value || 'me').toString();
    reviewsTbody.innerHTML =
      '<tr><td colspan="6" class="text-center text-muted py-4">Memuat reviews...</td></tr>';
    try {
      const res = await apiRequest('GET', `${BASE}/reviews?scope=${encodeURIComponent(scope)}&perPage=50`);
      const rows = Array.isArray(res?.data) ? res.data : [];
      if (!rows.length) {
        reviewsTbody.innerHTML =
          '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada review.</td></tr>';
        return;
      }
      reviewsTbody.innerHTML = rows.map(reviewRow).join('');
    } catch (e) {
      const msg = apiErrorMessage(e);
      reviewsTbody.innerHTML =
        `<tr><td colspan="6" class="text-center text-danger py-4">Gagal memuat reviews.<div class="text-muted fs-12 mt-1">${esc(msg)}</div></td></tr>`;
      notify('error', apiErrorMessage(e));
    }
  }

  function renderReviewDetail(data) {
    if (!reviewDetailWrap) return;
    const items = data?.items || [];
    const isOwner = !!data?.permissions?.isOwner;
    const isManager = !!data?.permissions?.isManager;
    const isAdmin = !!data?.permissions?.isAdmin;

    const canSelfEdit = isOwner && data?.status === 'draft';
    const canManagerEdit = isManager && data?.status === 'submitted';
    const canFinalEdit = isAdmin && (data?.status === 'manager_reviewed' || data?.status === 'finalized');

    if (reviewRefreshDetailBtn) reviewRefreshDetailBtn.classList.toggle('disabled', !selectedReviewId);
    if (reviewPrimaryBtn) reviewPrimaryBtn.classList.toggle('disabled', !selectedReviewId);
    if (reviewSecondaryBtn) reviewSecondaryBtn.classList.toggle('disabled', true);

    const header = `
      <div class="mb-3">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
          <div>
            <div class="fw-medium">${esc(data?.employee?.name || '')}</div>
            <div class="text-muted fs-12">
              Cycle: ${esc(data?.cycle?.name || '—')} • Status: ${esc(data?.status || '')}
            </div>
          </div>
          <div class="text-end">
            <div class="fw-medium">Self total: ${data?.totals?.selfTotalScore != null ? Number(data.totals.selfTotalScore).toFixed(2) : '—'}</div>
            <div class="text-muted fs-12">Manager: ${data?.totals?.managerTotalScore != null ? Number(data.totals.managerTotalScore).toFixed(2) : '—'} • Final: ${data?.totals?.finalTotalScore != null ? Number(data.totals.finalTotalScore).toFixed(2) : '—'}</div>
          </div>
        </div>
      </div>
    `;

    const notes = `
      <div class="row g-3 mb-3">
        <div class="col-12">
          <label class="form-label">Self note</label>
          <textarea class="form-control" rows="2" data-self-note ${canSelfEdit ? '' : 'disabled'}>${esc(data?.notes?.selfNote || '')}</textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Manager note</label>
          <textarea class="form-control" rows="2" data-manager-note ${canManagerEdit ? '' : 'disabled'}>${esc(data?.notes?.managerNote || '')}</textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Final note (Admin)</label>
          <textarea class="form-control" rows="2" data-final-note ${canFinalEdit ? '' : 'disabled'}>${esc(data?.notes?.finalNote || '')}</textarea>
        </div>
      </div>
    `;

    const table = `
      <div class="table-responsive">
        <table class="table">
          <thead class="thead-light">
            <tr>
              <th>Section</th>
              <th>Indicator</th>
              <th class="text-nowrap" style="min-width: 220px">Self</th>
              <th class="text-nowrap" style="min-width: 220px">Manager</th>
              <th class="text-nowrap" style="min-width: 220px">Final</th>
            </tr>
          </thead>
          <tbody>
            ${items
              .map((it) => {
                const isBeh = it.section === 'behavioral';
                const placeholder = isBeh ? `${it.ratingScaleMin}-${it.ratingScaleMax}` : '0-100';
                const selfVal = it?.scores?.selfScore ?? '';
                const mgrVal = it?.scores?.managerScore ?? '';
                const finVal = it?.scores?.finalScore ?? '';
                const scaleOptions = isBeh
                  ? Array.from(
                      { length: Math.max(0, Number(it.ratingScaleMax) - Number(it.ratingScaleMin) + 1) },
                      (_, idx) => Number(it.ratingScaleMin) + idx
                    )
                  : [];
                return `
                  <tr>
                    <td><span class="badge badge-soft-primary">${esc(it.section)}</span></td>
                    <td class="text-break">
                      <div class="fw-medium">${esc(it.title)}</div>
                      ${it.description ? `<div class="text-muted fs-12">${esc(it.description)}</div>` : ''}
                      ${it.section === 'kpi' ? `<div class="text-muted fs-12">Weight: ${esc(it.weight ?? 0)}</div>` : ''}
                    </td>
                    <td>
                      ${
                        isBeh
                          ? `<select class="form-select w-100" style="min-width: 160px" data-score-role="self" data-item-id="${it.id}" ${canSelfEdit ? '' : 'disabled'}>
                              <option value="">—</option>
                              ${scaleOptions
                                .map((v) => `<option value="${esc(v)}" ${String(v) === String(selfVal) ? 'selected' : ''}>${esc(v)}</option>`)
                                .join('')}
                            </select>`
                          : `<input type="number" class="form-control w-100" style="min-width: 160px" data-score-role="self" data-item-id="${it.id}" placeholder="${esc(placeholder)}" value="${esc(selfVal)}" ${canSelfEdit ? '' : 'disabled'}>`
                      }
                      <input type="text" class="form-control w-100 mt-2" style="min-width: 160px" data-comment-role="self" data-item-id="${it.id}" placeholder="Comment" value="${esc(it?.scores?.selfComment ?? '')}" ${canSelfEdit ? '' : 'disabled'}>
                    </td>
                    <td>
                      ${
                        isBeh
                          ? `<select class="form-select w-100" style="min-width: 160px" data-score-role="manager" data-item-id="${it.id}" ${canManagerEdit ? '' : 'disabled'}>
                              <option value="">—</option>
                              ${scaleOptions
                                .map((v) => `<option value="${esc(v)}" ${String(v) === String(mgrVal) ? 'selected' : ''}>${esc(v)}</option>`)
                                .join('')}
                            </select>`
                          : `<input type="number" class="form-control w-100" style="min-width: 160px" data-score-role="manager" data-item-id="${it.id}" placeholder="${esc(placeholder)}" value="${esc(mgrVal)}" ${canManagerEdit ? '' : 'disabled'}>`
                      }
                      <input type="text" class="form-control w-100 mt-2" style="min-width: 160px" data-comment-role="manager" data-item-id="${it.id}" placeholder="Comment" value="${esc(it?.scores?.managerComment ?? '')}" ${canManagerEdit ? '' : 'disabled'}>
                    </td>
                    <td>
                      ${
                        isBeh
                          ? `<select class="form-select w-100" style="min-width: 160px" data-score-role="final" data-item-id="${it.id}" ${canFinalEdit ? '' : 'disabled'}>
                              <option value="">—</option>
                              ${scaleOptions
                                .map((v) => `<option value="${esc(v)}" ${String(v) === String(finVal) ? 'selected' : ''}>${esc(v)}</option>`)
                                .join('')}
                            </select>`
                          : `<input type="number" class="form-control w-100" style="min-width: 160px" data-score-role="final" data-item-id="${it.id}" placeholder="${esc(placeholder)}" value="${esc(finVal)}" ${canFinalEdit ? '' : 'disabled'}>`
                      }
                      <input type="text" class="form-control w-100 mt-2" style="min-width: 160px" data-comment-role="final" data-item-id="${it.id}" placeholder="Comment" value="${esc(it?.scores?.finalComment ?? '')}" ${canFinalEdit ? '' : 'disabled'}>
                    </td>
                  </tr>
                `;
              })
              .join('')}
          </tbody>
        </table>
      </div>
    `;

    reviewDetailWrap.innerHTML = header + notes + table;

    // Button labels & enabled states
    if (!reviewPrimaryBtn || !reviewSecondaryBtn) return;
    reviewPrimaryBtn.textContent = 'Save';
    reviewSecondaryBtn.textContent = 'Next';

    if (canSelfEdit) {
      const canSubmit = isOwner && data?.status === 'draft' && data?.cycle?.status === 'active';
      reviewSecondaryBtn.textContent = 'Submit';
      reviewSecondaryBtn.classList.toggle('disabled', !canSubmit);
    } else if (canManagerEdit) {
      reviewSecondaryBtn.textContent = 'Complete Review';
      reviewSecondaryBtn.classList.toggle('disabled', false);
    } else if (canFinalEdit) {
      reviewSecondaryBtn.textContent = data?.status === 'finalized' ? 'Finalized' : 'Finalize';
      reviewSecondaryBtn.classList.toggle('disabled', data?.status === 'finalized');
    } else {
      reviewSecondaryBtn.classList.toggle('disabled', true);
    }
  }

  async function loadReviewDetail(id) {
    if (!reviewDetailWrap) return;
    reviewDetailWrap.innerHTML = '<div class="text-center text-muted py-5">Memuat detail...</div>';
    try {
      const res = await apiRequest('GET', `${BASE}/reviews/${id}`);
      renderReviewDetail(res?.data);
    } catch (e) {
      reviewDetailWrap.innerHTML = '<div class="text-center text-danger py-5">Gagal memuat detail.</div>';
      notify('error', apiErrorMessage(e));
    }
  }

  function collectScores(role) {
    if (!reviewDetailWrap) return { note: '', scores: [] };
    const noteSelector = role === 'self' ? '[data-self-note]' : role === 'manager' ? '[data-manager-note]' : '[data-final-note]';
    const note = reviewDetailWrap.querySelector(noteSelector)?.value || '';
    const scoreEls = Array.from(reviewDetailWrap.querySelectorAll(`[data-score-role="${role}"]`));
    const scores = scoreEls.map((el) => {
      const itemId = Number(el.getAttribute('data-item-id'));
      const scoreRaw = el.value;
      const score = scoreRaw === '' ? null : Number(scoreRaw);
      const comment = reviewDetailWrap.querySelector(`[data-comment-role="${role}"][data-item-id="${itemId}"]`)?.value || '';
      return { itemId, score, comment };
    });
    return { note, scores };
  }

  const autosaveTimers = { self: null, manager: null, final: null };
  let autosaveInFlight = { self: false, manager: false, final: false };

  async function saveDraftFor(role, opts = {}) {
    if (!selectedReviewId || !reviewDetailWrap) return;
    const { note, scores } = collectScores(role);
    try {
      if (role === 'self') {
        await apiRequest('PUT', `${BASE}/reviews/${selectedReviewId}`, { selfNote: note, scores });
      } else if (role === 'manager') {
        await apiRequest('PUT', `${BASE}/reviews/${selectedReviewId}/manager`, { managerNote: note, scores });
      } else {
        await apiRequest('PUT', `${BASE}/reviews/${selectedReviewId}/final`, { finalNote: note, scores });
      }
      if (!opts.silent) notify('success', 'Tersimpan.');
      if (opts.reloadDetail) await loadReviewDetail(selectedReviewId);
      if (opts.reloadList) await loadReviews();
    } catch (e) {
      if (!opts.silent) notify('error', apiErrorMessage(e));
    }
  }

  function queueAutosave(role) {
    if (!selectedReviewId || !reviewDetailWrap) return;
    clearTimeout(autosaveTimers[role]);
    autosaveTimers[role] = setTimeout(async () => {
      if (autosaveInFlight[role]) return;
      autosaveInFlight[role] = true;
      try {
        // Autosave should NOT reload detail to avoid scroll jumps.
        await saveDraftFor(role, { silent: true, reloadDetail: false, reloadList: false });
      } finally {
        autosaveInFlight[role] = false;
      }
    }, 400);
  }

  async function submitSelf() {
    if (!selectedReviewId) return;
    try {
      await apiRequest('POST', `${BASE}/reviews/${selectedReviewId}/submit`);
      notify('success', 'Review submitted.');
      await loadReviewDetail(selectedReviewId);
      await loadReviews();
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  async function managerComplete() {
    if (!selectedReviewId) return;
    try {
      await apiRequest('POST', `${BASE}/reviews/${selectedReviewId}/manager-complete`);
      notify('success', 'Manager review completed.');
      await loadReviewDetail(selectedReviewId);
      await loadReviews();
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  async function finalizeReview() {
    if (!selectedReviewId) return;
    try {
      await apiRequest('POST', `${BASE}/reviews/${selectedReviewId}/finalize`);
      notify('success', 'Review finalized.');
      await loadReviewDetail(selectedReviewId);
      await loadReviews();
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  async function createReview(e) {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const payload = {
      cycleId: Number(fd.get('cycleId')),
      userId: Number(fd.get('userId')),
      templateId: Number(fd.get('templateId')),
    };
    try {
      const res = await apiRequest('POST', `${BASE}/reviews`, payload);
      notify('success', 'Review created.');
      reviewCreateModal?.hide();
      await loadReviews();
      if (res?.data?.id) {
        selectedReviewId = Number(res.data.id);
        await loadReviewDetail(selectedReviewId);
      }
    } catch (e2) {
      notify('error', apiErrorMessage(e2));
    }
  }

  // Bind cycles
  if (cycleReload) cycleReload.addEventListener('click', loadCycles);
  if (cycleForm) cycleForm.addEventListener('submit', saveCycle);
  if (cyclesTbody) {
    cyclesTbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-cycle-action]');
      if (!btn) return;
      const id = btn.getAttribute('data-cycle-id');
      const action = btn.getAttribute('data-cycle-action');
      const c = cycles.find((x) => String(x.id) === String(id));
      if (action === 'edit') openCycleModal('edit', c);
      if (action === 'activate') activateCycle(id);
      if (action === 'close') closeCycle(id);
    });
  }

  // Bind reviews list
  if (reviewReload) reviewReload.addEventListener('click', loadReviews);
  if (reviewScope) reviewScope.addEventListener('change', loadReviews);
  if (reviewsTbody) {
    reviewsTbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-review-action="open"]');
      if (!btn) return;
      const id = Number(btn.getAttribute('data-review-id'));
      selectedReviewId = id;
      loadReviewDetail(id);
    });
  }

  // Detail buttons
  if (reviewRefreshDetailBtn) {
    reviewRefreshDetailBtn.addEventListener('click', () => {
      if (!selectedReviewId) return;
      loadReviewDetail(selectedReviewId);
    });
  }
  if (reviewPrimaryBtn) {
    reviewPrimaryBtn.addEventListener('click', async () => {
      if (!selectedReviewId) return;
      // Determine role by enabled inputs
      if (reviewDetailWrap?.querySelector('[data-score-role="self"]:not([disabled])')) return saveDraftFor('self', { reloadDetail: true, reloadList: true });
      if (reviewDetailWrap?.querySelector('[data-score-role="manager"]:not([disabled])')) return saveDraftFor('manager', { reloadDetail: true, reloadList: true });
      if (reviewDetailWrap?.querySelector('[data-score-role="final"]:not([disabled])')) return saveDraftFor('final', { reloadDetail: true, reloadList: true });
    });
  }
  if (reviewSecondaryBtn) {
    reviewSecondaryBtn.addEventListener('click', async () => {
      if (!selectedReviewId) return;
      if (reviewSecondaryBtn.classList.contains('disabled')) return;
      if (reviewDetailWrap?.querySelector('[data-score-role="self"]:not([disabled])')) {
        await saveDraftFor('self');
        await submitSelf();
        return;
      }
      if (reviewDetailWrap?.querySelector('[data-score-role="manager"]:not([disabled])')) {
        await saveDraftFor('manager');
        await managerComplete();
        return;
      }
      if (reviewDetailWrap?.querySelector('[data-score-role="final"]:not([disabled])')) {
        await saveDraftFor('final');
        await finalizeReview();
      }
    });
  }
  if (reviewDetailWrap) {
    reviewDetailWrap.addEventListener('change', (e) => {
      if (e.target.matches('[data-score-role="self"], [data-comment-role="self"], [data-self-note]')) queueAutosave('self');
      if (e.target.matches('[data-score-role="manager"], [data-comment-role="manager"], [data-manager-note]')) queueAutosave('manager');
      if (e.target.matches('[data-score-role="final"], [data-comment-role="final"], [data-final-note]')) queueAutosave('final');
    });
  }

  if (reviewCreateForm) reviewCreateForm.addEventListener('submit', createReview);
  if (reviewCreateModalEl) {
    reviewCreateModalEl.addEventListener('show.bs.modal', () => {
      fillReviewCreateCycles();
      fillReviewCreateTemplates();
    });
  }

  // init only if elements exist
  loadMe().finally(() => {
    // Indicator templates: admin-only
    if (templatesTbody && canManagePerformance) loadTemplates();
    // Cycles: admin-only
    if (cyclesTbody && canManagePerformance) loadCycles();
    // Reviews list/detail: allowed for all authenticated users (scope filtered by API + UI)
    if (reviewsTbody) loadReviews();
  });
})();

