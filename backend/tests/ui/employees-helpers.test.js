import { describe, test, expect } from 'vitest';
import {
    escapeHtml,
    formatEmployeeCode,
    formatApiError,
    formatRupiah,
    getCurrentListUrl,
    buildEmployeeDetailUrl,
    downloadBlob,
    toCsv,
    normalizeEmployeeScope,
} from '../../../frontend/resources/js/employees/helpers';

describe('escapeHtml', () => {
    test('escapes HTML special chars', () => {
        expect(escapeHtml('<script>alert("xss")</script>')).toBe(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'
        );
    });

    test('handles plain text', () => {
        expect(escapeHtml('hello world')).toBe('hello world');
    });

    test('handles empty string', () => {
        expect(escapeHtml('')).toBe('');
    });

    test('handles null/undefined', () => {
        expect(escapeHtml(null)).toBe('');
        expect(escapeHtml(undefined)).toBe('');
    });
});

describe('formatEmployeeCode', () => {
    test('formats valid ID', () => {
        expect(formatEmployeeCode(42)).toBe('EMP-42');
        expect(formatEmployeeCode('99')).toBe('EMP-99');
    });

    test('returns dash for invalid', () => {
        expect(formatEmployeeCode(0)).toBe('-');
        expect(formatEmployeeCode(-5)).toBe('-');
        expect(formatEmployeeCode(null)).toBe('-');
        expect(formatEmployeeCode('abc')).toBe('-');
    });
});

describe('formatRupiah', () => {
    test('formats number with IDR prefix', () => {
        const result = formatRupiah(199000);
        expect(result).toMatch(/^Rp/);
        expect(result).toContain('199');
    });

    test('handles zero', () => {
        expect(formatRupiah(0)).toMatch(/^Rp/);
    });

    test('handles null/undefined', () => {
        const result = formatRupiah(null);
        expect(result).toMatch(/^Rp/);
    });
});

describe('formatApiError', () => {
    test('uses ApiErrorHelper if available', () => {
        window.ApiErrorHelper = { format: (d, s) => 'Formatted: ' + s };
        expect(formatApiError({}, 403)).toBe('Formatted: 403');
        delete window.ApiErrorHelper;
    });

    test('falls back to error.message', () => {
        expect(formatApiError({ error: { message: 'Not found' } }, 404)).toBe('Not found');
        expect(formatApiError({ message: 'Server error' }, 500)).toBe('Server error');
    });

    test('generic fallback', () => {
        expect(formatApiError(null, 0)).toBe('Request failed');
        expect(formatApiError({}, 503)).toBe('Request failed (503)');
    });
});

describe('normalizeEmployeeScope', () => {
    test('returns valid scope values', () => {
        expect(normalizeEmployeeScope('global')).toBe('global');
        expect(normalizeEmployeeScope('active_company')).toBe('active_company');
        expect(normalizeEmployeeScope('GLOBAL')).toBe('global');
    });

    test('returns empty for invalid', () => {
        expect(normalizeEmployeeScope('')).toBe('');
        expect(normalizeEmployeeScope('all')).toBe('');
        expect(normalizeEmployeeScope(null)).toBe('');
        expect(normalizeEmployeeScope(undefined)).toBe('');
    });
});

describe('toCsv', () => {
    test('generates CSV with headers and rows', () => {
        const rows = [
            { name: 'Alice', age: 30 },
            { name: 'Bob', age: 25 },
        ];
        const csv = toCsv(rows, ['name', 'age']);
        expect(csv).toContain('name,age');
        expect(csv).toContain('"Alice","30"');
        expect(csv).toContain('"Bob","25"');
    });

    test('escapes quotes in values', () => {
        const csv = toCsv([{ note: 'He said "hello"' }], ['note']);
        expect(csv).toContain('""');
    });
});

describe('getCurrentListUrl', () => {
    test('returns current path with search and hash', () => {
        // jsdom defaults to about:blank, so it should return that
        const url = getCurrentListUrl();
        expect(typeof url).toBe('string');
    });
});

describe('buildEmployeeDetailUrl', () => {
    test('builds URL with employee id', () => {
        const url = buildEmployeeDetailUrl(42);
        expect(url).toContain('employee-details');
        expect(url).toContain('id=42');
        expect(url).toContain('returnTo=');
    });
});
