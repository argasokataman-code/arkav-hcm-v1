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

describe('Tickets API contract wiring', () => {
  beforeEach(() => {
    document.body.innerHTML = '<div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>';
    localStorage.clear();
  });

  it('maps GET tickets list with filters to /v1/hcm/tickets', async () => {
    const fetchMock = mockFetchOk();
    const api = await loadAuthApi();

    await api.request('get', '/hcm/tickets', {
      status: 'open',
      priority: 'high',
      q: 'laptop',
      perPage: 100,
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toContain('/v1/hcm/tickets?');
    expect(url).toContain('status=open');
    expect(url).toContain('priority=high');
    expect(url).toContain('q=laptop');
    expect(url).toContain('perPage=100');
    expect(options.method).toBe('GET');
  });

  it('maps POST ticket payload to /v1/hcm/tickets', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { id: 17 } });
    const api = await loadAuthApi();

    await api.request('post', '/hcm/tickets', {
      subject: 'Laptop freeze',
      description: 'Laptop keeps freezing',
      categoryId: 3,
      priority: 'high',
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/v1/hcm/tickets');
    expect(options.method).toBe('POST');
    expect(JSON.parse(options.body)).toEqual({
      subject: 'Laptop freeze',
      description: 'Laptop keeps freezing',
      categoryId: 3,
      priority: 'high',
    });
  });

  it('keeps numeric category and assignee identifiers in admin ticket payload', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { id: 18 } });
    const api = await loadAuthApi();

    await api.request('post', '/hcm/tickets', {
      subject: 'Assign ticket',
      description: 'Created from admin modal',
      categoryId: 7,
      priority: 'urgent',
      assigneeUserId: 12,
    });

    const [, options] = fetchMock.mock.calls[0];
    expect(JSON.parse(options.body)).toEqual({
      subject: 'Assign ticket',
      description: 'Created from admin modal',
      categoryId: 7,
      priority: 'urgent',
      assigneeUserId: 12,
    });
  });

  it('maps ticket detail and update endpoint to /v1/hcm/tickets/{id}', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { id: 21 } });
    const api = await loadAuthApi();

    await api.request('get', '/hcm/tickets/21');
    await api.request('put', '/hcm/tickets/21', { status: 'in_progress', assigneeUserId: 9 });

    const [detailUrl, detailOptions] = fetchMock.mock.calls[0];
    expect(detailUrl).toBe('/v1/hcm/tickets/21');
    expect(detailOptions.method).toBe('GET');

    const [updateUrl, updateOptions] = fetchMock.mock.calls[1];
    expect(updateUrl).toBe('/v1/hcm/tickets/21');
    expect(updateOptions.method).toBe('PUT');
    expect(JSON.parse(updateOptions.body)).toEqual({ status: 'in_progress', assigneeUserId: 9 });
  });

  it('maps ticket comment endpoint under /v1/hcm/tickets/{id}/comments', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { id: 31 } });
    const api = await loadAuthApi();

    await api.request('post', '/hcm/tickets/31/comments', { body: 'Please help quickly' });

    const [commentUrl, commentOptions] = fetchMock.mock.calls[0];
    expect(commentUrl).toBe('/v1/hcm/tickets/31/comments');
    expect(commentOptions.method).toBe('POST');
    expect(JSON.parse(commentOptions.body)).toEqual({ body: 'Please help quickly' });
  });

  it('maps ticket category and assignable endpoints correctly', async () => {
    const fetchMock = mockFetchOk();
    const api = await loadAuthApi();

    await api.request('get', '/hcm/tickets/assignable-users');
    await api.request('get', '/hcm/tickets/category-options');
    await api.request('post', '/hcm/tickets/categories', { name: 'IT', isActive: true, sortOrder: 1 });

    expect(fetchMock.mock.calls[0][0]).toBe('/v1/hcm/tickets/assignable-users');
    expect(fetchMock.mock.calls[1][0]).toBe('/v1/hcm/tickets/category-options');
    expect(fetchMock.mock.calls[2][0]).toBe('/v1/hcm/tickets/categories');
    expect(JSON.parse(fetchMock.mock.calls[2][1].body)).toEqual({ name: 'IT', isActive: true, sortOrder: 1 });
  });
});
