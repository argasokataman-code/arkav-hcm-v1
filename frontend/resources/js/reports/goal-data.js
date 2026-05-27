/* global bootstrap */

(function () {
  'use strict';

  const BASE = '/v1/hcm/performance';

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
    try {
      json = text ? JSON.parse(text) : {};
    } catch (_) {
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

  function statusBadge(status) {
    const map = {
      active: ['badge badge-success d-inline-flex align-items-center badge-xs', 'Active'],
      inactive: ['badge badge-danger d-inline-flex align-items-center badge-xs', 'Inactive'],
      completed: ['badge badge-soft-success d-inline-flex align-items-center badge-xs', 'Completed'],
    };
    const [cls, label] = map[status] || ['badge badge-soft-secondary', status];
    return `<span class="${cls}"><i class="ti ti-point-filled me-1"></i>${esc(label)}</span>`;
  }

  function progressHtml(pct) {
    const v = Math.max(0, Math.min(100, Number(pct ?? 0)));
    return `
      <div style="min-width:120px">
        <div class="d-flex align-items-center justify-content-between mb-1">
          <span class="fs-12 text-muted">${esc(v)}%</span>
        </div>
        <div class="progress" role="progressbar" aria-valuenow="${esc(v)}" aria-valuemin="0" aria-valuemax="100" style="height:5px;">
          <div class="progress-bar bg-primary" style="width:${esc(v)}%"></div>
        </div>
      </div>
    `;
  }

  function toCsv(rows) {
    const escCsv = (v) => `"${String(v ?? '').replaceAll('"', '""')}"`;
    const header = ['Goal Type', 'Subject', 'Target Achievement', 'Start Date', 'End Date', 'Status', 'Progress'];
    const lines = [header.map(escCsv).join(',')];
    rows.forEach((r) => {
      lines.push([
        r.goalType?.name || '',
        r.subject || '',
        r.targetAchievement || '',
        r.startDate || '',
        r.endDate || '',
        r.status || '',
        r.progressPercent ?? 0,
      ].map(escCsv).join(','));
    });
    return lines.join('\n');
  }

  function download(filename, content, type) {
    const blob = new Blob([content], { type: type || 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  // -------------------------
  // Goal Types page
  // -------------------------
  const goalTypesTbody = document.querySelector('[data-arcav-goal-types-tbody]');
  const goalTypeSearch = document.querySelector('[data-arcav-goal-type-search]');
  const goalTypeReload = document.querySelector('[data-arcav-goal-type-reload]');
  const goalTypeForm = document.querySelector('[data-arcav-goal-type-form]');
  const goalTypeModalEl = document.getElementById('arcav_goal_type_modal');
  const goalTypeModal = goalTypeModalEl ? bootstrap.Modal.getOrCreateInstance(goalTypeModalEl) : null;
  const goalTypeModalTitle = document.querySelector('[data-arcav-goal-type-modal-title]');
  const goalTypeIdEl = document.querySelector('[data-arcav-goal-type-id]');

  let me = null;
  let canManageGoals = false;
  let goalTypes = [];

  function renderGoalTypes() {
    if (!goalTypesTbody) return;
    const q = (goalTypeSearch?.value || '').trim().toLowerCase();
    const rows = goalTypes.filter((t) => {
      if (!q) return true;
      return String(t.name).toLowerCase().includes(q) || String(t.description || '').toLowerCase().includes(q);
    });

    if (!rows.length) {
      goalTypesTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Belum ada goal type.</td></tr>';
      return;
    }

    goalTypesTbody.innerHTML = rows.map((t) => `
      <tr>
        <td>
          <div class="fw-medium">${esc(t.name)}</div>
          <div class="text-muted fs-12">ID: ${esc(t.id)}</div>
        </td>
        <td class="text-break">${esc(t.description || '—')}</td>
        <td>${t.isActive ? '<span class="badge badge-success">active</span>' : '<span class="badge badge-danger">inactive</span>'}</td>
        <td class="text-end">
          ${meAdmin ? `
            <div class="d-inline-flex gap-2">
              <button type="button" class="btn btn-sm btn-white" data-goal-type-action="edit" data-id="${esc(t.id)}">Edit</button>
              <button type="button" class="btn btn-sm btn-danger" data-goal-type-action="delete" data-id="${esc(t.id)}">Delete</button>
            </div>
          ` : '<span class="text-muted fs-12">Admin only</span>'}
        </td>
      </tr>
    `).join('');
  }

  async function loadGoalTypes() {
    const needsGoalTypes = !!goalTypesTbody || !!goalTypeSelect || !!goalTypeFilter;
    if (!needsGoalTypes) return;
    if (goalTypesTbody) {
      goalTypesTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Memuat goal types...</td></tr>';
    }
    try {
      const res = await apiRequest('GET', `${BASE}/goal-types`);
      goalTypes = Array.isArray(res?.data) ? res.data : [];
      if (goalTypesTbody) renderGoalTypes();
      // Always refresh dropdown/filter when data available.
      fillGoalTypeOptions();
    } catch (e) {
      const msg = apiErrorMessage(e);
      if (goalTypesTbody) {
        goalTypesTbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-4">Gagal memuat goal types.<div class="text-muted fs-12 mt-1">${esc(msg)}</div></td></tr>`;
      }
      if (goalTypeSelect) {
        goalTypeSelect.innerHTML = '<option value="">(Gagal memuat goal types)</option>';
      }
      if (goalTypeFilter) {
        goalTypeFilter.innerHTML = '<option value="">Goal Type (Gagal load)</option>';
      }
      notify('error', msg);
    }
  }

  function openGoalTypeModal(mode, row) {
    if (!goalTypeForm || !goalTypeModal) return;
    goalTypeForm.reset();
    const name = goalTypeForm.querySelector('[name="name"]');
    const desc = goalTypeForm.querySelector('[name="description"]');
    const active = goalTypeForm.querySelector('[name="isActive"]');
    if (mode === 'edit' && row) {
      goalTypeModalTitle.textContent = 'Edit Goal Type';
      goalTypeIdEl.value = row.id;
      name.value = row.name || '';
      desc.value = row.description || '';
      active.checked = !!row.isActive;
    } else {
      goalTypeModalTitle.textContent = 'Add Goal Type';
      goalTypeIdEl.value = '';
      active.checked = true;
    }
    goalTypeModal.show();
  }

  async function saveGoalType(e) {
    e.preventDefault();
    if (!meAdmin) {
      notify('error', 'Hanya HCM Admin yang bisa mengubah goal types.');
      return;
    }
    const fd = new FormData(e.currentTarget);
    const payload = {
      name: (fd.get('name') || '').toString(),
      description: (fd.get('description') || '').toString() || null,
      isActive: !!fd.get('isActive'),
    };
    const id = (goalTypeIdEl?.value || '').trim();
    try {
      if (id) await apiRequest('PUT', `${BASE}/goal-types/${id}`, payload);
      else await apiRequest('POST', `${BASE}/goal-types`, payload);
      goalTypeModal?.hide();
      notify('success', 'Goal type tersimpan.');
      await loadGoalTypes();
    } catch (e2) {
      notify('error', apiErrorMessage(e2));
    }
  }

  async function deleteGoalType(id) {
    if (!meAdmin) return;
    if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== 'function') {
      notify('error', 'Confirm modal tidak tersedia.');
      return;
    }
    const ok = await window.ArcavUi.confirmDelete('Hapus goal type ini?', 'Hapus');
    if (!ok) return;
    try {
      await apiRequest('DELETE', `${BASE}/goal-types/${id}`);
      notify('success', 'Goal type terhapus.');
      await loadGoalTypes();
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  // -------------------------
  // Goal Tracking page
  // -------------------------
  const goalsTbody = document.querySelector('[data-arcav-goals-tbody]');
  const goalReload = document.querySelector('[data-arcav-goal-reload]');
  const goalScope = document.querySelector('[data-arcav-goal-scope]');
  const goalTypeFilter = document.querySelector('[data-arcav-goal-type-filter]');
  const goalStatusFilter = document.querySelector('[data-arcav-goal-status-filter]');
  const goalStartDate = document.querySelector('[data-arcav-goal-start-date]');
  const goalEndDate = document.querySelector('[data-arcav-goal-end-date]');
  const goalClearDates = document.querySelector('[data-arcav-goal-clear-dates]');
  const goalQ = document.querySelector('[data-arcav-goal-q]');
  const exportBtn = document.querySelector('[data-arcav-goal-export="csv"]');

  const goalForm = document.querySelector('[data-arcav-goal-form]');
  const goalModalEl = document.getElementById('arcav_goal_modal');
  const goalModal = goalModalEl ? bootstrap.Modal.getOrCreateInstance(goalModalEl) : null;
  const goalModalTitle = document.querySelector('[data-arcav-goal-modal-title]');
  const goalIdEl = document.querySelector('[data-arcav-goal-id]');
  const goalTypeSelect = document.querySelector('[data-arcav-goal-type-select]');
  const goalManagerNote = document.querySelector('[data-arcav-goal-manager-note]');

  let goals = [];
  let currentEditGoal = null;
  let currentEditPerm = { isOwner: false, isManager: false, isAdmin: false };

  function canEditGoal(g) {
    const meId = Number(me?.id || 0);
    const ownerId = Number(g?.employee?.id || 0);
    const mgrId = Number(g?.manager?.id || 0);
    return canManageGoals || (meId && ownerId && meId === ownerId) || (meId && mgrId && meId === mgrId);
  }

  function canDeleteGoal(g) {
    const meId = Number(me?.id || 0);
    const ownerId = Number(g?.employee?.id || 0);
    return canManageGoals || (meId && ownerId && meId === ownerId);
  }

  function fillGoalTypeOptions() {
    const options = ['<option value="">—</option>'].concat(
      goalTypes.filter((t) => t.isActive).map((t) => `<option value="${esc(t.id)}">${esc(t.name)}</option>`)
    );
    if (goalTypeSelect) goalTypeSelect.innerHTML = options.join('');
    if (goalTypeFilter) {
      const opt2 = ['<option value="">Goal Type (All)</option>'].concat(
        goalTypes.map((t) => `<option value="${esc(t.id)}">${esc(t.name)}</option>`)
      );
      goalTypeFilter.innerHTML = opt2.join('');
    }
  }

  function renderGoals() {
    if (!goalsTbody) return;
    if (!goals.length) {
      goalsTbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Belum ada goal.</td></tr>';
      return;
    }
    goalsTbody.innerHTML = goals.map((g) => {
      const canEdit = canEditGoal(g);
      const canDel = canDeleteGoal(g);
      const actions = [
        canEdit ? `<button type="button" class="btn btn-sm btn-white" data-goal-action="edit" data-id="${esc(g.id)}">Edit</button>` : '',
        canDel ? `<button type="button" class="btn btn-sm btn-danger" data-goal-action="delete" data-id="${esc(g.id)}">Delete</button>` : '',
      ].filter(Boolean).join('');
      const actionsHtml = actions ? `<div class="d-inline-flex gap-2">${actions}</div>` : '<span class="text-muted fs-12">—</span>';

      return `
        <tr>
          <td>${esc(g.goalType?.name || '—')}</td>
          <td class="text-break">
            <div class="fw-medium">${esc(g.subject)}</div>
            <div class="text-muted fs-12">${esc(g.employee?.name || '')}</div>
          </td>
          <td class="text-break">${esc(g.targetAchievement || '—')}</td>
          <td>${esc(g.startDate || '—')}</td>
          <td>${esc(g.endDate || '—')}</td>
          <td>${statusBadge(g.status)}</td>
          <td>${progressHtml(g.progressPercent)}</td>
          <td class="text-end">
            ${actionsHtml}
          </td>
        </tr>
      `;
    }).join('');
  }

  function buildGoalQuery() {
    const params = new URLSearchParams();
    params.set('perPage', '50');
    params.set('scope', (goalScope?.value || 'me').toString());
    if (goalStatusFilter?.value) params.set('status', goalStatusFilter.value);
    if (goalTypeFilter?.value) params.set('goalTypeId', goalTypeFilter.value);
    if (goalStartDate?.value) params.set('startDate', goalStartDate.value);
    if (goalEndDate?.value) params.set('endDate', goalEndDate.value);
    if (goalQ?.value) params.set('q', goalQ.value);
    return params.toString();
  }

  async function loadGoals() {
    if (!goalsTbody) return;
    goalsTbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Memuat goals...</td></tr>';
    try {
      const res = await apiRequest('GET', `${BASE}/goals?${buildGoalQuery()}`);
      goals = Array.isArray(res?.data) ? res.data : [];
      renderGoals();
    } catch (e) {
      const msg = apiErrorMessage(e);
      goalsTbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">Gagal memuat goals.<div class="text-muted fs-12 mt-1">${esc(msg)}</div></td></tr>`;
      notify('error', msg);
    }
  }

  function openGoalModal(mode, row) {
    if (!goalForm || !goalModal) return;
    goalForm.reset();
    const status = goalForm.querySelector('[name="status"]');
    const subject = goalForm.querySelector('[name="subject"]');
    const target = goalForm.querySelector('[name="targetAchievement"]');
    const sd = goalForm.querySelector('[name="startDate"]');
    const ed = goalForm.querySelector('[name="endDate"]');
    const prog = goalForm.querySelector('[name="progressPercent"]');
    const desc = goalForm.querySelector('[name="description"]');
    const gt = goalForm.querySelector('[name="goalTypeId"]');

    const lock = (el, locked) => {
      if (!el) return;
      if (locked) {
        el.setAttribute('disabled', 'disabled');
      } else {
        el.removeAttribute('disabled');
      }
    };

    if (mode === 'edit' && row) {
      goalModalTitle.textContent = 'Edit Goal';
      goalIdEl.value = row.id;
      status.value = row.status || 'active';
      subject.value = row.subject || '';
      target.value = row.targetAchievement || '';
      sd.value = row.startDate || '';
      ed.value = row.endDate || '';
      prog.value = row.progressPercent ?? 0;
      desc.value = row.description || '';
      gt.value = row.goalType?.id || '';

      currentEditGoal = row;
      const meId = Number(me?.id || 0);
      const ownerId = Number(row?.employee?.id || 0);
      const mgrId = Number(row?.manager?.id || 0);
      currentEditPerm = {
        isAdmin: !!meAdmin,
        isOwner: !!(meId && ownerId && meId === ownerId),
        isManager: !!(meId && mgrId && meId === mgrId),
      };

      const managerOnly = currentEditPerm.isManager && !currentEditPerm.isOwner && !currentEditPerm.isAdmin;
      // Manager-only: allow update status + progress only.
      if (goalManagerNote) goalManagerNote.style.display = managerOnly ? '' : 'none';
      lock(gt, managerOnly);
      lock(subject, managerOnly);
      lock(target, managerOnly);
      lock(sd, managerOnly);
      lock(ed, managerOnly);
      lock(desc, managerOnly);
      lock(status, false);
      lock(prog, false);
    } else {
      goalModalTitle.textContent = 'Add Goal';
      goalIdEl.value = '';
      status.value = 'active';
      currentEditGoal = null;
      currentEditPerm = { isOwner: false, isManager: false, isAdmin: false };

      // Ensure fields unlocked for create.
      if (goalManagerNote) goalManagerNote.style.display = 'none';
      lock(gt, false);
      lock(subject, false);
      lock(target, false);
      lock(sd, false);
      lock(ed, false);
      lock(desc, false);
      lock(status, false);
      lock(prog, false);
    }
    goalModal.show();
  }

  async function saveGoal(e) {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const id = (goalIdEl?.value || '').trim();
    const managerOnly = !!id && currentEditPerm.isManager && !currentEditPerm.isOwner && !currentEditPerm.isAdmin;
    const payload = managerOnly
      ? {
          status: (fd.get('status') || 'active').toString(),
          progressPercent: Number(fd.get('progressPercent') || 0),
        }
      : {
          goalTypeId: fd.get('goalTypeId') ? Number(fd.get('goalTypeId')) : null,
          subject: (fd.get('subject') || '').toString(),
          targetAchievement: (fd.get('targetAchievement') || '').toString() || null,
          startDate: (fd.get('startDate') || '').toString() || null,
          endDate: (fd.get('endDate') || '').toString() || null,
          description: (fd.get('description') || '').toString() || null,
          status: (fd.get('status') || 'active').toString(),
          progressPercent: Number(fd.get('progressPercent') || 0),
        };
    try {
      if (id) await apiRequest('PUT', `${BASE}/goals/${id}`, payload);
      else await apiRequest('POST', `${BASE}/goals`, payload);
      goalModal?.hide();
      notify('success', 'Goal tersimpan.');
      await loadGoals();
    } catch (e2) {
      notify('error', apiErrorMessage(e2));
    }
  }

  async function deleteGoal(id) {
    if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== 'function') {
      notify('error', 'Confirm modal tidak tersedia.');
      return;
    }
    const ok = await window.ArcavUi.confirmDelete('Hapus goal ini?', 'Hapus');
    if (!ok) return;
    try {
      await apiRequest('DELETE', `${BASE}/goals/${id}`);
      notify('success', 'Goal terhapus.');
      await loadGoals();
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  function bindGoalPage() {
    if (!goalsTbody) return;
    if (goalReload) goalReload.addEventListener('click', loadGoals);
    if (goalScope) goalScope.addEventListener('change', loadGoals);
    if (goalTypeFilter) goalTypeFilter.addEventListener('change', loadGoals);
    if (goalStatusFilter) goalStatusFilter.addEventListener('change', loadGoals);
    if (goalStartDate) goalStartDate.addEventListener('change', loadGoals);
    if (goalEndDate) goalEndDate.addEventListener('change', loadGoals);
    if (goalClearDates) goalClearDates.addEventListener('click', () => {
      if (goalStartDate) goalStartDate.value = '';
      if (goalEndDate) goalEndDate.value = '';
      loadGoals();
    });
    if (goalQ) goalQ.addEventListener('input', () => {
      // basic debounce-ish
      clearTimeout(goalQ.__t);
      goalQ.__t = setTimeout(loadGoals, 300);
    });
    if (exportBtn) exportBtn.addEventListener('click', () => {
      download('goals.csv', toCsv(goals), 'text/csv;charset=utf-8');
    });
    if (goalForm) goalForm.addEventListener('submit', saveGoal);

    goalsTbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-goal-action]');
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      const action = btn.getAttribute('data-goal-action');
      const row = goals.find((x) => String(x.id) === String(id));
      if (action === 'edit') openGoalModal('edit', row);
      if (action === 'delete') deleteGoal(id);
    });

    loadGoals();
  }

  function bindGoalTypesPage() {
    if (!goalTypesTbody) return;
    if (goalTypeReload) goalTypeReload.addEventListener('click', loadGoalTypes);
    if (goalTypeSearch) goalTypeSearch.addEventListener('input', renderGoalTypes);
    if (goalTypeForm) goalTypeForm.addEventListener('submit', saveGoalType);
    goalTypesTbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-goal-type-action]');
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      const action = btn.getAttribute('data-goal-type-action');
      const row = goalTypes.find((x) => String(x.id) === String(id));
      if (action === 'edit') openGoalTypeModal('edit', row);
      if (action === 'delete') deleteGoalType(id);
    });
  }

  // Init
  loadMe().then((m) => {
    me = m;
    canManageGoals = !!m?.permissions && (m.permissions['goal.manage'] || m.permissions['goal.admin']);

    // Scope filter: remove all for non-admin
    const scopeSel = document.querySelector('[data-arcav-goal-scope]');
    if (scopeSel && !canManageGoals) {
      const optAll = scopeSel.querySelector('option[value="all"]');
      if (optAll) optAll.remove();
      if (scopeSel.value === 'all') scopeSel.value = 'me';
    }

    // Goal Type modal button admin-only
    const goalTypeBtn = document.querySelector('[data-bs-target="#arcav_goal_type_modal"]');
    if (goalTypeBtn) goalTypeBtn.style.display = canManageGoals ? '' : 'none';

    bindGoalTypesPage();

    return loadGoalTypes().then(() => {
      fillGoalTypeOptions();
      bindGoalPage();
    });
  });
})();

