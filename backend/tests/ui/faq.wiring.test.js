import { beforeEach, describe, expect, it, vi } from 'vitest';
import { JSDOM } from 'jsdom';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function loadScript(dom, relPath) {
    const resolvedPath = relPath === 'faq-data.js'
        ? 'documents/faq-data.js'
        : relPath;
    const code = readFileSync(resolve(__dirname, '../../../frontend/resources/js/' + resolvedPath), 'utf8');
    const scriptEl = dom.window.document.createElement('script');
    scriptEl.textContent = code;
    dom.window.document.body.appendChild(scriptEl);
}

function flush() {
    return new Promise(function (resolvePromise) {
        setTimeout(resolvePromise, 0);
    });
}

function baseMarkup() {
    return `<!DOCTYPE html><html><body>
        <h3 id="faq-total-count">0</h3>
        <h3 id="faq-category-count">0</h3>
        <h5 id="faq-last-updated">-</h5>
        <input id="faq-search-input" type="text">
        <select id="faq-category-filter"><option value="all">All Categories</option></select>
        <select id="faq-sort-select">
            <option value="recent">Recent Update</option>
            <option value="az">Question A-Z</option>
            <option value="category">Category</option>
        </select>
        <button id="faq-reset-filters" type="button"></button>
        <div id="faq-selection-toolbar" class="d-none">
            <span id="faq-selected-count"></span>
            <button id="faq-delete-selected" type="button"></button>
        </div>
        <table>
            <tbody id="faq-table-body"></tbody>
        </table>
        <input id="select-all" type="checkbox">
        <a id="faq-export-csv"></a>
        <a id="faq-export-json"></a>

        <div id="add_faq" class="modal">
            <form id="faq-add-form">
                <input id="faq-add-category">
                <textarea id="faq-add-question"></textarea>
                <textarea id="faq-add-answer"></textarea>
                <div id="faq-add-error" class="d-none"></div>
                <button id="faq-add-submit" type="submit">Add</button>
            </form>
        </div>

        <div id="edit_faq" class="modal">
            <form id="faq-edit-form">
                <input id="faq-edit-id">
                <input id="faq-edit-category">
                <textarea id="faq-edit-question"></textarea>
                <textarea id="faq-edit-answer"></textarea>
                <div id="faq-edit-error" class="d-none"></div>
                <button id="faq-edit-submit" type="submit">Save</button>
            </form>
        </div>

        <div id="delete_modal" class="modal">
            <p id="faq-delete-message"></p>
            <button id="faq-confirm-delete" type="button">Delete</button>
        </div>
    </body></html>`;
}

