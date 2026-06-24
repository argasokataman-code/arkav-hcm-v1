import { beforeEach, describe, expect, it, vi } from 'vitest';

function okJson(data) {
  return { ok: true, status: 200, json: async () => data };
}

function errJson(status, data) {
  return { ok: false, status, json: async () => data };
}

describe('Team Master Data — wiring', () => {
  beforeEach(() => {
    vi.resetModules();
    window.history.pushState({}, '', '/teams');
    document.body.innerHTML = `
      <div data-company-id="1"></div>
      <div data-auth-token="test-token"></div>
      <table><tbody data-teams-body></tbody></table>
      <div data-hcm-pagination="teams"></div>
      <small data-hcm-showing="teams"></small>

      <div id="add_team">
        <form data-hcm-form="team-add">
          <input data-hcm-field="team-name" required maxlength="100" />
          <select data-hcm-field="team-department" required></select>
          <select data-hcm-field="team-lead"></select>
          <select data-hcm-field="team-active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          <button type="submit">Add Team</button>
        </form>
      </div>

      <div id="edit_team">
        <form data-hcm-form="team-edit">
          <input data-hcm-field="team-name" required maxlength="100" />
          <select data-hcm-field="team-department" required></select>
          <select data-hcm-field="team-lead"></select>
          <select data-hcm-field="team-active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          <button type="submit">Save Team</button>
        </form>
      </div>
    `;

    window.AuthApi = { getToken: () => 'test-token' };
    window.ArcavUi = { confirmDelete: vi.fn().mockResolvedValue(true) };
    window.ArcavValidation = { validateForm: vi.fn().mockReturnValue(true) };
    window.bootstrap = {
      Modal: {
        getOrCreateInstance: () => ({ show: vi.fn(), hide: vi.fn() }),
        getInstance: () => ({ hide: vi.fn() }),
      },
    };
    window.jQuery = undefined;
    global.fetch = vi.fn();
  });

  it('loads and calls fetch for teams list on page load', async () => {
    global.fetch.mockImplementation((url) => {
      if (String(url).includes('/v1/hcm/teams')) {
        return Promise.resolve(okJson({ success: true, data: [], meta: { page: 1, perPage: 20, total: 0 } }));
      }
      return Promise.resolve(okJson({ success: true, data: [] }));
    });

    await import('../../../frontend/resources/js/employees/team-master-data.js');

    await vi.waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/v1/hcm/teams'),
        expect.any(Object)
      );
    });
  });

  it('renders empty state when list is empty', async () => {
    global.fetch.mockImplementation((url) => {
      if (String(url).includes('/v1/hcm/teams')) {
        return Promise.resolve(okJson({ success: true, data: [], meta: { page: 1, perPage: 20, total: 0 } }));
      }
      return Promise.resolve(okJson({ success: true, data: [] }));
    });

    await import('../../../frontend/resources/js/employees/team-master-data.js');

    await vi.waitFor(() => {
      const body = document.querySelector('[data-teams-body]');
      expect(body.innerHTML).toContain('No teams found');
    });
  });

  it('renders team rows with Active badge', async () => {
    global.fetch.mockImplementation((url) => {
      if (String(url).includes('/v1/hcm/teams')) {
        return Promise.resolve(okJson({
          success: true,
          data: [{
            id: 1, name: 'Engineering Core', department_name: 'Engineering',
            member_count: 5, team_lead_id: 10, team_lead_name: 'John Doe', is_active: true,
          }],
          meta: { page: 1, perPage: 20, total: 1 },
        }));
      }
      return Promise.resolve(okJson({ success: true, data: [] }));
    });

    await import('../../../frontend/resources/js/employees/team-master-data.js');

    await vi.waitFor(() => {
      const body = document.querySelector('[data-teams-body]');
      expect(body.innerHTML).toContain('Engineering Core');
      expect(body.innerHTML).toContain('John Doe');
      expect(body.innerHTML).toContain('badge-success');
    });
  });

  it('renders team rows with Inactive badge', async () => {
    global.fetch.mockImplementation((url) => {
      if (String(url).includes('/v1/hcm/teams')) {
        return Promise.resolve(okJson({
          success: true,
          data: [{
            id: 2, name: 'Legacy Team', department_name: 'Old Dept',
            member_count: 0, team_lead_id: null, team_lead_name: null, is_active: false,
          }],
          meta: { page: 1, perPage: 20, total: 1 },
        }));
      }
      return Promise.resolve(okJson({ success: true, data: [] }));
    });

    await import('../../../frontend/resources/js/employees/team-master-data.js');

    await vi.waitFor(() => {
      const body = document.querySelector('[data-teams-body]');
      expect(body.innerHTML).toContain('Legacy Team');
      expect(body.innerHTML).toContain('badge-danger');
    });
  });

  it('calls ArcavValidation.validateForm on create submit', async () => {
    global.fetch.mockImplementation((url) => {
      if (String(url).includes('/v1/hcm/teams')) {
        return Promise.resolve(okJson({ success: true, data: [], meta: { page: 1, perPage: 20, total: 0 } }));
      }
      return Promise.resolve(okJson({ success: true, data: [] }));
    });

    await import('../../../frontend/resources/js/employees/team-master-data.js');

    await vi.waitFor(() => {
      expect(document.querySelector('[data-teams-body]').getAttribute('data-hydrated')).toBe('1');
    });

    const addForm = document.querySelector('[data-hcm-form="team-add"]');
    addForm.querySelector('[data-hcm-field="team-name"]').value = 'New Team';
    addForm.querySelector('[data-hcm-field="team-department"]').value = '1';

    addForm.dispatchEvent(new Event('submit'));

    await vi.waitFor(() => {
      expect(window.ArcavValidation.validateForm).toHaveBeenCalledWith(addForm);
    });
  });

  it('sends correct payload on create submit', async () => {
    let postPayload = null;
    global.fetch.mockImplementation((url, opts) => {
      if (opts && opts.method === 'POST' && String(url).includes('/v1/hcm/teams')) {
        postPayload = JSON.parse(opts.body);
        return Promise.resolve(okJson({ success: true, data: { id: 1, name: postPayload.name } }));
      }
      if (String(url).includes('/v1/hcm/teams')) {
        return Promise.resolve(okJson({ success: true, data: [], meta: { page: 1, perPage: 20, total: 0 } }));
      }
      if (String(url).includes('/v1/hcm/departments')) {
        return Promise.resolve(okJson({ success: true, data: [{ id: 5, name: 'Engineering' }] }));
      }
      return Promise.resolve(okJson({ success: true, data: [] }));
    });

    await import('../../../frontend/resources/js/employees/team-master-data.js');

    await vi.waitFor(() => {
      expect(document.querySelector('[data-teams-body]').getAttribute('data-hydrated')).toBe('1');
    });

    await vi.waitFor(() => {
      const deptSelect = document.querySelector('[data-hcm-field="team-department"]');
      expect(deptSelect.options.length).toBeGreaterThan(1);
    });

    const addForm = document.querySelector('[data-hcm-form="team-add"]');
    addForm.querySelector('[data-hcm-field="team-name"]').value = 'New Team';
    addForm.querySelector('[data-hcm-field="team-department"]').value = '5';
    addForm.querySelector('[data-hcm-field="team-lead"]').value = '';
    addForm.querySelector('[data-hcm-field="team-active"]').value = '1';

    addForm.dispatchEvent(new Event('submit'));

    await vi.waitFor(() => {
      expect(postPayload).not.toBeNull();
      expect(postPayload).toMatchObject({
        name: 'New Team',
        department_id: 5,
        team_lead_id: null,
        is_active: true,
      });
    });
  });

  it('shows toast on successful team creation', async () => {
    global.fetch.mockImplementation((url, opts) => {
      if (opts && opts.method === 'POST' && String(url).includes('/v1/hcm/teams')) {
        return Promise.resolve(okJson({ success: true, data: { id: 1, name: 'New Team' } }));
      }
      if (String(url).includes('/v1/hcm/teams')) {
        return Promise.resolve(okJson({ success: true, data: [], meta: { page: 1, perPage: 20, total: 0 } }));
      }
      return Promise.resolve(okJson({ success: true, data: [] }));
    });

    await import('../../../frontend/resources/js/employees/team-master-data.js');

    await vi.waitFor(() => {
      expect(document.querySelector('[data-teams-body]').getAttribute('data-hydrated')).toBe('1');
    });

    const addForm = document.querySelector('[data-hcm-form="team-add"]');
    addForm.querySelector('[data-hcm-field="team-name"]').value = 'New Team';
    addForm.querySelector('[data-hcm-field="team-department"]').value = '1';

    addForm.dispatchEvent(new Event('submit'));

    await vi.waitFor(() => {
      const toast = document.querySelector('[data-hcm-toast-container] .alert-success');
      expect(toast).toBeTruthy();
      expect(toast.textContent).toContain('Team created successfully');
    });
  });

  it('shows error toast on failed team creation', async () => {
    global.fetch.mockImplementation((url, opts) => {
      if (opts && opts.method === 'POST' && String(url).includes('/v1/hcm/teams')) {
        return Promise.resolve(errJson(422, {
          success: false,
          error: { code: 'VALIDATION_ERROR', message: 'Team name already exists.' },
        }));
      }
      if (String(url).includes('/v1/hcm/teams')) {
        return Promise.resolve(okJson({ success: true, data: [], meta: { page: 1, perPage: 20, total: 0 } }));
      }
      return Promise.resolve(okJson({ success: true, data: [] }));
    });

    await import('../../../frontend/resources/js/employees/team-master-data.js');

    await vi.waitFor(() => {
      expect(document.querySelector('[data-teams-body]').getAttribute('data-hydrated')).toBe('1');
    });

    const addForm = document.querySelector('[data-hcm-form="team-add"]');
    addForm.querySelector('[data-hcm-field="team-name"]').value = 'Duplicate Name';
    addForm.querySelector('[data-hcm-field="team-department"]').value = '1';

    addForm.dispatchEvent(new Event('submit'));

    await vi.waitFor(() => {
      const toast = document.querySelector('[data-hcm-toast-container] .alert-danger');
      expect(toast).toBeTruthy();
      expect(toast.textContent).toContain('Team name already exists');
    });
  });

  it('sends correct payload on delete', async () => {
    let deleteUrl = null;
    global.fetch.mockImplementation((url, opts) => {
      if (opts && opts.method === 'DELETE') {
        deleteUrl = String(url);
        return Promise.resolve(okJson({ success: true }));
      }
      if (String(url).includes('/v1/hcm/teams')) {
        return Promise.resolve(okJson({ success: true, data: [], meta: { page: 1, perPage: 20, total: 0 } }));
      }
      return Promise.resolve(okJson({ success: true, data: [] }));
    });

    await import('../../../frontend/resources/js/employees/team-master-data.js');

    await vi.waitFor(() => {
      expect(document.querySelector('[data-teams-body]').getAttribute('data-hydrated')).toBe('1');
    });

    const fakeDelete = document.createElement('a');
    fakeDelete.setAttribute('data-hcm-delete', 'team');
    fakeDelete.setAttribute('data-id', '99');
    fakeDelete.setAttribute('data-name', 'Test Team');
    document.body.appendChild(fakeDelete);

    fakeDelete.dispatchEvent(new Event('click', { bubbles: true }));

    await vi.waitFor(() => {
      expect(window.ArcavUi.confirmDelete).toHaveBeenCalled();
      expect(deleteUrl).toContain('/v1/hcm/teams/99');
    });
  });
});
