import { describe, test, expect, beforeEach, afterEach, vi } from 'vitest';

// list.js belum ada → import akan fail dengan module not found
// Ini TDD: test dulu, implementasi menyusul
import { makeListHandlers } from '../resources/js/employees/list';

describe('makeListHandlers', () => {
    let handlers;
    let state;
    let viewerContext;
    let selectedMap;

    beforeEach(() => {
        document.body.innerHTML = '';
        state = { page: 1, perPage: 20, search: '', status: '', departmentId: '', designationId: '', teamId: '', scope: '' };
        viewerContext = { isSpecialSuperAdminCode1: false };
        selectedMap = {};
        handlers = makeListHandlers({
            employeesTableState: state,
            employeesViewerContext: viewerContext,
            selectedEmployeeProfilesMap: selectedMap,
            selectedPreviewEmployeeId: null,
            getCurrentListUrl: () => '/employees',
        });
    });

    // ── isSpecialSuperAdminCode1 ──────────────────────────────────────
    describe('isSpecialSuperAdminCode1', () => {
        test('true when activeCompany.id === 1', () => {
            expect(handlers.isSpecialSuperAdminCode1(
                { hcmGlobalAdmin: true, activeCompany: { id: 1 }, id: 99 }
            )).toBe(true);
        });

        test('true when user.id === 1', () => {
            expect(handlers.isSpecialSuperAdminCode1(
                { hcmGlobalAdmin: true, activeCompany: { id: 42 }, id: 1 }
            )).toBe(true);
        });

        test('false when not global admin', () => {
            expect(handlers.isSpecialSuperAdminCode1(
                { hcmGlobalAdmin: false, activeCompany: { id: 42 }, id: 99 }
            )).toBe(false);
        });

        test('false when null', () => {
            expect(handlers.isSpecialSuperAdminCode1(null)).toBe(false);
        });

        test('false when undefined', () => {
            expect(handlers.isSpecialSuperAdminCode1(undefined)).toBe(false);
        });
    });

    // ── getSelectedEmployeeProfileIds ─────────────────────────────────
    describe('getSelectedEmployeeProfileIds', () => {
        test('returns numeric ids from map with true values', () => {
            selectedMap['1'] = true;
            selectedMap['2'] = true;
            selectedMap['3'] = false;
            expect(handlers.getSelectedEmployeeProfileIds()).toEqual([1, 2]);
        });

        test('returns empty array when map empty', () => {
            expect(handlers.getSelectedEmployeeProfileIds()).toEqual([]);
        });

        test('filters out non-numeric ids', () => {
            selectedMap['abc'] = true;
            selectedMap['0'] = true;
            selectedMap['-5'] = true;
            expect(handlers.getSelectedEmployeeProfileIds()).toEqual([]);
        });
    });

    // ── syncSelectAllCheckboxState ────────────────────────────────────
    describe('syncSelectAllCheckboxState', () => {
        function setup(checkedCount, total) {
            const rows = Array.from({ length: total }, (_, i) =>
                `<input data-employees-select data-employee-profile-id="${i + 1}" ${i < checkedCount ? 'checked' : ''}>`
            ).join('');
            document.body.innerHTML = `<input data-employees-select-all type="checkbox">${rows}`;
        }

        test('checks select-all when all rows selected', () => {
            setup(3, 3);
            handlers.syncSelectAllCheckboxState();
            const el = document.querySelector('[data-employees-select-all]');
            expect(el.checked).toBe(true);
            expect(el.indeterminate).toBe(false);
        });

        test('indeterminate when partial selected', () => {
            setup(2, 4);
            handlers.syncSelectAllCheckboxState();
            const el = document.querySelector('[data-employees-select-all]');
            expect(el.checked).toBe(false);
            expect(el.indeterminate).toBe(true);
        });

        test('unchecked when none selected', () => {
            setup(0, 3);
            handlers.syncSelectAllCheckboxState();
            const el = document.querySelector('[data-employees-select-all]');
            expect(el.checked).toBe(false);
            expect(el.indeterminate).toBe(false);
        });

        test('handles no row checkboxes', () => {
            document.body.innerHTML = '<input data-employees-select-all type="checkbox">';
            handlers.syncSelectAllCheckboxState();
            const el = document.querySelector('[data-employees-select-all]');
            expect(el.checked).toBe(false);
            expect(el.indeterminate).toBe(false);
        });
    });

    // ── updateBulkSelectionUi ────────────────────────────────────────
    describe('updateBulkSelectionUi', () => {
        test('shows count and enables button when selected', () => {
            document.body.innerHTML = `
                <span data-employees-selected-count></span>
                <button data-employees-bulk-reassign-open></button>
            `;
            selectedMap['1'] = true;
            selectedMap['2'] = true;
            handlers.updateBulkSelectionUi();
            expect(document.querySelector('[data-employees-selected-count]').textContent).toBe('2');
            expect(document.querySelector('[data-employees-bulk-reassign-open]').disabled).toBe(false);
        });

        test('shows 0 and disables button when none selected', () => {
            document.body.innerHTML = `
                <span data-employees-selected-count></span>
                <button data-employees-bulk-reassign-open></button>
            `;
            handlers.updateBulkSelectionUi();
            expect(document.querySelector('[data-employees-selected-count]').textContent).toBe('0');
            expect(document.querySelector('[data-employees-bulk-reassign-open]').disabled).toBe(true);
        });

        test('handles missing DOM elements', () => {
            expect(() => handlers.updateBulkSelectionUi()).not.toThrow();
        });
    });

    // ── clearSelectedEmployeesSelection ──────────────────────────────
    describe('clearSelectedEmployeesSelection', () => {
        test('clears map and unchecks checkboxes', () => {
            document.body.innerHTML = `
                <input data-employees-select-all type="checkbox" checked>
                <input data-employees-select data-employee-profile-id="1" checked>
                <input data-employees-select data-employee-profile-id="2" checked>
            `;
            selectedMap['1'] = true;
            selectedMap['2'] = true;
            handlers.clearSelectedEmployeesSelection();
            expect(Object.keys(selectedMap)).toHaveLength(0);
            document.querySelectorAll('[data-employees-select]').forEach(el => {
                expect(el.checked).toBe(false);
            });
        });
    });

    // ── renderGridMessage ────────────────────────────────────────────
    describe('renderGridMessage', () => {
        test('renders message in grid body', () => {
            document.body.innerHTML = '<div data-employees-grid-body></div>';
            handlers.renderGridMessage('No employees found');
            const el = document.querySelector('[data-employees-grid-body]');
            expect(el.innerHTML).toContain('No employees found');
            expect(el.getAttribute('data-hydrated')).toBe('1');
        });

        test('does nothing when grid body missing', () => {
            expect(() => handlers.renderGridMessage('test')).not.toThrow();
        });

        test('escapes HTML in message', () => {
            document.body.innerHTML = '<div data-employees-grid-body></div>';
            handlers.renderGridMessage('<script>alert("xss")</script>');
            const el = document.querySelector('[data-employees-grid-body]');
            expect(el.innerHTML).toContain('&lt;script&gt;');
            expect(el.innerHTML).not.toContain('<script>');
        });
    });

    // ── updateSummary ────────────────────────────────────────────────
    describe('updateSummary', () => {
        function setupSummary() {
            document.body.innerHTML = `
                <span data-employees-total></span>
                <span data-employees-active></span>
                <span data-employees-inactive></span>
                <span data-employees-new-joiners></span>
            `;
        }

        test('updates all summary elements', () => {
            setupSummary();
            handlers.updateSummary({
                summary: { totalEmployees: 100, activeEmployees: 80, inactiveEmployees: 15, newJoiners: 5 }
            });
            expect(document.querySelector('[data-employees-total]').textContent).toBe('100');
            expect(document.querySelector('[data-employees-active]').textContent).toBe('80');
            expect(document.querySelector('[data-employees-inactive]').textContent).toBe('15');
            expect(document.querySelector('[data-employees-new-joiners]').textContent).toBe('5');
        });

        test('handles empty meta', () => {
            setupSummary();
            handlers.updateSummary({ summary: {} });
            expect(document.querySelector('[data-employees-total]').textContent).toBe('0');
        });

        test('handles null meta', () => {
            setupSummary();
            handlers.updateSummary({});
            expect(document.querySelector('[data-employees-total]').textContent).toBe('0');
        });

        test('handles missing DOM elements', () => {
            expect(() => handlers.updateSummary({ summary: { totalEmployees: 5 } })).not.toThrow();
        });
    });

    // ── renderEmployeesShowing ───────────────────────────────────────
    describe('renderEmployeesShowing', () => {
        test('renders correct range', () => {
            document.body.innerHTML = '<span data-employees-showing></span>';
            handlers.renderEmployeesShowing({ total: 50, page: 2, perPage: 10 }, 10);
            expect(document.querySelector('[data-employees-showing]').textContent)
                .toContain('11 - 20 of 50');
        });

        test('shows zeros when no data', () => {
            document.body.innerHTML = '<span data-employees-showing></span>';
            handlers.renderEmployeesShowing({ total: 0, page: 1, perPage: 20 }, 0);
            expect(document.querySelector('[data-employees-showing]').textContent)
                .toContain('0 - 0 of 0');
        });

        test('handles first page edge', () => {
            document.body.innerHTML = '<span data-employees-showing></span>';
            handlers.renderEmployeesShowing({ total: 5, page: 1, perPage: 10 }, 5);
            expect(document.querySelector('[data-employees-showing]').textContent)
                .toContain('1 - 5 of 5');
        });

        test('handles no showing element', () => {
            expect(() => handlers.renderEmployeesShowing({ total: 10, page: 1, perPage: 10 }, 10))
                .not.toThrow();
        });
    });

    // ── renderEmployeesPagination ────────────────────────────────────
    describe('renderEmployeesPagination', () => {
        test('renders pagination for multi-page', () => {
            document.body.innerHTML = '<ul data-employees-pagination></ul>';
            handlers.renderEmployeesPagination({ total: 100, page: 3, perPage: 10 });
            const html = document.querySelector('[data-employees-pagination]').innerHTML;
            expect(html).toContain('data-employees-page="2"');
            expect(html).toContain('data-employees-page="3"');
            expect(html).toContain('data-employees-page="4"');
            expect(html).toContain('active');
        });

        test('renders nothing for single page', () => {
            document.body.innerHTML = '<ul data-employees-pagination></ul>';
            handlers.renderEmployeesPagination({ total: 5, page: 1, perPage: 20 });
            expect(document.querySelector('[data-employees-pagination]').innerHTML).toBe('');
        });

        test('disables prev on first page', () => {
            document.body.innerHTML = '<ul data-employees-pagination></ul>';
            handlers.renderEmployeesPagination({ total: 50, page: 1, perPage: 10 });
            expect(document.querySelector('[data-employees-pagination]').innerHTML).toContain('disabled');
        });

        test('disables next on last page', () => {
            document.body.innerHTML = '<ul data-employees-pagination></ul>';
            handlers.renderEmployeesPagination({ total: 50, page: 5, perPage: 10 });
            expect(document.querySelector('[data-employees-pagination]').innerHTML).toContain('disabled');
        });

        test('handles missing pagination element', () => {
            expect(() => handlers.renderEmployeesPagination({ total: 50, page: 1, perPage: 10 }))
                .not.toThrow();
        });
    });

    // ── exportEmployees ──────────────────────────────────────────────
    describe('exportEmployees', () => {
        let originalLocation;

        beforeEach(() => {
            originalLocation = window.location;
            delete window.location;
            window.location = { assign: vi.fn() };
        });

        afterEach(() => {
            window.location = originalLocation;
        });

        test('redirects with state params for xlsx', () => {
            state.search = 'alice';
            state.status = 'active';
            state.departmentId = '5';
            handlers.exportEmployees('xlsx');
            const url = window.location.assign.mock.calls[0][0];
            expect(url).toContain('/v1/hcm/employees/export');
            expect(url).toContain('format=xlsx');
            expect(url).toContain('search=alice');
            expect(url).toContain('status=active');
            expect(url).toContain('departmentId=5');
        });

        test('redirects with pdf format', () => {
            handlers.exportEmployees('pdf');
            const url = window.location.assign.mock.calls[0][0];
            expect(url).toContain('format=pdf');
        });

        test('defaults to xlsx when format unknown', () => {
            handlers.exportEmployees('csv');
            const url = window.location.assign.mock.calls[0][0];
            expect(url).toContain('format=xlsx');
        });

        test('omits empty params', () => {
            handlers.exportEmployees('xlsx');
            const url = window.location.assign.mock.calls[0][0];
            expect(url).not.toContain('search=');
            expect(url).not.toContain('status=');
        });

        test('includes scope when set', () => {
            state.scope = 'global';
            handlers.exportEmployees('xlsx');
            const url = window.location.assign.mock.calls[0][0];
            expect(url).toContain('scope=global');
        });
    });

    // ── syncEmployeesScopeTabState ───────────────────────────────────
    describe('syncEmployeesScopeTabState', () => {
        test('activates matching tab, deactivates others', () => {
            document.body.innerHTML = `
                <button data-employees-scope-tab="global">Global</button>
                <button data-employees-scope-tab="active_company" class="active">Company</button>
            `;
            handlers.syncEmployeesScopeTabState('global');
            const tabs = document.querySelectorAll('[data-employees-scope-tab]');
            expect(tabs[0].classList.contains('active')).toBe(true);
            expect(tabs[0].getAttribute('aria-pressed')).toBe('true');
            expect(tabs[1].classList.contains('active')).toBe(false);
            expect(tabs[1].getAttribute('aria-pressed')).toBe('false');
        });

        test('handles no tabs', () => {
            expect(() => handlers.syncEmployeesScopeTabState('global')).not.toThrow();
        });

        test('handles missing data attribute', () => {
            document.body.innerHTML = '<button>No attr</button>';
            const tabs = document.querySelectorAll('[data-employees-scope-tab]');
            expect(tabs.length).toBe(0);
        });
    });

    // ── syncEmployeesFilterOptions ───────────────────────────────────
    describe('syncEmployeesFilterOptions', () => {
        test('populates all three selects', () => {
            document.body.innerHTML = `
                <select data-employees-filter-department></select>
                <select data-employees-filter-designation></select>
                <select data-employees-filter-team></select>
            `;
            handlers.syncEmployeesFilterOptions(
                [{ id: 1, name: 'Eng' }, { id: 2, name: 'Mkt' }],
                [{ id: 10, name: 'Engineer' }],
                [{ id: 20, name: 'Alpha' }, { id: 21, name: 'Beta' }],
            );
            expect(document.querySelector('[data-employees-filter-department]').options.length).toBe(3);
            expect(document.querySelector('[data-employees-filter-designation]').options.length).toBe(2);
            expect(document.querySelector('[data-employees-filter-team]').options.length).toBe(3);
        });

        test('preserves selected department from state', () => {
            state.departmentId = '2';
            document.body.innerHTML = `
                <select data-employees-filter-department>
                    <option value="">All</option>
                    <option value="1">Eng</option>
                    <option value="2">Mkt</option>
                </select>
            `;
            handlers.syncEmployeesFilterOptions(
                [{ id: 1, name: 'Eng' }, { id: 2, name: 'Mkt' }], [], []
            );
            expect(document.querySelector('[data-employees-filter-department]').value).toBe('2');
        });

        test('handles missing selects', () => {
            expect(() => handlers.syncEmployeesFilterOptions([{ id: 1, name: 'A' }], [], []))
                .not.toThrow();
        });
    });

    // ── updateActiveRowHighlight ─────────────────────────────────────
    describe('updateActiveRowHighlight', () => {
        test('highlights selected row', () => {
            document.body.innerHTML = `
                <table><tbody>
                    <tr data-employees-row-preview="42"></tr>
                    <tr data-employees-row-preview="99"></tr>
                </tbody></table>
            `;
            handlers.updateActiveRowHighlight('42');
            const rows = document.querySelectorAll('[data-employees-row-preview]');
            expect(rows[0].classList.contains('table-primary')).toBe(true);
            expect(rows[1].classList.contains('table-primary')).toBe(false);
        });

        test('removes highlight when no selection', () => {
            document.body.innerHTML = `
                <table><tbody>
                    <tr data-employees-row-preview="42" class="table-primary"></tr>
                </tbody></table>
            `;
            handlers.updateActiveRowHighlight(null);
            expect(document.querySelector('[data-employees-row-preview]').classList
                .contains('table-primary')).toBe(false);
        });

        test('handles no rows', () => {
            expect(() => handlers.updateActiveRowHighlight(null)).not.toThrow();
        });
    });

    // ── applyEmployeesScopeTabs ──────────────────────────────────────
    describe('applyEmployeesScopeTabs', () => {
        function setupTabs() {
            document.body.innerHTML = `
                <div data-employees-scope-tabs-wrap class="d-none">
                    <button data-employees-scope-tab="global">Global</button>
                    <button data-employees-scope-tab="active_company">Company</button>
                </div>
            `;
        }

        test('shows tabs and sets scope for special super admin', () => {
            setupTabs();
            const me = { hcmGlobalAdmin: true, activeCompany: { id: 1 }, id: 99 };
            handlers.applyEmployeesScopeTabs(me);
            const wrap = document.querySelector('[data-employees-scope-tabs-wrap]');
            expect(wrap.classList.contains('d-none')).toBe(false);
            expect(viewerContext.isSpecialSuperAdminCode1).toBe(true);
        });

        test('hides tabs and clears scope for normal admin', () => {
            setupTabs();
            const me = { hcmGlobalAdmin: false, activeCompany: { id: 42 }, id: 99 };
            handlers.applyEmployeesScopeTabs(me);
            const wrap = document.querySelector('[data-employees-scope-tabs-wrap]');
            expect(wrap.classList.contains('d-none')).toBe(true);
            expect(state.scope).toBe('');
        });

        test('handles missing wrap element', () => {
            const me = { hcmGlobalAdmin: true, activeCompany: { id: 1 }, id: 99 };
            expect(() => handlers.applyEmployeesScopeTabs(me)).not.toThrow();
        });
    });

    // ── saveReturnState / restoreReturnStateIfAny ────────────────────
    describe('saveReturnState', () => {
        test('saves to sessionStorage', () => {
            const setItem = vi.fn();
            vi.stubGlobal('sessionStorage', { getItem: vi.fn(), setItem: setItem });
            handlers.saveReturnState(42);
            expect(setItem).toHaveBeenCalledOnce();
            const val = JSON.parse(setItem.mock.calls[0][1]);
            expect(val.selectedId).toBe('42');
            expect(val.url).toBe('/employees');
            vi.unstubAllGlobals();
        });
    });

    describe('restoreReturnStateIfAny', () => {
        beforeEach(() => {
            vi.stubGlobal('sessionStorage', { getItem: vi.fn(), setItem: vi.fn() });
        });

        afterEach(() => {
            vi.unstubAllGlobals();
        });

        test('restores selectedPreviewEmployeeId when url matches', () => {
            const data = JSON.stringify({ url: '/employees', scrollY: 100, selectedId: '42' });
            window.sessionStorage.getItem.mockReturnValue(data);
            handlers.restoreReturnStateIfAny();
            expect(handlers.getSelectedPreviewEmployeeId()).toBe('42');
        });

        test('does nothing when url differs', () => {
            const data = JSON.stringify({ url: '/other', scrollY: 0, selectedId: '1' });
            window.sessionStorage.getItem.mockReturnValue(data);
            handlers.restoreReturnStateIfAny();
            expect(handlers.getSelectedPreviewEmployeeId()).toBeNull();
        });

        test('handles missing sessionStorage data', () => {
            window.sessionStorage.getItem.mockReturnValue(null);
            expect(() => handlers.restoreReturnStateIfAny()).not.toThrow();
        });
    });
});
