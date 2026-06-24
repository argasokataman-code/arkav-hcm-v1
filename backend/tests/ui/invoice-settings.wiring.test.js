import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async () => {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
};

const INVOICE_SETTINGS_DEFAULTS = {
  invoice_prefix: 'INV-',
  invoice_due_days: '30',
  invoice_round_off: 'none',
  invoice_round_off_enabled: '0',
  invoice_show_tax: '1',
  invoice_header_terms: 'Thank you for your business.',
  invoice_footer_terms: 'Payment due within stated days.',
  invoice_document_status_map: {
    billing_invoice: true,
    payroll_monthly: true,
    payroll_thr: false,
    payroll_pkwt_compensation: true,
  },
  invoice_documents: [
    {
      code: 'billing_invoice',
      name: 'Billing Invoice',
      template: 'pdf.invoice',
      total_generated: 2,
      latest_generated_at: '2026-04-25T06:00:00Z',
      preview_url: '/v1/saas/invoices/12/pdf',
      active: true,
    },
    {
      code: 'payroll_thr',
      name: 'THR Slip',
      template: 'pdf.thr-slip',
      total_generated: 1,
      latest_generated_at: '2026-04-25T05:00:00Z',
      preview_url: '/v1/hcm/payroll/thr-batch/lines/21/slip',
      active: false,
    },
  ],
};

function buildFixture() {
  return `
    <div class="alert d-none" data-invoice-settings-feedback></div>
    <div class="card" data-invoice-settings-loading>
      <div class="card-body"><span>Loading…</span></div>
    </div>
    <div class="card d-none" data-invoice-settings-panel>
      <div class="card-body">
        <form data-invoice-settings-form>
          <input type="text" data-invoice-field="invoice_prefix" data-invoice-settings-input />
          <select data-invoice-field="invoice_due_days" data-invoice-settings-input>
            <option value="7">7 Days</option>
            <option value="14">14 Days</option>
            <option value="30">30 Days</option>
            <option value="45">45 Days</option>
            <option value="60">60 Days</option>
            <option value="90">90 Days</option>
          </select>
          <select data-invoice-field="invoice_round_off" data-invoice-settings-input>
            <option value="none">No Rounding</option>
            <option value="round_up">Round Up</option>
            <option value="round_down">Round Down</option>
          </select>
          <input type="checkbox" id="invoiceRoundOffSwitch"
                 data-invoice-field="invoice_round_off_enabled"
                 data-invoice-settings-toggle />
          <input type="checkbox" id="invoiceShowTaxSwitch"
                 data-invoice-field="invoice_show_tax"
                 data-invoice-settings-toggle />
          <textarea data-invoice-field="invoice_header_terms" data-invoice-settings-input></textarea>
          <textarea data-invoice-field="invoice_footer_terms" data-invoice-settings-input></textarea>
          <button type="button" data-invoice-settings-reset>Reset</button>
          <button type="submit" data-invoice-settings-submit>
            <span data-invoice-settings-submit-label>Save Changes</span>
            <span class="d-none spinner-border spinner-border-sm" data-invoice-settings-spinner></span>
          </button>
        </form>
        <table>
          <tbody data-invoice-documents-list>
            <tr data-invoice-documents-empty>
              <td colspan="6">No invoice documents found yet for this tenant.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  `;
}

