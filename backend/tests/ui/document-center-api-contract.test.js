import { beforeEach, describe, expect, it, vi } from 'vitest';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function mockFetchOk(data = { success: true, data: [] }) {
  const fetchMock = vi.fn().mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => data,
    text: async () => JSON.stringify(data),
    headers: { get: () => 'application/json' },
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

function mockFetchError(status, data) {
  const fetchMock = vi.fn().mockResolvedValue({
    ok: false,
    status,
    json: async () => data,
    text: async () => JSON.stringify(data),
    headers: { get: () => 'application/json' },
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

/**
 * Simulates the fetch wrapper used in document-center.js:
 *  apiRequest(method, url, body?)
 */
async function apiRequest(method, url, body) {
  const headers = { 'Content-Type': 'application/json' };
  const options = {
    method: method.toUpperCase(),
    headers,
    credentials: 'same-origin',
  };
  if (body !== undefined) {
    options.body = JSON.stringify(body);
  }
  const res = await fetch(url, options);
  const json = await res.json();
  if (!res.ok) throw json;
  return json;
}

async function apiFormRequest(method, url, formData) {
  const options = {
    method: method.toUpperCase(),
    body: formData,
    credentials: 'same-origin',
  };
  const res = await fetch(url, options);
  const json = await res.json();
  if (!res.ok) throw json;
  return json;
}

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────

describe('Document Center — API contract wiring', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  // ── Categories ─────────────────────────────────────────────────────────────

  describe('GET /v1/hcm/document-center/categories', () => {
    it('sends GET to correct URL and returns data array', async () => {
      const fetchMock = mockFetchOk({
        success: true,
        data: [{ id: 1, uuid: 'abc', name: 'Kontrak Kerja', isActive: true }],
      });

      const res = await apiRequest('GET', '/v1/hcm/document-center/categories');

      const [url, options] = fetchMock.mock.calls[0];
      expect(url).toBe('/v1/hcm/document-center/categories');
      expect(options.method).toBe('GET');
      expect(res.success).toBe(true);
      expect(res.data).toHaveLength(1);
      expect(res.data[0].name).toBe('Kontrak Kerja');
    });
  });

  describe('POST /v1/hcm/document-center/categories', () => {
    it('sends POST with name payload', async () => {
      const fetchMock = mockFetchOk({ success: true, data: { id: 2, name: 'SK Jabatan', isActive: true } });

      await apiRequest('POST', '/v1/hcm/document-center/categories', {
        name: 'SK Jabatan',
        isActive: true,
      });

      const [url, options] = fetchMock.mock.calls[0];
      expect(url).toBe('/v1/hcm/document-center/categories');
      expect(options.method).toBe('POST');
      expect(JSON.parse(options.body)).toEqual({ name: 'SK Jabatan', isActive: true });
    });
  });

  describe('PUT /v1/hcm/document-center/categories/{id}', () => {
    it('sends PUT to correct URL with partial payload', async () => {
      const fetchMock = mockFetchOk({ success: true, data: { id: 3, name: 'Updated', isActive: false } });

      await apiRequest('PUT', '/v1/hcm/document-center/categories/3', {
        name: 'Updated',
        isActive: false,
      });

      const [url, options] = fetchMock.mock.calls[0];
      expect(url).toBe('/v1/hcm/document-center/categories/3');
      expect(options.method).toBe('PUT');
      expect(JSON.parse(options.body)).toMatchObject({ name: 'Updated', isActive: false });
    });
  });

  describe('DELETE /v1/hcm/document-center/categories/{id}', () => {
    it('sends DELETE to correct URL', async () => {
      const fetchMock = mockFetchOk({ success: true });

      await apiRequest('DELETE', '/v1/hcm/document-center/categories/5');

      const [url, options] = fetchMock.mock.calls[0];
      expect(url).toBe('/v1/hcm/document-center/categories/5');
      expect(options.method).toBe('DELETE');
    });
  });

  // ── Documents ──────────────────────────────────────────────────────────────

  describe('GET /v1/hcm/document-center/documents', () => {
    it('sends GET request and returns paginated envelope', async () => {
      const fetchMock = mockFetchOk({
        success: true,
        data: [{ id: 10, title: 'Contract', visibility: 'hr_only' }],
        meta: { currentPage: 1, lastPage: 1, total: 1, perPage: 20 },
      });

      const res = await apiRequest('GET', '/v1/hcm/document-center/documents');

      const [url, options] = fetchMock.mock.calls[0];
      expect(url).toBe('/v1/hcm/document-center/documents');
      expect(options.method).toBe('GET');
      expect(res.success).toBe(true);
      expect(res.meta.total).toBe(1);
    });

    it('passes query filters to URL', async () => {
      const fetchMock = mockFetchOk({ success: true, data: [], meta: {} });

      const params = new URLSearchParams({ page: 2, visibility: 'hr_only', q: 'contract' });
      await apiRequest('GET', `/v1/hcm/document-center/documents?${params}`);

      const [url] = fetchMock.mock.calls[0];
      expect(url).toContain('page=2');
      expect(url).toContain('visibility=hr_only');
      expect(url).toContain('q=contract');
    });
  });

  describe('POST /v1/hcm/document-center/documents (multipart)', () => {
    it('sends FormData without Content-Type header (browser sets boundary)', async () => {
      const fetchMock = mockFetchOk({
        success: true,
        data: { id: 11, title: 'New Doc', visibility: 'hr_only', originalName: 'file.pdf' },
      });

      const form = new FormData();
      form.append('title', 'New Doc');
      form.append('visibility', 'hr_only');
      form.append('employeeProfileId', '5');

      await apiFormRequest('POST', '/v1/hcm/document-center/documents', form);

      const [url, options] = fetchMock.mock.calls[0];
      expect(url).toBe('/v1/hcm/document-center/documents');
      expect(options.method).toBe('POST');
      // FormData is passed as body (not JSON-stringified)
      expect(options.body).toBeInstanceOf(FormData);
      // No Content-Type header — browser sets multipart boundary automatically
      expect(options.headers).toBeUndefined();
    });
  });

  describe('PUT /v1/hcm/document-center/documents/{id}', () => {
    it('sends JSON metadata update to correct URL', async () => {
      const fetchMock = mockFetchOk({
        success: true,
        data: { id: 11, title: 'Updated Title', visibility: 'employee_visible' },
      });

      await apiRequest('PUT', '/v1/hcm/document-center/documents/11', {
        title: 'Updated Title',
        visibility: 'employee_visible',
        expiresAt: '2027-12-31',
      });

      const [url, options] = fetchMock.mock.calls[0];
      expect(url).toBe('/v1/hcm/document-center/documents/11');
      expect(options.method).toBe('PUT');
      expect(JSON.parse(options.body)).toMatchObject({
        title: 'Updated Title',
        visibility: 'employee_visible',
        expiresAt: '2027-12-31',
      });
    });
  });

  describe('DELETE /v1/hcm/document-center/documents/{id}', () => {
    it('sends DELETE to correct URL', async () => {
      const fetchMock = mockFetchOk({ success: true });

      await apiRequest('DELETE', '/v1/hcm/document-center/documents/11');

      const [url, options] = fetchMock.mock.calls[0];
      expect(url).toBe('/v1/hcm/document-center/documents/11');
      expect(options.method).toBe('DELETE');
    });
  });

  describe('GET /v1/hcm/document-center/documents/{id}/download', () => {
    it('resolves download URL to correct pattern', async () => {
      const fetchMock = mockFetchOk({ success: true });

      await apiRequest('GET', '/v1/hcm/document-center/documents/99/download');

      const [url] = fetchMock.mock.calls[0];
      expect(url).toBe('/v1/hcm/document-center/documents/99/download');
    });
  });

  // ── Error handling ─────────────────────────────────────────────────────────

  describe('Error handling', () => {
    it('throws on 403 response', async () => {
      mockFetchError(403, { success: false, error: { code: 'AUTH_FORBIDDEN', message: 'Forbidden.' } });

      await expect(
        apiRequest('POST', '/v1/hcm/document-center/categories', { name: 'x' })
      ).rejects.toMatchObject({ success: false, error: { code: 'AUTH_FORBIDDEN' } });
    });

    it('throws on 422 TENANT_CONTEXT_REQUIRED', async () => {
      mockFetchError(422, {
        success: false,
        error: { code: 'TENANT_CONTEXT_REQUIRED', message: 'Active company context is required.' },
      });

      await expect(
        apiRequest('GET', '/v1/hcm/document-center/documents')
      ).rejects.toMatchObject({ error: { code: 'TENANT_CONTEXT_REQUIRED' } });
    });
  });
});
