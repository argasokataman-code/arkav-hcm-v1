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

describe('Termination API contract wiring', () => {
  beforeEach(() => {
    document.body.innerHTML = '<div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>';
    localStorage.clear();
  });

  it('maps GET terminations list with filters to /v1/hcm/terminations', async () => {
    const fetchMock = mockFetchOk();
    const api = await loadAuthApi();

    await api.request('get', '/hcm/terminations', {
      q: 'layoff',
      dateFrom: '2026-04-01',
      dateTo: '2026-04-30',
      perPage: 100,
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toContain('/v1/hcm/terminations?');
    expect(url).toContain('q=layoff');
    expect(url).toContain('dateFrom=2026-04-01');
    expect(url).toContain('dateTo=2026-04-30');
    expect(url).toContain('perPage=100');
    expect(options.method).toBe('GET');
  });

  it('maps POST create payload to /v1/hcm/terminations', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { id: 19 } });
    const api = await loadAuthApi();

    await api.request('post', '/hcm/terminations', {
      userId: '550e8400-e29b-41d4-a716-446655440000',
      department: 'Finance',
      terminationType: 'Layoff',
      reason: 'Workforce reduction',
      noticeDate: '2026-04-01',
      terminationDate: '2026-04-30',
      notes: 'Approved by management',
      status: 'finalized',
      settlementPayrollPeriod: '2026-05',
      finalSalaryAmount: 4500000,
      finalAllowanceAmount: 750000,
      finalDeductionAmount: 500000,
      assetReturnNotes: 'Laptop returned',
      clearanceNotes: 'Settlement goes to nearest payroll',
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/v1/hcm/terminations');
    expect(options.method).toBe('POST');
    expect(JSON.parse(options.body)).toEqual({
      userId: '550e8400-e29b-41d4-a716-446655440000',
      department: 'Finance',
      terminationType: 'Layoff',
      reason: 'Workforce reduction',
      noticeDate: '2026-04-01',
      terminationDate: '2026-04-30',
      notes: 'Approved by management',
      status: 'finalized',
      settlementPayrollPeriod: '2026-05',
      finalSalaryAmount: 4500000,
      finalAllowanceAmount: 750000,
      finalDeductionAmount: 500000,
      assetReturnNotes: 'Laptop returned',
      clearanceNotes: 'Settlement goes to nearest payroll',
    });
  });

  it('maps show and update endpoints to /v1/hcm/terminations/{id}', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { id: 19 } });
    const api = await loadAuthApi();

    await api.request('get', '/hcm/terminations/19');
    await api.request('put', '/hcm/terminations/19', {
      status: 'approved',
      notes: 'Completed',
    });

    const [showUrl, showOptions] = fetchMock.mock.calls[0];
    expect(showUrl).toBe('/v1/hcm/terminations/19');
    expect(showOptions.method).toBe('GET');

    const [updateUrl, updateOptions] = fetchMock.mock.calls[1];
    expect(updateUrl).toBe('/v1/hcm/terminations/19');
    expect(updateOptions.method).toBe('PUT');
    expect(JSON.parse(updateOptions.body)).toEqual({
      status: 'approved',
      notes: 'Completed',
    });
  });

  it('maps per-user list endpoint to /v1/hcm/terminations/users/{id}/terminations', async () => {
    const fetchMock = mockFetchOk({ success: true, data: [{ id: 21 }] });
    const api = await loadAuthApi();

    await api.request('get', '/hcm/terminations/users/55/terminations', { perPage: 20 });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toContain('/v1/hcm/terminations/users/55/terminations?');
    expect(url).toContain('perPage=20');
    expect(options.method).toBe('GET');
  });

  it('maps delete endpoint to /v1/hcm/terminations/{id}', async () => {
    const fetchMock = mockFetchOk({ success: true });
    const api = await loadAuthApi();

    await api.request('delete', '/hcm/terminations/19');

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/v1/hcm/terminations/19');
    expect(options.method).toBe('DELETE');
  });

  it('maps settlement preview endpoint to /v1/hcm/terminations/settlement-preview', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { resolvedPeriod: { label: '2026-05' } } });
    const api = await loadAuthApi();

    await api.request('get', '/hcm/terminations/settlement-preview', {
      userId: '550e8400-e29b-41d4-a716-446655440000',
      terminationDate: '2026-05-15',
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toContain('/v1/hcm/terminations/settlement-preview?');
    expect(url).toContain('userId=550e8400-e29b-41d4-a716-446655440000');
    expect(url).toContain('terminationDate=2026-05-15');
    expect(options.method).toBe('GET');
  });

  it('maps termination clearance return endpoint to /v1/hcm/terminations/{id}/clearance-items/{assignmentId}/return', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { returnedAssignmentId: 33 } });
    const api = await loadAuthApi();

    await api.request('post', '/hcm/terminations/19/clearance-items/33/return', {
      notes: 'Returned from termination workflow.',
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/v1/hcm/terminations/19/clearance-items/33/return');
    expect(options.method).toBe('POST');
    expect(JSON.parse(options.body)).toEqual({
      notes: 'Returned from termination workflow.',
    });
  });
});
