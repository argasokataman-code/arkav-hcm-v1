import { describe, test, expect, beforeEach, afterEach, vi } from 'vitest';
import {
    employeesListUrl,
    requestAuthMe,
    requestEmployees,
    requestEmployeesByState,
    requestJson,
    requestFormData,
    requestEmployeeDetail,
} from '../../../frontend/resources/js/employees/api';

describe('employeesListUrl', () => {
    test('builds URL with default pagination', () => {
        const url = employeesListUrl();
        expect(url).toContain('/v1/hcm/employees');
        expect(url).toContain('perPage=20');
        expect(url).toContain('page=1');
    });

    test('builds URL with custom pagination', () => {
        const url = employeesListUrl(50, 3);
        expect(url).toContain('perPage=50');
        expect(url).toContain('page=3');
    });
});

describe('requestJson', () => {
    beforeEach(() => {
        // Stub AuthApi for token
        window.AuthApi = {
            getToken: () => 'test-token-123',
            getTenantContext: () => ({ companyId: 1, companyUuid: 'uuid-abc' }),
        };
        window.axios = undefined;
    });

    afterEach(() => {
        delete window.AuthApi;
        delete window.axios;
    });

    test('GET request returns data on success', async () => {
        const mockData = { success: true, data: [{ id: 1 }] };
        const mockFetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockData),
        });
        vi.stubGlobal('fetch', mockFetch);

        const result = await requestJson('get', '/v1/hcm/employees?perPage=20&page=1', null);
        expect(result).toEqual(mockData);
        expect(mockFetch).toHaveBeenCalledWith(
            '/v1/hcm/employees?perPage=20&page=1',
            expect.objectContaining({ method: 'GET' })
        );

        vi.unstubAllGlobals();
    });

    test('GET request handles 403 error', async () => {
        const mockFetch = vi.fn().mockResolvedValue({
            ok: false,
            status: 403,
            json: () => Promise.resolve({ error: { message: 'Forbidden' } }),
        });
        vi.stubGlobal('fetch', mockFetch);

        await expect(requestJson('get', '/v1/hcm/employees', null)).rejects.toEqual({
            status: 403,
            data: { error: { message: 'Forbidden' } },
        });

        vi.unstubAllGlobals();
    });

    test('POST request sends JSON body', async () => {
        const mockFetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ success: true }),
        });
        vi.stubGlobal('fetch', mockFetch);

        const payload = { name: 'Test' };
        await requestJson('post', '/v1/hcm/employees', payload);

        const callArgs = mockFetch.mock.calls[0];
        expect(callArgs[1].method).toBe('POST');
        expect(callArgs[1].body).toBe(JSON.stringify(payload));
        expect(callArgs[1].headers['Content-Type']).toBe('application/json');

        vi.unstubAllGlobals();
    });

    test('uses axios when available', async () => {
        const mockAxios = vi.fn().mockResolvedValue({
            data: { success: true, data: [] },
        });
        window.axios = mockAxios;
        const result = await requestJson('get', '/v1/hcm/employees', null);
        expect(result).toEqual({ success: true, data: [] });
    });
});

describe('requestAuthMe', () => {
    beforeEach(() => {
        window.AuthApi = { getToken: () => 'token-xyz' };
        window.axios = undefined;
    });

    afterEach(() => {
        delete window.AuthApi;
        delete window.axios;
        vi.unstubAllGlobals();
    });

    test('returns auth data on success', async () => {
        const mockData = { success: true, data: { name: 'Admin' } };
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockData),
        }));

        const result = await requestAuthMe();
        expect(result).toEqual(mockData);
    });

    test('rejects on failure', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            status: 401,
            json: () => Promise.resolve({ error: { message: 'Unauthorized' } }),
        }));

        await expect(requestAuthMe()).rejects.toEqual({
            status: 401,
            data: { error: { message: 'Unauthorized' } },
        });
    });
});

describe('requestEmployees', () => {
    beforeEach(() => {
        window.AuthApi = { getToken: () => 'token-xyz' };
        window.axios = undefined;
    });

    afterEach(() => {
        delete window.AuthApi;
        delete window.axios;
        vi.unstubAllGlobals();
    });

    test('fetches employees with pagination', async () => {
        const mockData = { success: true, data: [{ id: 1 }], meta: { total: 1 } };
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(mockData),
        }));

        const result = await requestEmployees(10, 1);
        expect(result).toEqual(mockData);
    });
});

describe('requestEmployeesByState', () => {
    beforeEach(() => {
        window.AuthApi = {
            getToken: () => 'tok',
            getTenantContext: () => ({ companyId: 1 }),
        };
        window.axios = undefined;
    });

    afterEach(() => {
        delete window.AuthApi;
        delete window.axios;
        vi.unstubAllGlobals();
    });

    test('builds query from state', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ success: true, data: [] }),
        }));

        await requestEmployeesByState({
            search: 'alice',
            status: 'active',
            departmentId: '5',
            teamId: '3',
            scope: 'active_company',
            perPage: 25,
            page: 2,
        });

        const callUrl = vi.mocked(fetch).mock.calls[0][0];
        expect(callUrl).toContain('search=alice');
        expect(callUrl).toContain('status=active');
        expect(callUrl).toContain('departmentId=5');
        expect(callUrl).toContain('teamId=3');
        expect(callUrl).toContain('scope=active_company');
        expect(callUrl).toContain('perPage=25');
        expect(callUrl).toContain('page=2');
    });
});

describe('requestEmployeeDetail', () => {
    test('returns null for empty id', async () => {
        const result = await requestEmployeeDetail(null);
        expect(result).toBeNull();
    });

    test('fetches employee detail', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ success: true, data: { id: 42, name: 'Alice' } }),
        }));

        const result = await requestEmployeeDetail(42);
        expect(result.data.name).toBe('Alice');

        vi.unstubAllGlobals();
    });
});

describe('requestFormData', () => {
    beforeEach(() => {
        window.AuthApi = { getToken: () => 'tok' };
        window.axios = undefined;
    });

    afterEach(() => {
        delete window.AuthApi;
        delete window.axios;
        vi.unstubAllGlobals();
    });

    test('sends FormData and returns result', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ success: true }),
        }));

        const fd = new FormData();
        fd.append('file', 'test');
        const result = await requestFormData('post', '/v1/hcm/upload', fd);
        expect(result).toEqual({ success: true });
    });
});
