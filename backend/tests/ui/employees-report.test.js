import { describe, test, expect, beforeEach, afterEach, vi } from 'vitest';
import { makeReportHandlers } from '../../../frontend/resources/js/employees/report';

describe('makeReportHandlers', () => {
    let handlers;
    let mockDeps;

    beforeEach(() => {
        document.body.innerHTML = '';
        mockDeps = {
            escapeHtml: (v) => String(v || '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;'),
            formatEmployeeCode: (id) => 'EMP-' + id,
            formatApiError: (data, status) => data?.error?.message || 'Request failed',
            requestJson: vi.fn(),
            requestAuthMe: vi.fn(),
            requestAllEmployeesAggregated: vi.fn(),
        };
        handlers = makeReportHandlers(mockDeps);
    });

    // ── getEmployeeReportSourceMode ─────────────────────────────────
    describe('getEmployeeReportSourceMode', () => {
        test('returns live when element missing', () => {
            expect(handlers.getEmployeeReportSourceMode()).toBe('live');
        });

        test('returns live by default', () => {
            document.body.innerHTML = '<input data-employee-report-source value="live">';
            expect(handlers.getEmployeeReportSourceMode()).toBe('live');
        });

        test('returns archive when set', () => {
            document.body.innerHTML = '<input data-employee-report-source value="archive">';
            expect(handlers.getEmployeeReportSourceMode()).toBe('archive');
        });

        test('normalizes casing', () => {
            document.body.innerHTML = '<input data-employee-report-source value="ARCHIVE">';
            expect(handlers.getEmployeeReportSourceMode()).toBe('archive');
        });
    });

    // ── getEmployeeReportSnapshotId ─────────────────────────────────
    describe('getEmployeeReportSnapshotId', () => {
        test('returns 0 when element missing', () => {
            expect(handlers.getEmployeeReportSnapshotId()).toBe(0);
        });

        test('returns parsed number', () => {
            document.body.innerHTML = '<input data-employee-report-snapshot-id value="42">';
            expect(handlers.getEmployeeReportSnapshotId()).toBe(42);
        });

        test('returns 0 for invalid', () => {
            document.body.innerHTML = '<input data-employee-report-snapshot-id value="abc">';
            expect(handlers.getEmployeeReportSnapshotId()).toBe(0);
        });
    });

    // ── setEmployeeReportSourceBadge ────────────────────────────────
    describe('setEmployeeReportSourceBadge', () => {
        test('sets badge to Live', () => {
            document.body.innerHTML = '<span data-employee-report-source-badge></span>';
            document.body.innerHTML += '<input data-employee-report-source value="live">';
            handlers.setEmployeeReportSourceBadge();
            expect(document.querySelector('[data-employee-report-source-badge]').textContent)
                .toBe('Source: Live');
        });

        test('sets badge to Archive with snapshot id', () => {
            document.body.innerHTML = '<span data-employee-report-source-badge></span>';
            document.body.innerHTML += '<input data-employee-report-source value="archive">';
            document.body.innerHTML += '<input data-employee-report-snapshot-id value="7">';
            handlers.setEmployeeReportSourceBadge();
            expect(document.querySelector('[data-employee-report-source-badge]').textContent)
                .toBe('Source: Archive #7');
        });

        test('handles missing badge element', () => {
            expect(() => handlers.setEmployeeReportSourceBadge()).not.toThrow();
        });
    });

    // ── syncEmployeeReportSourceControls ────────────────────────────
    describe('syncEmployeeReportSourceControls', () => {
        test('shows snapshot wrap when archive mode', () => {
            document.body.innerHTML = '<div data-employee-report-snapshot-wrap class="d-none"></div>';
            document.body.innerHTML += '<input data-employee-report-source value="archive">';
            document.body.innerHTML += '<span data-employee-report-source-badge></span>';
            handlers.syncEmployeeReportSourceControls();
            const wrap = document.querySelector('[data-employee-report-snapshot-wrap]');
            expect(wrap.classList.contains('d-none')).toBe(false);
        });

        test('hides snapshot wrap when live mode', () => {
            document.body.innerHTML = '<div data-employee-report-snapshot-wrap></div>';
            document.body.innerHTML += '<input data-employee-report-source value="live">';
            document.body.innerHTML += '<span data-employee-report-source-badge></span>';
            handlers.syncEmployeeReportSourceControls();
            const wrap = document.querySelector('[data-employee-report-snapshot-wrap]');
            expect(wrap.classList.contains('d-none')).toBe(true);
        });
    });

    // ── normalizeArchiveEmployeeRows ────────────────────────────────
    describe('normalizeArchiveEmployeeRows', () => {
        test('returns empty array for null snapshot', () => {
            expect(handlers.normalizeArchiveEmployeeRows(null)).toEqual([]);
        });

        test('returns empty when module missing', () => {
            expect(handlers.normalizeArchiveEmployeeRows({ dataByModule: {} })).toEqual([]);
        });

        test('maps by_status to rows', () => {
            const snapshot = {
                id: 1,
                periodEnd: '2026-05-31',
                dataByModule: {
                    employee: {
                        by_status: {
                            active: { status: 'active', count: 10, percentage: 80 },
                            inactive: { status: 'inactive', count: 2, percentage: 20 },
                        },
                    },
                },
            };
            const rows = handlers.normalizeArchiveEmployeeRows(snapshot);
            expect(rows).toHaveLength(2);
            expect(rows[0].fullName).toContain('active');
            expect(rows[0].email).toContain('10');
            expect(rows[1].fullName).toContain('inactive');
            expect(rows[1].email).toContain('2');
        });
    });

    // ── renderReportMessage ─────────────────────────────────────────
    describe('renderReportMessage', () => {
        test('renders message in tbody', () => {
            document.body.innerHTML = '<table><tbody data-employee-report-body></tbody></table>';
            handlers.renderReportMessage('Data tidak ditemukan');
            const body = document.querySelector('[data-employee-report-body]');
            expect(body.innerHTML).toContain('Data tidak ditemukan');
            expect(body.getAttribute('data-hydrated')).toBeNull();
        });

        test('handles missing tbody', () => {
            expect(() => handlers.renderReportMessage('test')).not.toThrow();
        });

        test('escapes HTML', () => {
            document.body.innerHTML = '<table><tbody data-employee-report-body></tbody></table>';
            handlers.renderReportMessage('<script>alert(1)</script>');
            const body = document.querySelector('[data-employee-report-body]');
            expect(body.innerHTML).toContain('&lt;script&gt;');
            expect(body.innerHTML).not.toContain('<script>');
        });
    });

    // ── renderReportTable ───────────────────────────────────────────
    describe('renderReportTable', () => {
        function setupTable() {
            document.body.innerHTML = '<table><tbody data-employee-report-body></tbody></table>';
        }

        test('renders empty message when no rows', () => {
            setupTable();
            handlers.renderReportTable([]);
            const body = document.querySelector('[data-employee-report-body]');
            expect(body.innerHTML).toContain('No employees');
        });

        test('renders employee rows', () => {
            setupTable();
            handlers.renderReportTable([
                { id: 1, fullName: 'Alice', email: 'alice@test.com', departmentName: 'Eng', employmentStatus: 'active' },
            ]);
            const body = document.querySelector('[data-employee-report-body]');
            expect(body.innerHTML).toContain('EMP-1');
            expect(body.innerHTML).toContain('Alice');
            expect(body.innerHTML).toContain('alice@test.com');
            expect(body.innerHTML).toContain('Eng');
            expect(body.innerHTML).toContain('badge-success');
        });

        test('handles missing tbody', () => {
            expect(() => handlers.renderReportTable([{ id: 1 }])).not.toThrow();
        });
    });

    // ── renderEmployeeReportChart ───────────────────────────────────
    describe('renderEmployeeReportChart', () => {
        test('does nothing when chart not initialized', () => {
            expect(() => handlers.renderEmployeeReportChart([])).not.toThrow();
        });

        test('updates chart series with monthly data', () => {
            const updateSeries = vi.fn();
            window.__employeeReportChart = { updateSeries };
            window.__employeeReportChartYear = 2026;

            handlers.renderEmployeeReportChart([
                { joinDate: '2026-01-15', employmentStatus: 'active' },
                { joinDate: '2026-03-20', employmentStatus: 'inactive' },
                { joinDate: '2026-03-10', employmentStatus: 'active' },
            ]);

            expect(updateSeries).toHaveBeenCalledOnce();
            const series = updateSeries.mock.calls[0][0];
            expect(series[0].name).toBe('Active Employees');
            expect(series[0].data[0]).toBe(1); // Jan
            expect(series[0].data[2]).toBe(1); // Mar
            expect(series[1].data[2]).toBe(1); // Mar inactive

            delete window.__employeeReportChart;
            delete window.__employeeReportChartYear;
        });

        test('filters by current year', () => {
            const updateSeries = vi.fn();
            window.__employeeReportChart = { updateSeries };

            handlers.renderEmployeeReportChart([
                { joinDate: '2025-12-01', employmentStatus: 'active' }, // wrong year
            ]);

            const series = updateSeries.mock.calls[0][0];
            expect(series[0].data.every(v => v === 0)).toBe(true);

            delete window.__employeeReportChart;
        });
    });

    // ── updateReportSummary ─────────────────────────────────────────
    describe('updateReportSummary', () => {
        function setupSummary() {
            document.body.innerHTML = `
                <span data-employee-report-total></span>
                <span data-employee-report-active></span>
                <span data-employee-report-inactive></span>
                <span data-employee-report-new></span>
            `;
        }

        test('updates all summary fields', () => {
            setupSummary();
            handlers.updateReportSummary({
                summary: { totalEmployees: 50, activeEmployees: 40, inactiveEmployees: 8, newJoiners: 2 },
            });
            expect(document.querySelector('[data-employee-report-total]').textContent).toBe('50');
            expect(document.querySelector('[data-employee-report-active]').textContent).toBe('40');
            expect(document.querySelector('[data-employee-report-inactive]').textContent).toBe('8');
            expect(document.querySelector('[data-employee-report-new]').textContent).toBe('2');
        });

        test('handles null meta', () => {
            setupSummary();
            expect(() => handlers.updateReportSummary(null)).not.toThrow();
        });

        test('falls back to alternate field names', () => {
            setupSummary();
            handlers.updateReportSummary({
                summary: { total: 10, total_active: 7, total_inactive: 2, total_pending: 1 },
            });
            expect(document.querySelector('[data-employee-report-total]').textContent).toBe('10');
            expect(document.querySelector('[data-employee-report-active]').textContent).toBe('7');
            expect(document.querySelector('[data-employee-report-inactive]').textContent).toBe('2');
            expect(document.querySelector('[data-employee-report-new]').textContent).toBe('1');
        });
    });

    // ── loadArchiveEmployeeReport ────────────────────────────────────
    describe('loadArchiveEmployeeReport', () => {
        beforeEach(() => {
            document.body.innerHTML = '<table><tbody data-employee-report-body></tbody></table>';
            document.body.innerHTML += '<span data-employee-report-total></span>';
        });

        test('shows error when snapshotId falsy', async () => {
            await handlers.loadArchiveEmployeeReport(0);
            const body = document.querySelector('[data-employee-report-body]');
            expect(body.innerHTML).toContain('Snapshot ID wajib diisi');
        });

        test('fetches and renders snapshot data', async () => {
            mockDeps.requestJson.mockResolvedValue({
                success: true,
                data: {
                    id: 1,
                    reportType: 'employee',
                    status: 'completed',
                    periodEnd: '2026-05-31',
                    dataByModule: {
                        employee: {
                            by_status: { active: { status: 'active', count: 5, percentage: 100 } },
                            summary: { totalEmployees: 5, activeEmployees: 5 },
                        },
                    },
                },
            });

            await handlers.loadArchiveEmployeeReport(1);
            const body = document.querySelector('[data-employee-report-body]');
            expect(body.innerHTML).toContain('EMP');
            expect(document.querySelector('[data-employee-report-total]').textContent).toBe('5');
        });

        test('handles API error', async () => {
            mockDeps.requestJson.mockRejectedValue({ status: 500, data: { error: { message: 'Server error' } } });

            await handlers.loadArchiveEmployeeReport(1);
            const body = document.querySelector('[data-employee-report-body]');
            expect(body.innerHTML).toContain('Server error');
        });
    });

    // ── loadEmployeeReportData ───────────────────────────────────────
    describe('loadEmployeeReportData', () => {
        beforeEach(() => {
            document.body.innerHTML = '<table><tbody data-employee-report-body></tbody></table>';
            document.body.innerHTML += '<span data-employee-report-total></span>';
        });

        test('returns early when not on /employee-report page', () => {
            handlers.loadEmployeeReportData();
        });

        test('loads live report data', async () => {
            vi.stubGlobal('location', { pathname: '/employee-report', search: '', hash: '' });

            mockDeps.requestAuthMe.mockResolvedValue({
                success: true,
                data: { permissions: { 'employee.view': true } },
            });
            mockDeps.requestAllEmployeesAggregated.mockResolvedValue({
                success: true,
                data: [{ id: 1, fullName: 'Alice', employmentStatus: 'active' }],
                meta: { summary: { totalEmployees: 1, activeEmployees: 1 } },
            });

            await handlers.loadEmployeeReportData();
            const body = document.querySelector('[data-employee-report-body]');
            expect(body.innerHTML).toContain('Alice');
            expect(document.querySelector('[data-employee-report-total]').textContent).toBe('1');

            vi.unstubAllGlobals();
        });
    });
});
