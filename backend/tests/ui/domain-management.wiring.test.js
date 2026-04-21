import { beforeAll, describe, expect, it, vi } from 'vitest';

beforeAll(async () => {
  document.body.innerHTML = `
    <div id="domainModal"></div>
    <div id="verificationModal"></div>
    <form id="domainForm"><button type="submit">Save</button></form>
    <button id="btn_add_domain"></button>
    <select id="filter_status"></select>
    <select id="filter_company"></select>
    <input id="search_domains" />
    <button id="btn_reset_filters"></button>
    <button id="btn_verify_domain"></button>
    <select id="input_domain_company"></select>
    <input id="input_domain_name" />
    <textarea id="input_domain_notes"></textarea>
    <input type="radio" name="verification_type" id="verification_dns" value="dns" />
    <input type="radio" name="verification_type" id="verification_file" value="file" />
    <div data-domains-list-container></div>
    <div id="verification_instructions"></div>
  `;

  window.bootstrap = {
    Modal: {
      getOrCreateInstance: () => ({ show() {}, hide() {} }),
    },
  };

  window.ArcavUi = {
    confirmDelete: vi.fn().mockResolvedValue(true),
  };

  global.fetch = vi.fn(async (url) => {
    if (String(url).includes('/api-token')) {
      return {
        ok: true,
        json: async () => ({ success: true, data: { token: 'test-token' } }),
      };
    }

    if (String(url).includes('/v1/company')) {
      return {
        ok: true,
        json: async () => ({
          success: true,
          data: {
            companies: [
              { id: 7, uuid: '550e8400-e29b-41d4-a716-446655440000', name: 'Acme Corp' },
            ],
          },
        }),
      };
    }

    if (String(url).includes('/v1/saas/domains')) {
      return {
        ok: true,
        json: async () => ({ success: true, data: [], pagination: { last_page: 1 } }),
      };
    }

    return {
      ok: true,
      json: async () => ({}),
    };
  });

  await import('../../../frontend/resources/js/domain-management.js');
});

describe('domain management wiring', () => {
  it('normalizes and validates host-only domain payloads', () => {
    expect(window.DomainManagementRules.normalizeDomainName(' HR-DEMO.Example.Com ')).toBe('hr-demo.example.com');
    expect(window.DomainManagementRules.validateDomainPayload({
      company_id: '550e8400-e29b-41d4-a716-446655440000',
      domain_name: 'https://bad.example.com/path',
      verification_type: 'dns',
    })).toContain('Domain name harus berupa host/domain valid tanpa http:// atau path.');
  });

  it('renders company options using company uuid so FE matches BE write contract', async () => {
    document.body.innerHTML = '<select id="input_domain_company"></select><select id="filter_company"></select>';
    window.DomainManager.companies = [
      { id: 7, uuid: '550e8400-e29b-41d4-a716-446655440000', name: 'Acme Corp' },
    ];
    window.DomainManager.renderCompanyOptions();
    const options = Array.from(document.querySelectorAll('#input_domain_company option')).map((option) => ({
      value: option.value,
      text: option.textContent,
    }));
    expect(options.some((option) => option.value === '550e8400-e29b-41d4-a716-446655440000')).toBe(true);
    expect(options.some((option) => option.text.includes('Acme Corp'))).toBe(true);
  });

  it('formats laravel validation errors into a user-facing message', () => {
    expect(window.DomainManagementRules.formatApiError({
      data: {
        errors: {
          domain_name: ['The domain name field format is invalid.'],
        },
      },
    }, 'fallback')).toBe('The domain name field format is invalid.');
  });
});