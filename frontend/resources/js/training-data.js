/* global bootstrap */

(function () {
  'use strict';

  const BASE = '/v1/hcm/training';

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

  function flash(el, type, message) {
    if (!el) return;
    const cls = type === 'error' ? 'alert-danger' : 'alert-success';
    el.innerHTML = `<div class="alert ${cls} mb-3">${esc(message)}</div>`;
    el.style.display = '';
  }

  function apiErrorMessage(err) {
    const e = err?.response?.data ?? err;
    return e?.error?.message || e?.message || 'Terjadi kesalahan. Silakan coba lagi.';
  }

  async function apiRequest(method, url, body) {
    const headers = { 'Content-Type': 'application/json' };
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

  // -------------------------
  // Training types page
  // -------------------------
  const trainingTypesTbody = document.querySelector('[data-arcav-training-types-tbody]');
  const trainingTypeSearch = document.querySelector('[data-arcav-training-type-search]');
  const trainingTypeReload = document.querySelector('[data-arcav-training-type-reload]');
  const trainingTypeForm = document.querySelector('[data-arcav-training-type-form]');
  const trainingTypeModalEl = document.getElementById('arcav_training_type_modal');
  const trainingTypeModal = trainingTypeModalEl ? bootstrap.Modal.getOrCreateInstance(trainingTypeModalEl) : null;
  const trainingTypeModalTitle = document.querySelector('[data-arcav-training-type-modal-title]');
  const trainingTypeIdEl = document.querySelector('[data-arcav-training-type-id]');

  // -------------------------
  // Training page
  // -------------------------
  const trainingsTbody = document.querySelector('[data-arcav-trainings-tbody]');
  const trainingReload = document.querySelector('[data-arcav-training-reload]');
  const trainingTypeFilter = document.querySelector('[data-arcav-training-type-filter]');
  const trainingStatusFilter = document.querySelector('[data-arcav-training-status-filter]');
  const trainingQ = document.querySelector('[data-arcav-training-q]');

  const trainingForm = document.querySelector('[data-arcav-training-form]');
  const trainingModalEl = document.getElementById('arcav_training_modal');
  const trainingModal = trainingModalEl ? bootstrap.Modal.getOrCreateInstance(trainingModalEl) : null;
  const trainingModalTitle = document.querySelector('[data-arcav-training-modal-title]');
  const trainingIdEl = document.querySelector('[data-arcav-training-id]');
  const trainingTypeSelect = document.querySelector('[data-arcav-training-type-select]');
  const trainingTrainerSelect = document.querySelector('[data-arcav-training-trainer-select]');
  const trainingTrainerOther = document.querySelector('[data-arcav-training-trainer-other]');
  const trainingParticipantsSummary = document.querySelector('[data-arcav-training-participants-summary]');
  const trainingFlash = document.querySelector('[data-arcav-training-flash]');
  const trainingSaveBtn = trainingForm ? trainingForm.querySelector('button[type="submit"]') : null;

  const trainingDetailModalEl = document.getElementById('arcav_training_detail_modal');
  const trainingDetailModal = trainingDetailModalEl ? bootstrap.Modal.getOrCreateInstance(trainingDetailModalEl) : null;
  const trainingDetailType = document.querySelector('[data-arcav-training-detail-type]');
  const trainingDetailTrainer = document.querySelector('[data-arcav-training-detail-trainer]');
  const trainingDetailStatus = document.querySelector('[data-arcav-training-detail-status]');
  const trainingDetailStart = document.querySelector('[data-arcav-training-detail-start]');
  const trainingDetailEnd = document.querySelector('[data-arcav-training-detail-end]');
  const trainingDetailCost = document.querySelector('[data-arcav-training-detail-cost]');
  const trainingDetailParticipants = document.querySelector('[data-arcav-training-detail-participants]');
  const trainingDetailDesc = document.querySelector('[data-arcav-training-detail-desc]');

  const participantsPickerEl = document.getElementById('arcav_training_participants_picker');
  const participantsPicker = participantsPickerEl ? bootstrap.Modal.getOrCreateInstance(participantsPickerEl) : null;
  const participantsFlash = document.querySelector('[data-arcav-training-participants-flash]');
  const participantsTbody = document.querySelector('[data-arcav-training-participants-tbody]');
  const participantsSearch = document.querySelector('[data-arcav-training-participants-search]');
  const participantsPrev = document.querySelector('[data-arcav-training-participants-prev]');
  const participantsNext = document.querySelector('[data-arcav-training-participants-next]');
  const participantsPage = document.querySelector('[data-arcav-training-participants-page]');
  const participantsSelectAll = document.querySelector('[data-arcav-training-participants-select-all]');
  const participantsApply = document.querySelector('[data-arcav-training-participants-apply]');
  const participantsSelectedCount = document.querySelector('[data-arcav-training-participants-selected-count]');
  const openParticipantsPickerBtn = document.querySelector('[data-arcav-open-training-participants-picker]');

  // -------------------------
  // Trainers page
  // -------------------------
  const trainersTbody = document.querySelector('[data-arcav-trainers-tbody]');
  const trainerReload = document.querySelector('[data-arcav-trainer-reload]');
  const trainerStatus = document.querySelector('[data-arcav-trainer-status]');
  const trainerQ = document.querySelector('[data-arcav-trainer-q]');
  const trainerForm = document.querySelector('[data-arcav-trainer-form]');
  const trainerModalEl = document.getElementById('arcav_trainer_modal');
  const trainerModal = trainerModalEl ? bootstrap.Modal.getOrCreateInstance(trainerModalEl) : null;
  const trainerModalTitle = document.querySelector('[data-arcav-trainer-modal-title]');
  const trainerIdEl = document.querySelector('[data-arcav-trainer-id]');

  let me = null;
  let meAdmin = false;
  let trainingTypes = [];
  let trainings = [];
  let trainers = [];

  let pickerEmployees = [];
  let pickerPage = 1;
  let pickerLastPage = 1;
  const selectedParticipantIds = new Set();
  const employeeCache = new Map(); // id -> { fullName, email }
  let trainingModalWasOpenBeforePicker = false;

  function statusBadge(status) {
    const map = {
      active: ['badge badge-success d-inline-flex align-items-center badge-xs', 'Active'],
      inactive: ['badge badge-danger d-inline-flex align-items-center badge-xs', 'Inactive'],
      completed: ['badge badge-soft-success d-inline-flex align-items-center badge-xs', 'Completed'],
    };
    const [cls, label] = map[status] || ['badge badge-soft-secondary', status];
    return `<span class="${cls}"><i class="ti ti-point-filled me-1"></i>${esc(label)}</span>`;
  }

  function renderParticipantsSummary() {
    if (!trainingParticipantsSummary) return;
    const ids = Array.from(selectedParticipantIds.values());
    if (!ids.length) {
      trainingParticipantsSummary.innerHTML = '<div class="text-muted fs-12">Belum ada peserta dipilih.</div>';
      return;
    }
    const chips = ids.slice(0, 12).map((id) => {
      const info = employeeCache.get(Number(id));
      const name = info?.fullName ? esc(info.fullName) : `User #${esc(id)}`;
      const email = info?.email ? String(info.email) : '';
      const title = email ? `${name} — ${esc(email)}` : name;
      return `
        <span class="badge badge-soft-primary d-inline-flex align-items-center px-2 py-1"
              style="max-width: 260px;"
              title="${title}">
          <span class="text-truncate" style="max-width: 240px;">${name}</span>
        </span>
      `;
    }).join('');
    const extraCount = ids.length > 12 ? `<span class="text-muted fs-12 ms-1">(+${esc(ids.length - 12)} lainnya)</span>` : '';
    trainingParticipantsSummary.innerHTML = `
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex flex-wrap gap-2">
          ${chips}
          ${extraCount}
        </div>
        <div class="text-muted fs-12">Selected: ${esc(ids.length)}</div>
      </div>
    `;
  }

  function updateSelectedCount() {
    if (!participantsSelectedCount) return;
    participantsSelectedCount.textContent = `Selected: ${selectedParticipantIds.size}`;
  }

  function renderPickerTable() {
    if (!participantsTbody) return;
    if (!pickerEmployees.length) {
      participantsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada karyawan.</td></tr>';
      return;
    }

    participantsTbody.innerHTML = pickerEmployees
      .map((e) => {
        const checked = selectedParticipantIds.has(Number(e.id)) ? 'checked' : '';
        return `
          <tr>
            <td>
              <div class="form-check form-check-md">
                <input class="form-check-input" type="checkbox" data-participant-id="${esc(e.id)}" ${checked}>
              </div>
            </td>
            <td class="text-break">
              <div class="fw-medium">${esc(e.fullName || '')}</div>
              <div class="text-muted fs-12">${esc(e.email || '')}</div>
            </td>
            <td>${esc(e.team || '—')}</td>
            <td>${esc(e.designation || '—')}</td>
            <td>${esc(e.employmentStatus || '—')}</td>
          </tr>
        `;
      })
      .join('');
  }

  async function loadPickerEmployees() {
    if (!participantsTbody) return;
    participantsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Memuat karyawan...</td></tr>';
    try {
      const search = (participantsSearch?.value || '').trim();
      const params = new URLSearchParams();
      params.set('perPage', '20');
      params.set('page', String(pickerPage));
      if (search) params.set('search', search);
      const res = await apiRequest('GET', `/v1/hcm/employees?${params.toString()}`);
      pickerEmployees = Array.isArray(res?.data) ? res.data : [];
      // Cache for nicer summary labels
      pickerEmployees.forEach((e) => {
        const id = Number(e?.id);
        if (!Number.isFinite(id) || id <= 0) return;
        employeeCache.set(id, { fullName: e.fullName || e.name || '', email: e.email || '' });
      });
      const total = Number(res?.meta?.total || 0);
      const perPage = Number(res?.meta?.perPage || 20);
      pickerLastPage = Math.max(1, Math.ceil(total / Math.max(1, perPage)));
      if (participantsPage) participantsPage.textContent = `Page ${pickerPage} / ${pickerLastPage}`;
      if (participantsPrev) participantsPrev.disabled = pickerPage <= 1;
      if (participantsNext) participantsNext.disabled = pickerPage >= pickerLastPage;
      if (participantsSelectAll) participantsSelectAll.checked = pickerEmployees.length > 0 && pickerEmployees.every((e) => selectedParticipantIds.has(Number(e.id)));
      renderPickerTable();
      updateSelectedCount();
    } catch (e) {
      const msg = apiErrorMessage(e);
      participantsTbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Gagal memuat karyawan.<div class="text-muted fs-12 mt-1">${esc(msg)}</div></td></tr>`;
      notify('error', msg);
    }
  }

  function moneyIdr(cents) {
    const v = Number(cents ?? 0);
    // Phase 1: use integer; display as IDR without decimals.
    const idr = Math.max(0, Math.round(v));
    return `Rp ${idr.toLocaleString('id-ID')}`;
  }

  function participantsCell(p) {
    const names = Array.isArray(p) ? p.map((x) => x?.name).filter(Boolean) : [];
    if (!names.length) return '<span class="text-muted fs-12">—</span>';
    const shown = names.slice(0, 3);
    const extra = names.length - shown.length;
    return `
      <div class="d-flex flex-column">
        <div class="fw-medium">${esc(shown.join(', '))}${extra > 0 ? ` <span class="text-muted">(+${esc(extra)})</span>` : ''}</div>
      </div>
    `;
  }

  function fillTrainingTypeOptions() {
    const active = trainingTypes.filter((t) => t.isActive);
    if (trainingTypeSelect) {
      trainingTypeSelect.innerHTML = ['<option value="">—</option>']
        .concat(active.map((t) => `<option value="${esc(t.id)}">${esc(t.name)}</option>`))
        .join('');
    }
    if (trainingTypeFilter) {
      trainingTypeFilter.innerHTML = ['<option value="">Training Type (All)</option>']
        .concat(trainingTypes.map((t) => `<option value="${esc(t.id)}">${esc(t.name)}</option>`))
        .join('');
    }
  }

  function fillTrainerOptions() {
    if (!trainingTrainerSelect) return;
    const active = trainers.filter((t) => t.isActive);
    const opts = ['<option value="">—</option>']
      .concat(active.map((t) => `<option value="${esc(t.id)}">${esc(t.name)}</option>`))
      .concat(['<option value="__other__">Other...</option>']);
    trainingTrainerSelect.innerHTML = opts.join('');
  }

  function findTrainerById(id) {
    const target = Number(id || 0);
    if (!Number.isFinite(target) || target <= 0) return null;
    return trainers.find((t) => Number(t.id) === target) || null;
  }

  function syncTrainerOtherVisibility() {
    if (!trainingTrainerSelect || !trainingTrainerOther) return;
    const v = trainingTrainerSelect.value;
    const show = v === '__other__';
    trainingTrainerOther.style.display = show ? '' : 'none';
    if (!show) {
      // If selecting from master, mirror to hidden field (so payload consistent)
      trainingTrainerOther.value = '';
    }
  }

  function renderTrainingTypes() {
    if (!trainingTypesTbody) return;
    const q = (trainingTypeSearch?.value || '').trim().toLowerCase();
    const rows = trainingTypes.filter((t) => {
      if (!q) return true;
      return String(t.name).toLowerCase().includes(q) || String(t.description || '').toLowerCase().includes(q);
    });

    if (!rows.length) {
      trainingTypesTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Belum ada training type.</td></tr>';
      return;
    }

    trainingTypesTbody.innerHTML = rows
      .map(
        (t) => `
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
              <button type="button" class="btn btn-sm btn-white" data-training-type-action="edit" data-id="${esc(t.id)}">Edit</button>
              <button type="button" class="btn btn-sm btn-danger" data-training-type-action="delete" data-id="${esc(t.id)}">Delete</button>
            </div>
          ` : '<span class="text-muted fs-12">Admin only</span>'}
        </td>
      </tr>
    `
      )
      .join('');
  }

  async function loadTrainingTypes() {
    const needs = !!trainingTypesTbody || !!trainingTypeSelect || !!trainingTypeFilter;
    if (!needs) return;

    if (trainingTypesTbody) {
      trainingTypesTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Memuat training types...</td></tr>';
    }

    try {
      const res = await apiRequest('GET', `${BASE}/types`);
      trainingTypes = Array.isArray(res?.data) ? res.data : [];
      fillTrainingTypeOptions();
      if (trainingTypesTbody) renderTrainingTypes();
    } catch (e) {
      const msg = apiErrorMessage(e);
      if (trainingTypesTbody) {
        trainingTypesTbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-4">Gagal memuat training types.<div class="text-muted fs-12 mt-1">${esc(msg)}</div></td></tr>`;
      }
      if (trainingTypeSelect) trainingTypeSelect.innerHTML = '<option value="">(Gagal memuat training types)</option>';
      if (trainingTypeFilter) trainingTypeFilter.innerHTML = '<option value="">Training Type (Gagal load)</option>';
      notify('error', msg);
    }
  }

  function openTrainingTypeModal(mode, row) {
    if (!trainingTypeForm || !trainingTypeModal) return;
    trainingTypeForm.reset();
    const name = trainingTypeForm.querySelector('[name="name"]');
    const desc = trainingTypeForm.querySelector('[name="description"]');
    const active = trainingTypeForm.querySelector('[name="isActive"]');
    if (mode === 'edit' && row) {
      trainingTypeModalTitle.textContent = 'Edit Training Type';
      trainingTypeIdEl.value = row.id;
      name.value = row.name || '';
      desc.value = row.description || '';
      active.checked = !!row.isActive;
    } else {
      trainingTypeModalTitle.textContent = 'Add Training Type';
      trainingTypeIdEl.value = '';
      active.checked = true;
    }
    trainingTypeModal.show();
  }

  async function saveTrainingType(e) {
    e.preventDefault();
    if (!meAdmin) {
      notify('error', 'Hanya HCM Admin yang bisa mengubah training types.');
      return;
    }
    const fd = new FormData(e.currentTarget);
    const payload = {
      name: (fd.get('name') || '').toString(),
      description: (fd.get('description') || '').toString() || null,
      isActive: !!fd.get('isActive'),
    };
    const id = (trainingTypeIdEl?.value || '').trim();
    try {
      if (id) await apiRequest('PUT', `${BASE}/types/${id}`, payload);
      else await apiRequest('POST', `${BASE}/types`, payload);
      trainingTypeModal?.hide();
      notify('success', 'Training type tersimpan.');
      await loadTrainingTypes();
    } catch (e2) {
      notify('error', apiErrorMessage(e2));
    }
  }

  async function deleteTrainingType(id) {
    if (!meAdmin) return;
    if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== 'function') {
      notify('error', 'Confirm modal tidak tersedia.');
      return;
    }
    const ok = await window.ArcavUi.confirmDelete('Hapus training type ini?', 'Hapus');
    if (!ok) return;
    try {
      await apiRequest('DELETE', `${BASE}/types/${id}`);
      notify('success', 'Training type terhapus.');
      await loadTrainingTypes();
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  function setText(el, v) {
    if (!el) return;
    el.textContent = v || '—';
  }

  function openTrainingDetail(row) {
    if (!trainingDetailModal) return;
    setText(trainingDetailType, row?.type?.name || '—');
    setText(trainingDetailTrainer, row?.trainerName || '—');
    if (trainingDetailStatus) {
      trainingDetailStatus.innerHTML = statusBadge(row?.status);
    }
    setText(trainingDetailStart, row?.startDate || '—');
    setText(trainingDetailEnd, row?.endDate || '—');
    setText(trainingDetailCost, moneyIdr(row?.costCents || 0));
    if (trainingDetailParticipants) {
      const parts = Array.isArray(row?.participants) ? row.participants : [];
      if (!parts.length) {
        trainingDetailParticipants.innerHTML = '<span class="text-muted fs-12">—</span>';
      } else {
        trainingDetailParticipants.innerHTML = parts
          .slice(0, 20)
          .map((p) => `<span class="badge badge-soft-primary me-1 mb-1" title="${esc(p.email || '')}">${esc(p.name || '')}</span>`)
          .join('') + (parts.length > 20 ? `<span class="text-muted fs-12">(+${esc(parts.length - 20)} lainnya)</span>` : '');
      }
    }
    if (trainingDetailDesc) {
      trainingDetailDesc.textContent = row?.description || '—';
    }
    trainingDetailModal.show();
  }

  function renderTrainings() {
    if (!trainingsTbody) return;
    if (!trainings.length) {
      trainingsTbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Belum ada training.</td></tr>';
      return;
    }

    trainingsTbody.innerHTML = trainings
      .map((t) => {
        const duration = t.startDate || t.endDate ? `${esc(t.startDate || '—')} - ${esc(t.endDate || '—')}` : '—';
        const actions = meAdmin
          ? `
            <div class="d-inline-flex gap-2">
              <button type="button" class="btn btn-sm btn-white" data-training-action="view" data-id="${esc(t.id)}" title="View">
                <i class="ti ti-eye"></i>
              </button>
              <button type="button" class="btn btn-sm btn-white" data-training-action="edit" data-id="${esc(t.id)}">Edit</button>
              <button type="button" class="btn btn-sm btn-danger" data-training-action="delete" data-id="${esc(t.id)}">Delete</button>
            </div>
          `
          : '<span class="text-muted fs-12">Admin only</span>';

        return `
        <tr>
          <td>${esc(t.type?.name || '—')}</td>
          <td class="text-break">${esc(t.trainerName || '—')}</td>
          <td class="text-break">${participantsCell(t.participants)}</td>
          <td>${duration}</td>
          <td class="text-break">${esc(t.description || '—')}</td>
          <td>${moneyIdr(t.costCents || 0)}</td>
          <td>${statusBadge(t.status)}</td>
          <td class="text-end">${actions}</td>
        </tr>
      `;
      })
      .join('');
  }

  function buildTrainingQuery() {
    const params = new URLSearchParams();
    params.set('perPage', '50');
    if (trainingStatusFilter?.value) params.set('status', trainingStatusFilter.value);
    if (trainingTypeFilter?.value) params.set('trainingTypeId', trainingTypeFilter.value);
    if (trainingQ?.value) params.set('q', trainingQ.value);
    return params.toString();
  }

  async function loadTrainings() {
    if (!trainingsTbody) return;
    trainingsTbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Memuat trainings...</td></tr>';
    try {
      const res = await apiRequest('GET', `${BASE}/trainings?${buildTrainingQuery()}`);
      trainings = Array.isArray(res?.data) ? res.data : [];
      renderTrainings();
    } catch (e) {
      const msg = apiErrorMessage(e);
      trainingsTbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">Gagal memuat trainings.<div class="text-muted fs-12 mt-1">${esc(msg)}</div></td></tr>`;
      notify('error', msg);
    }
  }

  function parseIds(text) {
    return String(text || '')
      .split(',')
      .map((x) => x.trim())
      .filter(Boolean)
      .map((x) => Number(x))
      .filter((n) => Number.isFinite(n) && n > 0);
  }

  function openTrainingModal(mode, row) {
    if (!trainingForm || !trainingModal) return;
    trainingForm.reset();
    if (trainingFlash) {
      trainingFlash.style.display = 'none';
      trainingFlash.innerHTML = '';
    }
    const status = trainingForm.querySelector('[name="status"]');
    const trainerName = trainingForm.querySelector('[name="trainerName"]');
    const sd = trainingForm.querySelector('[name="startDate"]');
    const ed = trainingForm.querySelector('[name="endDate"]');
    const costIdr = trainingForm.querySelector('[name="costIdr"]');
    const desc = trainingForm.querySelector('[name="description"]');
    const typeId = trainingForm.querySelector('[name="trainingTypeId"]');
    // participantUserIds now managed via picker (selectedParticipantIds)

    if (mode === 'edit' && row) {
      trainingModalTitle.textContent = 'Edit Training';
      trainingIdEl.value = row.id;
      status.value = row.status || 'active';
      trainerName.value = row.trainerName || '';
      sd.value = row.startDate || '';
      ed.value = row.endDate || '';
      costIdr.value = String(row.costCents ?? 0);
      desc.value = row.description || '';
      typeId.value = row.type?.id || '';
      const ids = Array.isArray(row.participants) ? row.participants.map((p) => p.id).filter(Boolean) : [];
      // Picker state
      selectedParticipantIds.clear();
      ids.forEach((x) => selectedParticipantIds.add(Number(x)));
      // Cache participants info from training row (API includes name/email)
      (row.participants || []).forEach((p) => {
        const pid = Number(p?.id);
        if (!Number.isFinite(pid) || pid <= 0) return;
        employeeCache.set(pid, { fullName: p.name || '', email: p.email || '' });
      });
      renderParticipantsSummary();

      // Trainer dropdown: choose existing name if in options; otherwise fallback to Other.
      if (trainingTrainerSelect) {
        const hasTrainerId = Array.from(trainingTrainerSelect.options).some((o) => o.value === String(row.trainerId || ''));
        const fallbackTrainer = trainers.find((t) => String(t.name || '') === String(row.trainerName || ''));
        if (hasTrainerId) {
          trainingTrainerSelect.value = String(row.trainerId || '');
        } else if (fallbackTrainer) {
          trainingTrainerSelect.value = String(fallbackTrainer.id);
        } else {
          trainingTrainerSelect.value = row.trainerName ? '__other__' : '';
        }
        syncTrainerOtherVisibility();
        if (trainingTrainerOther && trainingTrainerSelect.value === '__other__') {
          trainingTrainerOther.value = row.trainerName || '';
        }
      }
    } else {
      trainingModalTitle.textContent = 'Add Training';
      trainingIdEl.value = '';
      status.value = 'active';
      if (trainingTrainerSelect) {
        trainingTrainerSelect.value = '';
        syncTrainerOtherVisibility();
      }
      selectedParticipantIds.clear();
      renderParticipantsSummary();
    }
    trainingModal.show();
  }

  async function saveTraining(e) {
    e.preventDefault();
    if (!meAdmin) {
      const msg = 'Hanya HCM Admin yang bisa mengubah training.';
      notify('error', msg);
      flash(trainingFlash, 'error', msg);
      return;
    }
    if (trainingForm && typeof trainingForm.checkValidity === 'function' && !trainingForm.checkValidity()) {
      if (typeof trainingForm.reportValidity === 'function') {
        trainingForm.reportValidity();
      }
      flash(trainingFlash, 'error', 'Form belum valid. Mohon periksa input yang bertanda error (mis. Cost tidak boleh negatif).');
      return;
    }
    const fd = new FormData(e.currentTarget);
    const id = (trainingIdEl?.value || '').trim();
    const selectedTrainer = trainingTrainerSelect?.value || '';
    const selectedTrainerObj = selectedTrainer && selectedTrainer !== '__other__' ? findTrainerById(selectedTrainer) : null;
    const trainerNameValue =
      selectedTrainer === '__other__'
        ? (trainingTrainerOther?.value || '').toString()
        : (selectedTrainerObj?.name || '');
    const trainerIdValue = selectedTrainerObj?.id ? Number(selectedTrainerObj.id) : null;
    const payload = {
      trainingTypeId: fd.get('trainingTypeId') ? Number(fd.get('trainingTypeId')) : null,
      trainerId: trainerIdValue,
      trainerName: trainerNameValue ? trainerNameValue : null,
      participantUserIds: Array.from(selectedParticipantIds.values()),
      startDate: (fd.get('startDate') || '').toString() || null,
      endDate: (fd.get('endDate') || '').toString() || null,
      description: (fd.get('description') || '').toString() || null,
      costCents: Number(fd.get('costIdr') || 0),
      status: (fd.get('status') || 'active').toString(),
    };
    try {
      if (id) await apiRequest('PUT', `${BASE}/trainings/${id}`, payload);
      else await apiRequest('POST', `${BASE}/trainings`, payload);
      trainingModal?.hide();
      notify('success', 'Training tersimpan.');
      flash(trainingFlash, 'success', 'Training tersimpan.');
      await loadTrainings();
    } catch (e2) {
      const msg = apiErrorMessage(e2);
      notify('error', msg);
      flash(trainingFlash, 'error', msg);
    }
  }

  async function deleteTraining(id) {
    if (!meAdmin) return;
    if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== 'function') {
      notify('error', 'Confirm modal tidak tersedia.');
      return;
    }
    const ok = await window.ArcavUi.confirmDelete('Hapus training ini?', 'Hapus');
    if (!ok) return;
    try {
      await apiRequest('DELETE', `${BASE}/trainings/${id}`);
      notify('success', 'Training terhapus.');
      await loadTrainings();
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  function bindTrainingTypesPage() {
    if (!trainingTypesTbody) return;
    if (trainingTypeReload) trainingTypeReload.addEventListener('click', loadTrainingTypes);
    if (trainingTypeSearch) trainingTypeSearch.addEventListener('input', renderTrainingTypes);
    if (trainingTypeForm) trainingTypeForm.addEventListener('submit', saveTrainingType);
    trainingTypesTbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-training-type-action]');
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      const action = btn.getAttribute('data-training-type-action');
      const row = trainingTypes.find((x) => String(x.id) === String(id));
      if (action === 'edit') openTrainingTypeModal('edit', row);
      if (action === 'delete') deleteTrainingType(id);
    });
  }

  function bindTrainingPage() {
    if (!trainingsTbody) return;
    if (trainingReload) trainingReload.addEventListener('click', loadTrainings);
    if (trainingTypeFilter) trainingTypeFilter.addEventListener('change', loadTrainings);
    if (trainingStatusFilter) trainingStatusFilter.addEventListener('change', loadTrainings);
    if (trainingQ) trainingQ.addEventListener('input', () => {
      clearTimeout(trainingQ.__t);
      trainingQ.__t = setTimeout(loadTrainings, 300);
    });
    if (trainingForm) trainingForm.addEventListener('submit', saveTraining);
    // Some browsers block submit event when invalid; hook click to show feedback.
    if (trainingSaveBtn) {
      trainingSaveBtn.addEventListener('click', (ev) => {
        if (trainingForm && typeof trainingForm.checkValidity === 'function' && !trainingForm.checkValidity()) {
          ev.preventDefault();
          ev.stopPropagation();
          if (typeof trainingForm.reportValidity === 'function') {
            trainingForm.reportValidity();
          }
          flash(trainingFlash, 'error', 'Form belum valid. Mohon periksa input yang bertanda error (mis. Cost tidak boleh negatif).');
        }
      });
    }
    if (trainingTrainerSelect) {
      trainingTrainerSelect.addEventListener('change', () => {
        syncTrainerOtherVisibility();
        // keep hidden input in sync for "normal" selection
        if (trainingTrainerSelect.value !== '__other__' && trainingTrainerOther) {
          const name = trainingForm?.querySelector('[name="trainerName"]');
          const selected = findTrainerById(trainingTrainerSelect.value);
          if (name) name.value = selected?.name || '';
        }
      });
    }

    trainingsTbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-training-action]');
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      const action = btn.getAttribute('data-training-action');
      const row = trainings.find((x) => String(x.id) === String(id));
      if (action === 'view') openTrainingDetail(row);
      if (action === 'edit') openTrainingModal('edit', row);
      if (action === 'delete') deleteTraining(id);
    });

    if (openParticipantsPickerBtn && participantsPicker) {
      openParticipantsPickerBtn.addEventListener('click', () => {
        trainingModalWasOpenBeforePicker = !!trainingModalEl?.classList.contains('show');
        participantsPicker.show();
      });
    }

    loadTrainings();
  }

  function renderTrainers() {
    if (!trainersTbody) return;
    if (!trainers.length) {
      trainersTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada trainer.</td></tr>';
      return;
    }

    trainersTbody.innerHTML = trainers
      .map((t) => {
        const actions = meAdmin
          ? `
            <div class="d-inline-flex gap-2">
              <button type="button" class="btn btn-sm btn-white" data-trainer-action="edit" data-id="${esc(t.id)}">Edit</button>
              <button type="button" class="btn btn-sm btn-danger" data-trainer-action="delete" data-id="${esc(t.id)}">Delete</button>
            </div>
          `
          : '<span class="text-muted fs-12">Admin only</span>';
        const status = t.isActive
          ? '<span class="badge badge-success d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>Active</span>'
          : '<span class="badge badge-danger d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>Inactive</span>';

        return `
          <tr>
            <td>
              <div class="fw-medium">${esc(t.name)}</div>
              <div class="text-muted fs-12">ID: ${esc(t.id)}</div>
            </td>
            <td class="text-break">${esc(t.phone || '—')}</td>
            <td class="text-break">${esc(t.email || '—')}</td>
            <td class="text-break">${esc(t.description || '—')}</td>
            <td>${status}</td>
            <td class="text-end">${actions}</td>
          </tr>
        `;
      })
      .join('');
  }

  function buildTrainerQuery() {
    const params = new URLSearchParams();
    params.set('perPage', '50');
    if (trainerStatus?.value) params.set('status', trainerStatus.value);
    if (trainerQ?.value) params.set('q', trainerQ.value);
    return params.toString();
  }

  async function loadTrainers() {
    const needs = !!trainersTbody || !!trainingTrainerSelect;
    if (!needs) return;
    if (trainersTbody) {
      trainersTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Memuat trainers...</td></tr>';
    }
    try {
      const res = await apiRequest('GET', `${BASE}/trainers?${buildTrainerQuery()}`);
      trainers = Array.isArray(res?.data) ? res.data : [];
      fillTrainerOptions();
      if (trainersTbody) renderTrainers();
    } catch (e) {
      const msg = apiErrorMessage(e);
      if (trainersTbody) {
        trainersTbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">Gagal memuat trainers.<div class="text-muted fs-12 mt-1">${esc(msg)}</div></td></tr>`;
      }
      if (trainingTrainerSelect) {
        trainingTrainerSelect.innerHTML = '<option value="">(Gagal memuat trainers)</option>';
      }
      notify('error', msg);
    }
  }

  function openTrainerModal(mode, row) {
    if (!trainerForm || !trainerModal) return;
    trainerForm.reset();
    const name = trainerForm.querySelector('[name="name"]');
    const email = trainerForm.querySelector('[name="email"]');
    const phone = trainerForm.querySelector('[name="phone"]');
    const desc = trainerForm.querySelector('[name="description"]');
    const active = trainerForm.querySelector('[name="isActive"]');

    if (mode === 'edit' && row) {
      trainerModalTitle.textContent = 'Edit Trainer';
      trainerIdEl.value = row.id;
      name.value = row.name || '';
      email.value = row.email || '';
      phone.value = row.phone || '';
      desc.value = row.description || '';
      active.checked = !!row.isActive;
    } else {
      trainerModalTitle.textContent = 'Add Trainer';
      trainerIdEl.value = '';
      active.checked = true;
    }
    trainerModal.show();
  }

  async function saveTrainer(e) {
    e.preventDefault();
    if (!meAdmin) {
      notify('error', 'Hanya HCM Admin yang bisa mengubah trainers.');
      return;
    }
    const fd = new FormData(e.currentTarget);
    const id = (trainerIdEl?.value || '').trim();
    const payload = {
      name: (fd.get('name') || '').toString(),
      email: (fd.get('email') || '').toString() || null,
      phone: (fd.get('phone') || '').toString() || null,
      description: (fd.get('description') || '').toString() || null,
      isActive: !!fd.get('isActive'),
    };
    try {
      if (id) await apiRequest('PUT', `${BASE}/trainers/${id}`, payload);
      else await apiRequest('POST', `${BASE}/trainers`, payload);
      trainerModal?.hide();
      notify('success', 'Trainer tersimpan.');
      await loadTrainers();
    } catch (e2) {
      notify('error', apiErrorMessage(e2));
    }
  }

  async function deleteTrainer(id) {
    if (!meAdmin) return;
    if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== 'function') {
      notify('error', 'Confirm modal tidak tersedia.');
      return;
    }
    const ok = await window.ArcavUi.confirmDelete('Hapus trainer ini?', 'Hapus');
    if (!ok) return;
    try {
      await apiRequest('DELETE', `${BASE}/trainers/${id}`);
      notify('success', 'Trainer terhapus.');
      await loadTrainers();
    } catch (e) {
      notify('error', apiErrorMessage(e));
    }
  }

  function bindTrainersPage() {
    const needs = !!trainersTbody || !!trainingTrainerSelect;
    if (!needs) return;

    if (trainerReload) trainerReload.addEventListener('click', loadTrainers);
    if (trainerStatus) trainerStatus.addEventListener('change', loadTrainers);
    if (trainerQ) trainerQ.addEventListener('input', () => {
      clearTimeout(trainerQ.__t);
      trainerQ.__t = setTimeout(loadTrainers, 300);
    });
    if (trainerForm) trainerForm.addEventListener('submit', saveTrainer);

    if (trainersTbody) {
      trainersTbody.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-trainer-action]');
        if (!btn) return;
        const id = btn.getAttribute('data-id');
        const action = btn.getAttribute('data-trainer-action');
        const row = trainers.find((x) => String(x.id) === String(id));
        if (action === 'edit') openTrainerModal('edit', row);
        if (action === 'delete') deleteTrainer(id);
      });
    }

    loadTrainers();
  }

  function bindParticipantsPicker() {
    if (!participantsPickerEl) return;

    participantsPickerEl.addEventListener('shown.bs.modal', () => {
      if (participantsFlash) {
        participantsFlash.style.display = 'none';
        participantsFlash.innerHTML = '';
      }
      pickerPage = 1;
      loadPickerEmployees();
      updateSelectedCount();
    });

    participantsPickerEl.addEventListener('hidden.bs.modal', () => {
      // Some themes hide the underlying modal; restore it.
      if (trainingModalWasOpenBeforePicker && trainingModal && !trainingModalEl?.classList.contains('show')) {
        trainingModal.show();
      }
      trainingModalWasOpenBeforePicker = false;
    });

    if (participantsSearch) {
      participantsSearch.addEventListener('input', () => {
        clearTimeout(participantsSearch.__t);
        participantsSearch.__t = setTimeout(() => {
          pickerPage = 1;
          loadPickerEmployees();
        }, 300);
      });
    }
    if (participantsPrev) {
      participantsPrev.addEventListener('click', () => {
        pickerPage = Math.max(1, pickerPage - 1);
        loadPickerEmployees();
      });
    }
    if (participantsNext) {
      participantsNext.addEventListener('click', () => {
        pickerPage = Math.min(pickerLastPage, pickerPage + 1);
        loadPickerEmployees();
      });
    }
    if (participantsSelectAll) {
      participantsSelectAll.addEventListener('change', () => {
        const check = !!participantsSelectAll.checked;
        pickerEmployees.forEach((e) => {
          const id = Number(e.id);
          if (!Number.isFinite(id)) return;
          if (check) selectedParticipantIds.add(id);
          else selectedParticipantIds.delete(id);
        });
        renderPickerTable();
        updateSelectedCount();
      });
    }

    if (participantsTbody) {
      participantsTbody.addEventListener('change', (e) => {
        const cb = e.target.closest('input[type="checkbox"][data-participant-id]');
        if (!cb) return;
        const id = Number(cb.getAttribute('data-participant-id'));
        if (!Number.isFinite(id)) return;
        if (cb.checked) selectedParticipantIds.add(id);
        else selectedParticipantIds.delete(id);
        if (participantsSelectAll) {
          participantsSelectAll.checked = pickerEmployees.length > 0 && pickerEmployees.every((x) => selectedParticipantIds.has(Number(x.id)));
        }
        updateSelectedCount();
      });
    }

    if (participantsApply) {
      participantsApply.addEventListener('click', () => {
        renderParticipantsSummary();
        // Always show visible feedback inside the picker (toast might be unavailable).
        flash(participantsFlash, 'success', 'Peserta tersimpan. Klik Save di modal Training untuk menyimpan perubahan.');
        notify('success', 'Peserta tersimpan (belum disave).');
        // Auto-close after user sees the wording.
        setTimeout(() => {
          participantsPicker?.hide();
        }, 800);
      });
    }
  }

  // Init
  loadMe().then((m) => {
    me = m;
    meAdmin = !!m?.hcmAdmin;

    // Admin-only create buttons: hide for non-admin
    const btnType = document.querySelector('[data-bs-target="#arcav_training_type_modal"]');
    if (btnType) btnType.style.display = meAdmin ? '' : 'none';
    const btnTraining = document.querySelector('[data-bs-target="#arcav_training_modal"]');
    if (btnTraining) btnTraining.style.display = meAdmin ? '' : 'none';
    const btnTrainer = document.querySelector('[data-bs-target="#arcav_trainer_modal"]');
    if (btnTrainer) btnTrainer.style.display = meAdmin ? '' : 'none';

    bindTrainingTypesPage();

    return loadTrainingTypes().then(() => {
      bindTrainingPage();
      bindTrainersPage();
      bindParticipantsPicker();
    });
  });
})();

