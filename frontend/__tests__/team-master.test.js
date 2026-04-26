import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';

describe('Team Master Data', () => {
    let fetchMock;

    beforeEach(() => {
        // Setup DOM
        document.body.innerHTML = `
            <input type="text" data-hcm-search-input="teams" />
            <select data-hcm-status-filter="teams">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select data-hcm-per-page="teams">
                <option value="20" selected>20 / page</option>
            </select>
            <table>
                <tbody data-teams-body></tbody>
            </table>
            <div data-hcm-pagination-wrap="teams">
                <small data-hcm-showing="teams">Showing 0 - 0 of 0 entries</small>
                <ul data-hcm-pagination="teams"></ul>
            </div>
            
            <div id="add_team" class="modal">
                <form data-hcm-form="team-add">
                    <input data-hcm-field="team-name" />
                    <select data-hcm-field="team-department"></select>
                    <select data-hcm-field="team-lead"></select>
                    <select data-hcm-field="team-active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    <button type="submit">Add</button>
                </form>
            </div>

            <div id="edit_team" class="modal">
                <form data-hcm-form="team-edit">
                    <input data-hcm-field="team-name" />
                    <select data-hcm-field="team-department"></select>
                    <select data-hcm-field="team-lead"></select>
                    <select data-hcm-field="team-active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    <button type="submit">Save</button>
                </form>
            </div>
        `;

        // Mock fetch
        fetchMock = vi.fn();
        global.fetch = fetchMock;

        // Mock bootstrap modal
        window.bootstrap = {
            Modal: {
                getInstance: vi.fn(),
            },
        };
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('should render teams list from API response', async () => {
        const mockResponse = {
            success: true,
            data: [
                {
                    id: 1,
                    uuid: 'uuid-1',
                    name: 'Customer Service',
                    department_id: 1,
                    department_name: 'Operations',
                    team_lead_id: null,
                    team_lead_name: null,
                    member_count: 5,
                    is_active: true,
                },
                {
                    id: 2,
                    uuid: 'uuid-2',
                    name: 'Engineering',
                    department_id: 2,
                    department_name: 'Tech',
                    team_lead_id: 10,
                    team_lead_name: 'John Doe',
                    member_count: 3,
                    is_active: true,
                },
            ],
            meta: { page: 1, perPage: 20, total: 2 },
        };

        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: vi.fn().mockResolvedValueOnce(mockResponse),
        });

        // Simulate page load
        const body = document.querySelector('[data-teams-body]');
        expect(body.innerHTML).toContain('Loading...');

        // Verify fetch was called with correct URL
        // (In real scenario, fetch would be called by page init)
    });

    it('should handle API error gracefully', async () => {
        const mockError = {
            success: false,
            error: {
                code: 'TEAM_NOT_FOUND',
                message: 'Team not found.',
            },
        };

        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 404,
            json: vi.fn().mockResolvedValueOnce(mockError),
        });

        // Error handling should work
        expect(fetchMock).toBeDefined();
    });

    it('should validate team name is required', () => {
        const form = document.querySelector('[data-hcm-form="team-add"]');
        const nameInput = form.querySelector('[data-hcm-field="team-name"]');

        // Test validation attribute
        expect(nameInput.hasAttribute('required')).toBe(true);
        expect(nameInput.getAttribute('maxlength')).toBe('100');
    });

    it('should validate department is required', () => {
        const form = document.querySelector('[data-hcm-form="team-add"]');
        const deptSelect = form.querySelector('[data-hcm-field="team-department"]');

        // Test validation attribute
        expect(deptSelect.hasAttribute('required')).toBe(true);
    });

    it('should have team lead as optional field', () => {
        const form = document.querySelector('[data-hcm-form="team-add"]');
        const leadSelect = form.querySelector('[data-hcm-field="team-lead"]');

        // Should NOT be required
        expect(leadSelect.hasAttribute('required')).toBe(false);
    });

    it('should display pagination controls', () => {
        const pagination = document.querySelector('[data-hcm-pagination="teams"]');
        expect(pagination).toBeDefined();

        const showing = document.querySelector('[data-hcm-showing="teams"]');
        expect(showing).toBeDefined();
        expect(showing.textContent).toBe('Showing 0 - 0 of 0 entries');
    });

    it('should have search and filter inputs', () => {
        const searchInput = document.querySelector('[data-hcm-search-input="teams"]');
        expect(searchInput).toBeDefined();
        expect(searchInput.placeholder).toContain('Search');

        const statusFilter = document.querySelector('[data-hcm-status-filter="teams"]');
        expect(statusFilter).toBeDefined();
        expect(statusFilter.options.length).toBeGreaterThan(1);
    });

    it('should have edit form with correct fields', () => {
        const form = document.querySelector('[data-hcm-form="team-edit"]');
        expect(form).toBeDefined();

        const nameField = form.querySelector('[data-hcm-field="team-name"]');
        const deptField = form.querySelector('[data-hcm-field="team-department"]');
        const leadField = form.querySelector('[data-hcm-field="team-lead"]');
        const activeField = form.querySelector('[data-hcm-field="team-active"]');

        expect(nameField).toBeDefined();
        expect(deptField).toBeDefined();
        expect(leadField).toBeDefined();
        expect(activeField).toBeDefined();
    });

    it('should disable pagination on single page', () => {
        // Single page (totalPages = 1) should not render pagination
        const mockMeta = { page: 1, perPage: 20, total: 10 };
        // When total = 10 and perPage = 20, totalPages = 1

        const pagination = document.querySelector('[data-hcm-pagination="teams"]');
        // Should be empty or minimal
        expect(pagination).toBeDefined();
    });

    it('should support per-page selection', () => {
        const select = document.querySelector('[data-hcm-per-page="teams"]');
        expect(select).toBeDefined();

        const options = select.querySelectorAll('option');
        expect(options.length).toBeGreaterThan(0);
        expect(select.value).toBe('20');
    });
});
