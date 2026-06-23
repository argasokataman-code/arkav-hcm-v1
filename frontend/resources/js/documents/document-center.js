/* global bootstrap */

(function () {
  'use strict';

  const BASE = '/v1/hcm/document-center';

  // ─── Utilities ────────────────────────────────────────────────────────────

  function esc(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function notify(type, message) {
    try {
      if (window.ArcavUi && typeof window.ArcavUi.toast === 'function') {
        window.ArcavUi.toast(type, message);
        return;
      }
    } catch (_) {}
    if (type === 'error') console.error(message);
    else console.log(message);
  }

  function apiErrorMessage(err) {
    const e = err?.response?.data ?? err;
    return e?.error?.message || e?.message || 'Terjadi kesalahan. Silakan coba lagi.';
  }

  async function apiRequest(method, url, body) {
    const token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    if (token) { headers['Authorization'] = 'Bearer ' + token; }
    if (window.axios) {
      try {
        const res = await window.axios.request({
          method,
          url,
          data: body,
          headers,
          withCredentials: true,
        });
        return res.data;
      } catch (err) {
        const status = err?.response?.status;
        const data = err?.response?.data;
        if (status === 401 || data?.error?.code === 'AUTH_UNAUTHORIZED') {
          window.location.replace('/login');
          return new Promise(() => {});
        }
        throw err;
      }
    }
    const res = await fetch(url, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined,
      credentials: 'same-origin',
    });
    const text = await res.text();
    let json;
    try { json = text ? JSON.parse(text) : {}; } catch (_) {
      json = { success: false, error: { message: text || 'Invalid response' } };
    }
    if (!res.ok) {
      if (res.status === 401 || json?.error?.code === 'AUTH_UNAUTHORIZED') {
        window.location.replace('/login');
        return new Promise(() => {});
      }
      throw json;
    }
    return json;
  }

  async function apiFormRequest(method, url, formData) {
    const token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
    const authHeader = token ? { 'Authorization': 'Bearer ' + token } : {};
    if (window.axios) {
      try {
        const res = await window.axios.request({
          method,
          url,
          data: formData,
          headers: authHeader,
          withCredentials: true,
        });
        return res.data;
      } catch (err) {
        const status = err?.response?.status;
        const data = err?.response?.data;
        if (status === 401 || data?.error?.code === 'AUTH_UNAUTHORIZED') {
          window.location.replace('/login');
          return new Promise(() => {});
        }
        throw err;
      }
    }
    const res = await fetch(url, {
      method,
      body: formData,
      headers: authHeader,
      credentials: 'same-origin',
    });
    const text = await res.text();
    let json;
    try { json = text ? JSON.parse(text) : {}; } catch (_) {
      json = { success: false, error: { message: text || 'Invalid response' } };
    }
    if (!res.ok) {
      if (res.status === 401 || json?.error?.code === 'AUTH_UNAUTHORIZED') {
        window.location.replace('/login');
        return new Promise(() => {});
      }
      throw json;
    }
    return json;
  }

  async function loadMe() {
    try {
      const res = await apiRequest('GET', '/v1/identity/auth/me');
      return res?.data || null;
    } catch (_) {
      return null;
    }
  }

  function formatBytes(bytes) {
    if (!bytes) return '-';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
  }

  function formatDate(str) {
    if (!str) return '-';
    try {
      return new Date(str).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch (_) { return str; }
  }

  function visibilityBadge(v) {
    if (v === 'employee_visible') return '<span class="badge bg-success-subtle text-success">Employee Visible</span>';
    return '<span class="badge bg-secondary-subtle text-secondary">HR Only</span>';
  }

  // ─── Page Elements ────────────────────────────────────────────────────────

  const tbody = document.getElementById('docCenterTbody');
  const categoryFilter = document.getElementById('docCenterCategoryFilter');
  const visibilityFilter = document.getElementById('docCenterVisibilityFilter');
  const searchInput = document.getElementById('docCenterSearch');
  const reloadBtn = document.getElementById('docCenterReload');
  const adminActionsEl = document.getElementById('docCenterAdminActions');
  const categoryBtnEl = document.getElementById('docCenterCategoryBtn');
  const paginationEl = document.getElementById('docCenterPagination');
  const paginationInfo = document.getElementById('docCenterPaginationInfo');
  const prevPageBtn = document.getElementById('docCenterPrevPage');
  const nextPageBtn = document.getElementById('docCenterNextPage');

  // Upload modal
  const uploadModalEl = document.getElementById('arcav_doc_upload_modal');
  const uploadModal = uploadModalEl ? bootstrap.Modal.getOrCreateInstance(uploadModalEl) : null;
  const uploadForm = document.getElementById('arcavDocUploadForm');
  const uploadEmployeeSelect = document.getElementById('arcavDocEmployee');
  const uploadCategorySelect = document.getElementById('arcavDocCategory');

  // Edit modal
  const editModalEl = document.getElementById('arcav_doc_edit_modal');
  const editModal = editModalEl ? bootstrap.Modal.getOrCreateInstance(editModalEl) : null;
  const editForm = document.getElementById('arcavDocEditForm');
  const editCategorySelect = document.getElementById('arcavDocEditCategory');

  // Category modal
  const catModalEl = document.getElementById('arcav_doc_category_modal');
  const catForm = document.getElementById('arcavDocCategoryForm');
  const catTbody = document.getElementById('arcavDocCatTbody');
  const catNameInput = document.getElementById('arcavDocCatName');
  const catIdInput = document.getElementById('arcavDocCatId');
  const catSubmitBtn = document.getElementById('arcavDocCatSubmit');
  const catCancelBtn = document.getElementById('arcavDocCatCancelEdit');

  // Delete modal
  const deleteModalEl = document.getElementById('arcav_doc_delete_modal');
  const deleteModal = deleteModalEl ? bootstrap.Modal.getOrCreateInstance(deleteModalEl) : null;
  const deleteConfirmBtn = document.getElementById('arcavDocDeleteConfirm');
  const deleteTitleEl = document.getElementById('arcavDocDeleteTitle');
  const deleteIdInput = document.getElementById('arcavDocDeleteId');

  // ─── State ────────────────────────────────────────────────────────────────

  let isAdmin = false;
  let currentPage = 1;
  let lastPage = 1;
  let categories = [];
  let employees = [];
  let searchTimer = null;

  // ─── Init ─────────────────────────────────────────────────────────────────

  if (!tbody) return; // Not on document-center page

  async function init() {
    const me = await loadMe();
    const role = me?.companyRole || '';
    const perms = Array.isArray(me?.permissions)
      ? me.permissions
      : (me?.permissions && typeof me.permissions === 'object'
          ? Object.keys(me.permissions).filter((code) => me.permissions[code] === true)
          : (Array.isArray(me?.permissionCodes) ? me.permissionCodes : []));
    const isEmployee = role === 'employee' || role === 'member';

    isAdmin = !isEmployee && (
      me?.isHcmAdmin ||
      perms.includes('document_center.manage') ||
      perms.includes('document_center.view')
    );

    if (isAdmin) {
      if (adminActionsEl) adminActionsEl.style.removeProperty('display');
      if (categoryBtnEl) categoryBtnEl.style.removeProperty('display');
      await loadEmployees();
    }

    // Filters only visible to admin
    if (!isAdmin && visibilityFilter) {
      visibilityFilter.parentElement?.classList.add('d-none');
    }

    await loadCategories();
    await loadDocuments();
  }

  // ─── Employees ────────────────────────────────────────────────────────────

  async function loadEmployees() {
    try {
      const res = await apiRequest('GET', '/v1/hcm/employees?perPage=200');
      employees = (res?.data || []).map(e => ({
        id: e.employeeProfileId || e.id,
        name: e.fullName || (e.firstName + ' ' + e.lastName).trim(),
      }));
      populateEmployeeSelect(uploadEmployeeSelect, employees, '');
    } catch (_) {}
  }

  function populateEmployeeSelect(sel, list, currentId) {
    if (!sel) return;
    const prev = sel.value;
    const opts = list.map(e =>
      `<option value="${esc(String(e.id))}" ${String(e.id) === String(currentId || prev) ? 'selected' : ''}>${esc(e.name)}</option>`
    ).join('');
    sel.innerHTML = `<option value="">Pilih employee...</option>${opts}`;
  }

  // ─── Categories ───────────────────────────────────────────────────────────

  async function loadCategories() {
    try {
      const res = await apiRequest('GET', `${BASE}/categories`);
      categories = res?.data || [];
      populateCategorySelects();
      renderCategoryTable();
    } catch (_) {
      categories = [];
    }
  }

  function populateCategorySelects() {
    const opts = categories.map(c =>
      `<option value="${esc(String(c.id))}">${esc(c.name)}</option>`
    ).join('');

    if (categoryFilter) {
      categoryFilter.innerHTML = `<option value="">Category (All)</option>${opts}`;
    }
    if (uploadCategorySelect) {
      uploadCategorySelect.innerHTML = `<option value="">Tanpa category</option>${opts}`;
    }
    if (editCategorySelect) {
      editCategorySelect.innerHTML = `<option value="">Tanpa category</option>${opts}`;
    }
  }

  function renderCategoryTable() {
    if (!catTbody) return;
    if (!categories.length) {
      catTbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Belum ada category.</td></tr>';
      return;
    }
    catTbody.innerHTML = categories.map(c => `
      <tr>
        <td>${esc(c.name)}</td>
        <td>${c.isActive
          ? '<span class="badge bg-success-subtle text-success">Active</span>'
          : '<span class="badge bg-secondary-subtle text-secondary">Inactive</span>'}</td>
        <td>
          <button class="btn btn-sm btn-icon btn-light me-1" title="Edit" data-cat-edit="${esc(String(c.id))}" data-cat-name="${esc(c.name)}">
            <i class="ti ti-edit"></i>
          </button>
          <button class="btn btn-sm btn-icon btn-danger-subtle" title="Hapus" data-cat-delete="${esc(String(c.id))}" data-cat-name="${esc(c.name)}">
            <i class="ti ti-trash"></i>
          </button>
        </td>
      </tr>
    `).join('');
  }

  // Category form handlers
  if (catTbody) {
    catTbody.addEventListener('click', async (e) => {
      const editBtn = e.target.closest('[data-cat-edit]');
      const deleteBtn = e.target.closest('[data-cat-delete]');

      if (editBtn) {
        catIdInput.value = editBtn.dataset.catEdit;
        catNameInput.value = editBtn.dataset.catName;
        catSubmitBtn.innerHTML = '<i class="ti ti-device-floppy"></i> Save';
        catCancelBtn.style.display = '';
        catNameInput.focus();
        return;
      }

      if (deleteBtn) {
        if (!confirm(`Hapus category "${deleteBtn.dataset.catName}"? Dokumen yang terkait akan kehilangan kategori.`)) return;
        try {
          await apiRequest('DELETE', `${BASE}/categories/${deleteBtn.dataset.catDelete}`);
          notify('success', 'Category dihapus.');
          await loadCategories();
        } catch (err) {
          notify('error', apiErrorMessage(err));
        }
      }
    });
  }

  if (catCancelBtn) {
    catCancelBtn.addEventListener('click', () => {
      catIdInput.value = '';
      catNameInput.value = '';
      catSubmitBtn.innerHTML = '<i class="ti ti-plus"></i> Add';
      catCancelBtn.style.display = 'none';
    });
  }

  if (catForm) {
    catForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!ArcavValidation.validateForm(catForm)) { return; }
      const name = catNameInput.value.trim();
      if (!name) return;
      const id = catIdInput.value;
      try {
        if (id) {
          await apiRequest('PUT', `${BASE}/categories/${id}`, { name });
          notify('success', 'Category diperbarui.');
        } else {
          await apiRequest('POST', `${BASE}/categories`, { name, isActive: true });
          notify('success', 'Category ditambahkan.');
        }
        catIdInput.value = '';
        catNameInput.value = '';
        catSubmitBtn.innerHTML = '<i class="ti ti-plus"></i> Add';
        if (catCancelBtn) catCancelBtn.style.display = 'none';
        await loadCategories();
      } catch (err) {
        notify('error', apiErrorMessage(err));
      }
    });
  }

  // ─── Documents ────────────────────────────────────────────────────────────

  async function loadDocuments(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({ page });
    const catVal = categoryFilter?.value;
    const visVal = visibilityFilter?.value;
    const qVal = searchInput?.value?.trim();
    if (catVal) params.set('categoryId', catVal);
    if (visVal) params.set('visibility', visVal);
    if (qVal) params.set('q', qVal);

    if (tbody) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="ti ti-loader-2 me-2"></i>Memuat...</td></tr>';
    }

    try {
      const res = await apiRequest('GET', `${BASE}/documents?${params}`);
      const docs = res?.data || [];
      const meta = res?.meta || {};
      lastPage = meta.lastPage || 1;
      renderDocTable(docs, meta);
      updatePagination(meta);
    } catch (err) {
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${esc(apiErrorMessage(err))}</td></tr>`;
      }
    }
  }

  function renderDocTable(docs, meta) {
    if (!tbody) return;
    if (!docs.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada dokumen ditemukan.</td></tr>';
      return;
    }

    tbody.innerHTML = docs.map(doc => {
      const actionBtns = isAdmin ? `
        <button class="btn btn-sm btn-icon btn-light me-1" title="Edit" data-doc-edit="${doc.id}"
          data-doc-json="${esc(JSON.stringify(doc))}">
          <i class="ti ti-edit"></i>
        </button>
        <button class="btn btn-sm btn-icon btn-danger-subtle" title="Hapus" data-doc-delete="${doc.id}"
          data-doc-title="${esc(doc.title)}">
          <i class="ti ti-trash"></i>
        </button>
      ` : '';

      return `
        <tr>
          <td>
            <div class="fw-medium">${esc(doc.title)}</div>
            ${doc.description ? `<div class="text-muted fs-12">${esc(doc.description)}</div>` : ''}
          </td>
          <td>${esc(doc.employee?.fullName || '-')}</td>
          <td>${esc(doc.category?.name || '-')}</td>
          <td>
            <a href="${esc(doc.downloadUrl)}" target="_blank" class="d-flex align-items-center gap-1 text-nowrap"
              title="${esc(doc.originalName)}">
              <i class="ti ti-file-download text-primary"></i>
              <span class="fs-12">${esc(doc.originalName || '-')}</span>
            </a>
            <div class="text-muted fs-11">${formatBytes(doc.sizeBytes)}</div>
          </td>
          <td>${visibilityBadge(doc.visibility)}</td>
          <td>${esc(formatDate(doc.expiresAt))}</td>
          <td>
            <div class="fs-12">${esc(doc.uploadedBy?.name || '-')}</div>
            <div class="text-muted fs-11">${formatDate(doc.createdAt)}</div>
          </td>
          <td>
            <div class="d-flex gap-1">
              ${actionBtns}
            </div>
          </td>
        </tr>
      `;
    }).join('');
  }

  function updatePagination(meta) {
    if (!paginationEl) return;
    if (!meta.total) {
      paginationEl.style.display = 'none';
      return;
    }
    paginationEl.style.removeProperty('display');
    const from = (meta.currentPage - 1) * meta.perPage + 1;
    const to = Math.min(meta.currentPage * meta.perPage, meta.total);
    if (paginationInfo) paginationInfo.textContent = `Menampilkan ${from}–${to} dari ${meta.total}`;
    if (prevPageBtn) prevPageBtn.disabled = meta.currentPage <= 1;
    if (nextPageBtn) nextPageBtn.disabled = meta.currentPage >= (meta.lastPage || 1);
  }

  // ─── Document table actions ───────────────────────────────────────────────

  if (tbody) {
    tbody.addEventListener('click', async (e) => {
      const editBtn = e.target.closest('[data-doc-edit]');
      const deleteBtn = e.target.closest('[data-doc-delete]');

      if (editBtn) {
        const doc = JSON.parse(editBtn.dataset.docJson || '{}');
        document.getElementById('arcavDocEditId').value = doc.id;
        document.getElementById('arcavDocEditTitle').value = doc.title || '';
        document.getElementById('arcavDocEditDescription').value = doc.description || '';
        document.getElementById('arcavDocEditExpiresAt').value = doc.expiresAt || '';
        if (editCategorySelect) editCategorySelect.value = doc.category?.id || '';
        if (document.getElementById('arcavDocEditVisibility')) {
          document.getElementById('arcavDocEditVisibility').value = doc.visibility || 'hr_only';
        }
        if (editModal) editModal.show();
        return;
      }

      if (deleteBtn) {
        if (deleteTitleEl) deleteTitleEl.textContent = deleteBtn.dataset.docTitle;
        if (deleteIdInput) deleteIdInput.value = deleteBtn.dataset.docDelete;
        if (deleteModal) deleteModal.show();
      }
    });
  }

  // Edit form submit
  if (editForm) {
    editForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!ArcavValidation.validateForm(editForm)) { return; }
      const id = document.getElementById('arcavDocEditId')?.value;
      if (!id) return;

      const body = {
        title: document.getElementById('arcavDocEditTitle')?.value?.trim(),
        description: document.getElementById('arcavDocEditDescription')?.value?.trim() || null,
        categoryId: editCategorySelect?.value ? Number(editCategorySelect.value) : null,
        visibility: document.getElementById('arcavDocEditVisibility')?.value || 'hr_only',
        expiresAt: document.getElementById('arcavDocEditExpiresAt')?.value || null,
      };

      try {
        await apiRequest('PUT', `${BASE}/documents/${id}`, body);
        notify('success', 'Dokumen diperbarui.');
        if (editModal) editModal.hide();
        await loadDocuments(currentPage);
      } catch (err) {
        notify('error', apiErrorMessage(err));
      }
    });
  }

  // Delete confirm
  if (deleteConfirmBtn) {
    deleteConfirmBtn.addEventListener('click', async () => {
      const id = deleteIdInput?.value;
      if (!id) return;
      try {
        await apiRequest('DELETE', `${BASE}/documents/${id}`);
        notify('success', 'Dokumen dihapus.');
        if (deleteModal) deleteModal.hide();
        await loadDocuments(currentPage);
      } catch (err) {
        notify('error', apiErrorMessage(err));
        if (deleteModal) deleteModal.hide();
      }
    });
  }

  // Upload form submit
  if (uploadForm) {
    uploadForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!ArcavValidation.validateForm(uploadForm)) { return; }
      const formData = new FormData(uploadForm);
      const submitBtn = document.getElementById('arcavDocUploadSubmit');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Uploading...';
      }
      try {
        await apiFormRequest('POST', `${BASE}/documents`, formData);
        notify('success', 'Dokumen berhasil diupload.');
        uploadForm.reset();
        if (uploadModal) uploadModal.hide();
        await loadDocuments(1);
      } catch (err) {
        notify('error', apiErrorMessage(err));
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<span class="me-1"><i class="ti ti-upload"></i></span>Upload';
        }
      }
    });
  }

  // ─── Filters & Pagination ─────────────────────────────────────────────────

  if (categoryFilter) {
    categoryFilter.addEventListener('change', () => loadDocuments(1));
  }
  if (visibilityFilter) {
    visibilityFilter.addEventListener('change', () => loadDocuments(1));
  }
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => loadDocuments(1), 400);
    });
  }
  if (reloadBtn) {
    reloadBtn.addEventListener('click', () => loadDocuments(currentPage));
  }
  if (prevPageBtn) {
    prevPageBtn.addEventListener('click', () => {
      if (currentPage > 1) loadDocuments(currentPage - 1);
    });
  }
  if (nextPageBtn) {
    nextPageBtn.addEventListener('click', () => {
      if (currentPage < lastPage) loadDocuments(currentPage + 1);
    });
  }

  // ─── Category modal re-load on open ──────────────────────────────────────

  if (catModalEl) {
    catModalEl.addEventListener('show.bs.modal', () => {
      loadCategories();
    });
  }

  // ─── Auto-focus first field on modal open ─────────────────────────────────

  function focusFirstField(modalId) {
    var firstInput = document.querySelector("#" + modalId + " input:not([type=hidden]):not([type=password]), #" + modalId + " select");
    if (firstInput) setTimeout(function () { firstInput.focus(); }, 100);
  }

  [uploadModalEl, editModalEl, catModalEl].forEach(function (el) {
    if (el) {
      el.addEventListener("shown.bs.modal", function () {
        focusFirstField(el.id);
      });
    }
  });

  // ─── Boot ─────────────────────────────────────────────────────────────────

  init();

})();