describe('faq-data.js', function () {
    let dom;
    let hiddenModalMock;
    let entries;

    function jsonResponse(payload, status) {
        return Promise.resolve({
            ok: status >= 200 && status < 300,
            status,
            json: function () {
                return Promise.resolve(payload);
            },
        });
    }

    function installFetchMock(window) {
        window.fetch = vi.fn(function (url, options) {
            const method = (options && options.method ? options.method : 'GET').toUpperCase();
            const body = options && options.body ? JSON.parse(options.body) : null;

            if (url === '/v1/hcm/faqs' && method === 'GET') {
                return jsonResponse({ success: true, data: entries.slice() }, 200);
            }

            if (url === '/v1/hcm/faqs' && method === 'POST') {
                const id = entries.length ? Math.max.apply(null, entries.map(function (entry) { return entry.id; })) + 1 : 1;
                const now = '2026-03-01T10:00:00.000Z';
                const created = {
                    id,
                    uuid: 'uuid-' + String(id),
                    category: body.category,
                    question: body.question,
                    answer: body.answer,
                    createdBy: 1,
                    updatedBy: 1,
                    createdAt: now,
                    updatedAt: now,
                };
                entries = [created].concat(entries);
                return jsonResponse({ success: true, data: created }, 201);
            }

            if (url.indexOf('/v1/hcm/faqs/') === 0 && method === 'PUT') {
                const id = Number(url.split('/').pop());
                entries = entries.map(function (entry) {
                    if (entry.id !== id) {
                        return entry;
                    }
                    return {
                        id: entry.id,
                        uuid: entry.uuid,
                        category: body.category,
                        question: body.question,
                        answer: body.answer,
                        createdBy: entry.createdBy,
                        updatedBy: 1,
                        createdAt: entry.createdAt,
                        updatedAt: '2026-03-02T12:00:00.000Z',
                    };
                });

                const updated = entries.find(function (entry) {
                    return entry.id === id;
                });

                return jsonResponse({ success: true, data: updated }, 200);
            }

            if (url.indexOf('/v1/hcm/faqs/') === 0 && method === 'DELETE') {
                const id = Number(url.split('/').pop());
                entries = entries.filter(function (entry) {
                    return entry.id !== id;
                });
                return jsonResponse({ success: true }, 200);
            }

            if (url === '/v1/hcm/faqs/bulk-delete' && method === 'POST') {
                const idSet = {};
                (body.ids || []).forEach(function (id) {
                    idSet[Number(id)] = true;
                });
                entries = entries.filter(function (entry) {
                    return !idSet[entry.id];
                });
                return jsonResponse({ success: true, data: { deletedCount: Object.keys(idSet).length } }, 200);
            }

            return jsonResponse({ success: false, error: { message: 'Unhandled route in test mock' } }, 404);
        });
    }

    beforeEach(function () {
        dom = new JSDOM(baseMarkup(), { runScripts: 'dangerously', resources: 'usable', url: 'http://localhost/faq' });
        hiddenModalMock = { hide: vi.fn() };
        entries = [
            {
                id: 1,
                uuid: 'faq-1',
                category: 'General',
                question: 'What is an HRMS?',
                answer: 'An HRMS is a software system that helps teams centralize employee records.',
                createdBy: 1,
                updatedBy: 1,
                createdAt: '2026-01-10T08:30:00.000Z',
                updatedAt: '2026-01-10T08:30:00.000Z',
            },
            {
                id: 2,
                uuid: 'faq-2',
                category: 'Payroll',
                question: 'How do I process payroll in the workspace?',
                answer: 'Review payroll inputs and execute the payroll run after validation.',
                createdBy: 1,
                updatedBy: 1,
                createdAt: '2026-01-22T07:45:00.000Z',
                updatedAt: '2026-01-22T07:45:00.000Z',
            },
            {
                id: 3,
                uuid: 'faq-3',
                category: 'Employee',
                question: 'How do I add a new employee profile?',
                answer: 'Open employee module and complete profile fields before saving.',
                createdBy: 1,
                updatedBy: 1,
                createdAt: '2026-01-20T14:20:00.000Z',
                updatedAt: '2026-01-20T14:20:00.000Z',
            },
        ];

        dom.window.bootstrap = {
            Modal: {
                getInstance: vi.fn().mockReturnValue(hiddenModalMock),
                getOrCreateInstance: vi.fn().mockReturnValue(hiddenModalMock),
            },
        };

        dom.window.AuthApi = {
            getToken: vi.fn(() => null),
            getTenantContext: vi.fn().mockReturnValue({
                companyId: 77,
                companyCode: 'arcav',
                companyUuid: 'company-uuid-77',
            }),
        };

        dom.window.ArcavValidation = { validateForm: vi.fn().mockReturnValue(true) };
        installFetchMock(dom.window);

        dom.window.URL.createObjectURL = vi.fn().mockReturnValue('blob:test');
        dom.window.URL.revokeObjectURL = vi.fn();
    });

    it('loads FAQ entries from API and renders initial rows', async function () {
        loadScript(dom, 'faq-data.js');
        await flush();
        await flush();

        const rows = dom.window.document.querySelectorAll('#faq-table-body tr');
        expect(rows.length).toBe(3);
        expect(dom.window.document.getElementById('faq-total-count').textContent).toBe('3');
        expect(dom.window.document.getElementById('faq-category-count').textContent).toBe('3');
        expect(dom.window.fetch).toHaveBeenCalledWith('/v1/hcm/faqs', expect.objectContaining({ method: 'GET' }));
    });

    it('adds a new FAQ entry through API and re-renders table', async function () {
        loadScript(dom, 'faq-data.js');
        await flush();
        await flush();

        dom.window.document.getElementById('faq-add-category').value = 'Security';
        dom.window.document.getElementById('faq-add-question').value = 'How is access controlled?';
        dom.window.document.getElementById('faq-add-answer').value = 'Access is controlled by role checks and tenant context validation.';
        dom.window.document.getElementById('faq-add-form').dispatchEvent(new dom.window.Event('submit', { bubbles: true, cancelable: true }));
        await flush();
        await flush();

        expect(entries.length).toBe(4);
        expect(entries[0].category).toBe('Security');
        expect(dom.window.document.getElementById('faq-table-body').textContent).toContain('How is access controlled?');
        expect(hiddenModalMock.hide).toHaveBeenCalled();
        expect(dom.window.fetch).toHaveBeenCalledWith('/v1/hcm/faqs', expect.objectContaining({ method: 'POST' }));
    });

    it('filters rows by search query and category', async function () {
        loadScript(dom, 'faq-data.js');
        await flush();
        await flush();

        const search = dom.window.document.getElementById('faq-search-input');
        search.value = 'execute the payroll run';
        search.dispatchEvent(new dom.window.Event('input', { bubbles: true }));
        await flush();

        expect(dom.window.document.getElementById('faq-table-body').textContent).toContain('process payroll');
        expect(dom.window.document.querySelectorAll('#faq-table-body tr').length).toBe(1);

        const reset = dom.window.document.getElementById('faq-reset-filters');
        reset.click();
        await flush();

        const categoryFilter = dom.window.document.getElementById('faq-category-filter');
        categoryFilter.value = 'Employee';
        categoryFilter.dispatchEvent(new dom.window.Event('change', { bubbles: true }));
        await flush();

        expect(dom.window.document.getElementById('faq-table-body').textContent).toContain('add a new employee profile');
        expect(dom.window.document.querySelectorAll('#faq-table-body tr').length).toBe(1);
    });

    it('deletes a selected FAQ entry after confirmation', async function () {
        loadScript(dom, 'faq-data.js');
        await flush();
        await flush();

        const beforeText = dom.window.document.getElementById('faq-table-body').textContent;
        const deleteTrigger = dom.window.document.querySelector('tr[data-faq-id="1"] .faq-delete-trigger');
        deleteTrigger.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true }));
        dom.window.document.getElementById('faq-confirm-delete').click();
        await flush();
        await flush();

        expect(entries.length).toBe(2);
        expect(beforeText).toContain('What is an HRMS?');
        expect(dom.window.document.getElementById('faq-table-body').textContent).not.toContain('What is an HRMS?');
        expect(dom.window.fetch).toHaveBeenCalledWith('/v1/hcm/faqs/1', expect.objectContaining({ method: 'DELETE' }));
    });
});