describe('invoice settings wiring', () => {
  beforeEach(() => {
    vi.resetModules();
    document.body.innerHTML = buildFixture();

    window.AuthApi = {
      getTenantContext: vi.fn(() => ({
        companyCode: 'test_company_001',
        companyId: 42,
        companyUuid: 'company-uuid-42',
      })),
      getToken: vi.fn(() => 'test-bearer-token'),
    };
    window.ArcavValidation = { validateForm: vi.fn().mockReturnValue(true) };
  });

  it('loads settings from API and populates form fields', async () => {
    global.fetch = vi.fn((url) => {
      if (url === '/v1/hcm/invoice-settings') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, data: INVOICE_SETTINGS_DEFAULTS }),
        });
      }
      return Promise.resolve({ ok: false, json: () => Promise.resolve({ success: false }) });
    });

    await import('../../../frontend/resources/js/invoice-settings-data.js');
    const loading = document.querySelector('[data-invoice-settings-loading]');
    expect(loading.classList.contains('d-none')).toBe(true);

    // Panel should be visible
    const panel = document.querySelector('[data-invoice-settings-panel]');
    expect(panel.classList.contains('d-none')).toBe(false);

    // Fields should be populated
    const prefixInput = document.querySelector('[data-invoice-field="invoice_prefix"]');
    expect(prefixInput.value).toBe('INV-');

    const dueDaysSelect = document.querySelector('[data-invoice-field="invoice_due_days"]');
    expect(dueDaysSelect.value).toBe('30');

    const showTaxToggle = document.querySelector('[data-invoice-field="invoice_show_tax"]');
    expect(showTaxToggle.checked).toBe(true);

    const roundOffToggle = document.querySelector('[data-invoice-field="invoice_round_off_enabled"]');
    expect(roundOffToggle.checked).toBe(false);

    const headerTerms = document.querySelector('[data-invoice-field="invoice_header_terms"]');
    expect(headerTerms.value).toBe('Thank you for your business.');

    const documentRows = document.querySelectorAll('[data-invoice-doc-row]');
    expect(documentRows.length).toBe(2);
    const thrToggle = document.querySelector('[data-invoice-doc-active][data-doc-code="payroll_thr"]');
    expect(thrToggle.checked).toBe(false);

    // No error feedback visible
    const feedbackEl = document.querySelector('[data-invoice-settings-feedback]');
    expect(feedbackEl.classList.contains('d-none')).toBe(true);
  });

  it('submits form and shows success feedback on 200 PUT', async () => {
    let capturedBody = null;

    global.fetch = vi.fn((url, opts) => {
      if (url === '/v1/hcm/invoice-settings' && opts && opts.method === 'GET') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, data: INVOICE_SETTINGS_DEFAULTS }),
        });
      }
      if (url === '/v1/hcm/invoice-settings' && opts && opts.method === 'PUT') {
        capturedBody = JSON.parse(opts.body);
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            message: 'Invoice settings saved.',
            data: Object.assign({}, INVOICE_SETTINGS_DEFAULTS, { invoice_prefix: 'BILL-' }),
          }),
        });
      }
      return Promise.resolve({ ok: false, json: () => Promise.resolve({ success: false }) });
    });

    await import('../../../frontend/resources/js/invoice-settings-data.js');
    await flush();
    await flush();

    // Change prefix and submit
    const prefixInput = document.querySelector('[data-invoice-field="invoice_prefix"]');
    prefixInput.value = 'BILL-';

    const thrToggle = document.querySelector('[data-invoice-doc-active][data-doc-code="payroll_thr"]');
    thrToggle.checked = true;

    const form = document.querySelector('[data-invoice-settings-form]');
    form.dispatchEvent(new Event('submit', { bubbles: true }));
    await flush();
    await flush();

    // PUT should have been called
    const putCall = global.fetch.mock.calls.find(
      ([u, o]) => u === '/v1/hcm/invoice-settings' && o && o.method === 'PUT'
    );
    expect(putCall).toBeTruthy();
    expect(capturedBody).toBeTruthy();
    expect(capturedBody.invoice_prefix).toBe('BILL-');
    expect(capturedBody.invoice_document_status_map).toBeTruthy();
    expect(capturedBody.invoice_document_status_map.payroll_thr).toBe(true);

    // Success feedback
    const feedbackEl = document.querySelector('[data-invoice-settings-feedback]');
    expect(feedbackEl.classList.contains('d-none')).toBe(false);
    expect(feedbackEl.classList.contains('alert-success')).toBe(true);
    expect(feedbackEl.textContent).toContain('saved');
  });

  it('shows forbidden warning when API returns 403', async () => {
    global.fetch = vi.fn((url) => {
      if (url === '/v1/hcm/invoice-settings') {
        return Promise.resolve({
          ok: false,
          status: 403,
          json: () => Promise.resolve({ success: false, error: { message: 'Forbidden.' } }),
        });
      }
      return Promise.resolve({ ok: false, json: () => Promise.resolve({ success: false }) });
    });

    await import('../../../frontend/resources/js/invoice-settings-data.js');
    await flush();
    await flush();

    // Loading hidden
    const loading = document.querySelector('[data-invoice-settings-loading]');
    expect(loading.classList.contains('d-none')).toBe(true);

    // Panel stays hidden
    const panel = document.querySelector('[data-invoice-settings-panel]');
    expect(panel.classList.contains('d-none')).toBe(true);

    // Warning feedback shown
    const feedbackEl = document.querySelector('[data-invoice-settings-feedback]');
    expect(feedbackEl.classList.contains('d-none')).toBe(false);
    expect(feedbackEl.classList.contains('alert-warning')).toBe(true);
    expect(feedbackEl.textContent).toContain('permission');
  });
});
