import { beforeEach, describe, expect, it, vi } from 'vitest';

function mockOkJson(payload) {
  return Promise.resolve({
    ok: true,
    status: 200,
    json: async () => payload,
  });
}

async function loadShiftMasterModule() {
  vi.resetModules();
  return import('../../../frontend/resources/js/shift-master-data.js');
}

describe('Shift master data wiring', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    window.axios = undefined;
    window.ApiClient = { toast: vi.fn() };
    global.fetch = vi.fn();
  });

  it('hides write actions for users without schedule.manage permission', async () => {
    document.body.innerHTML = `
      <button type="button" data-bs-target="#arcav_add_shift">Add Shift</button>
      <table><tbody data-hcm-shifts-body></tbody></table>
    `;

    global.fetch
      .mockImplementationOnce(() =>
        mockOkJson({
          success: true,
          data: { permissions: { 'schedule.view': true } },
        }),
      )
      .mockImplementationOnce(() =>
        mockOkJson({
          success: true,
          data: [
            {
              id: 10,
              code: 'MORN',
              name: 'Morning',
              startTime: '08:00',
              endTime: '17:00',
              description: null,
              isActive: true,
              sortOrder: 1,
            },
          ],
        }),
      );

    await loadShiftMasterModule();

    await vi.waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith('/v1/identity/auth/me', expect.any(Object));
      expect(global.fetch).toHaveBeenCalledWith('/v1/hcm/shifts', expect.any(Object));
    });

    expect(document.querySelector('[data-bs-target="#arcav_add_shift"]')?.classList.contains('d-none')).toBe(true);
    expect(document.querySelector('[data-hcm-shift-edit]')).toBeNull();
    expect(document.querySelector('[data-hcm-shift-delete]')).toBeNull();
  });

  it('renders write actions for users with schedule.manage permission', async () => {
    document.body.innerHTML = `
      <button type="button" data-bs-target="#arcav_add_shift">Add Shift</button>
      <table><tbody data-hcm-shifts-body></tbody></table>
    `;

    global.fetch
      .mockImplementationOnce(() =>
        mockOkJson({
          success: true,
          data: { permissions: { 'schedule.manage': true } },
        }),
      )
      .mockImplementationOnce(() =>
        mockOkJson({
          success: true,
          data: [
            {
              id: 11,
              code: 'NIGHT',
              name: 'Night',
              startTime: '20:00',
              endTime: '05:00',
              description: null,
              isActive: true,
              sortOrder: 2,
            },
          ],
        }),
      );

    await loadShiftMasterModule();

    await vi.waitFor(() => {
      expect(document.querySelector('[data-hcm-shift-edit]')).not.toBeNull();
      expect(document.querySelector('[data-hcm-shift-delete]')).not.toBeNull();
    });

    expect(document.querySelector('[data-bs-target="#arcav_add_shift"]')?.classList.contains('d-none')).toBe(false);
  });
});
