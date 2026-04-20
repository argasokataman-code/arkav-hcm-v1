import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadAuthApi() {
  vi.resetModules();
  await import('../../../frontend/resources/js/api-client.js');
  return window.AuthApi;
}

function mockFetchOk(data = { success: true, data: [] }) {
  const fetchMock = vi.fn().mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => data,
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

describe('Training API contract wiring', () => {
  beforeEach(() => {
    document.body.innerHTML = '<div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>';
    localStorage.clear();
  });

  it('maps GET training types to /v1/hcm/training/types', async () => {
    const fetchMock = mockFetchOk();
    const api = await loadAuthApi();

    await api.request('get', '/hcm/training/types');

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/v1/hcm/training/types');
    expect(options.method).toBe('GET');
  });

  it('maps POST trainer payload to /v1/hcm/training/trainers', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { id: 7 } });
    const api = await loadAuthApi();

    await api.request('post', '/hcm/training/trainers', {
      name: 'Trainer A',
      email: 'trainer.a@example.com',
      isActive: true,
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/v1/hcm/training/trainers');
    expect(options.method).toBe('POST');
    expect(JSON.parse(options.body)).toEqual({
      name: 'Trainer A',
      email: 'trainer.a@example.com',
      isActive: true,
    });
  });

  it('maps PUT training update payload to /v1/hcm/training/trainings/{id}', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { id: 11 } });
    const api = await loadAuthApi();

    await api.request('put', '/hcm/training/trainings/11', {
      status: 'completed',
      cost: 150000,
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/v1/hcm/training/trainings/11');
    expect(options.method).toBe('PUT');
    expect(JSON.parse(options.body)).toEqual({
      status: 'completed',
      cost: 150000,
    });
  });

  it('maps GET trainings with filter query to /v1/hcm/training/trainings', async () => {
    const fetchMock = mockFetchOk();
    const api = await loadAuthApi();

    await api.request('get', '/hcm/training/trainings', {
      status: 'active',
      trainingTypeId: 2,
      q: 'safety',
      perPage: 20,
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toContain('/v1/hcm/training/trainings?');
    expect(url).toContain('status=active');
    expect(url).toContain('trainingTypeId=2');
    expect(url).toContain('q=safety');
    expect(url).toContain('perPage=20');
    expect(options.method).toBe('GET');
  });

  it('maps user trainings endpoint to /v1/hcm/training/users/{id}/trainings', async () => {
    const fetchMock = mockFetchOk({ success: true, data: [{ id: 31 }] });
    const api = await loadAuthApi();

    await api.request('get', '/hcm/training/users/99/trainings');

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/v1/hcm/training/users/99/trainings');
    expect(options.method).toBe('GET');
  });

  it('keeps unauthorized contract recognizable for UI auth guard', async () => {
    const api = await loadAuthApi();

    expect(api.isUnauthorizedApiPayload(401, { error: { code: 'AUTH_UNAUTHORIZED' } })).toBe(true);
    expect(api.isUnauthorizedApiPayload(401, { error: { code: 'AUTH_INVALID_CREDENTIALS' } })).toBe(false);
    expect(api.isUnauthorizedApiPayload(403, { error: { code: 'ADMIN_REQUIRED' } })).toBe(false);
  });
});
