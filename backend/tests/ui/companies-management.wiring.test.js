import { beforeEach, describe, expect, it, vi } from 'vitest';

function okJson(data) {
  return {
    ok: true,
    status: 200,
    json: async () => data,
  };
}

async function flush(times = 8) {
  for (let i = 0; i < times; i += 1) {
    await Promise.resolve();
  }
}

describe('Companies management wiring', () => {
  beforeEach(() => {
    vi.resetModules();
    document.body.innerHTML = `
      <form id="add_company_form"></form>
      <form id="edit_company_form"></form>
      <input id="company_search" />
      <select id="status_filter"><option value="">All</option></select>
      <button id="companies_refresh" type="button"></button>
      <table><tbody id="companies_table_body"></tbody></table>
      <div id="companies_pagination"></div>
      <div id="companies_table_info"></div>
      <div id="companies_total_count"></div>
      <div id="companies_active_count"></div>
      <div id="companies_inactive_count"></div>
      <div id="companies_location_count"></div>
      <div id="add_company"></div>
      <div id="edit_company"></div>
      <div id="delete_modal"></div>
      <span id="delete_company_name"></span>
      <button id="delete_confirm_btn" type="button"></button>
      <input id="edit_company_id" />
      <input id="edit_company_code" />
      <input id="edit_company_name" />
      <input id="edit_company_legal_name" />
      <select id="edit_company_status"><option value="active">active</option></select>
      <input id="edit_company_timezone" />
      <input id="edit_company_currency" />
      <input id="edit_company_country" />
    `;

    window.bootstrap = {
      Modal: {
        getInstance: () => ({ hide: vi.fn() }),
      },
    };
  });

  it('renders explicit company code column and company support details', async () => {
    const fetchMock = vi.fn((url) => {
      if (url === '/api-token') {
        return Promise.resolve(okJson({ success: true, data: { token: 'token-1' } }));
      }

      if (String(url).startsWith('/v1/company?')) {
        return Promise.resolve(okJson({
          success: true,
          data: {
            companies: [
              {
                id: 12,
                uuid: 'comp-uuid-12',
                code: 'ARCAV-HQ',
                name: 'Arcav HQ',
                legal_name: 'Arcav Holdings',
                status: 'active',
                timezone: 'Asia/Jakarta',
                currency: 'IDR',
                country_code: 'ID',
                created_at: '2026-04-20T10:00:00Z',
                owner: {
                  id: 7,
                  name: 'Nadia Admin',
                  email: 'nadia@arcav.test',
                },
                subscription: {
                  planCode: 'enterprise',
                  status: 'active',
                  endsAt: '2026-05-20T10:00:00Z',
                },
              },
            ],
            pagination: {
              total: 1,
              per_page: 25,
              page: 1,
              last_page: 1,
            },
            stats: {
              totalCompanies: 1,
              activeCompanies: 1,
              inactiveCompanies: 0,
              locationCount: 1,
            },
          },
        }));
      }

      return Promise.resolve(okJson({ success: true, data: {} }));
    });

    vi.stubGlobal('fetch', fetchMock);

    await import('../../../frontend/resources/js/companies-management.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    const tableText = document.getElementById('companies_table_body')?.textContent || '';
    expect(tableText).toContain('ARCAV-HQ');
    expect(tableText).toContain('Nadia Admin');
    expect(tableText).toContain('nadia@arcav.test');
    expect(tableText).toContain('Arcav Holdings');

    const copyButton = document.querySelector('.btn-copy-code');
    expect(copyButton).toBeTruthy();

    const infoText = document.getElementById('companies_table_info')?.textContent || '';
    expect(infoText).toContain('Showing 1-1 of 1 companies.');
  });
});
