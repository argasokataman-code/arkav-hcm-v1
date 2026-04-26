import { beforeEach, describe, expect, it, vi } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

function extractInlineScript() {
  const bladePath = path.resolve(process.cwd(), 'resources/views/bussiness-settings.blade.php');
  const blade = fs.readFileSync(bladePath, 'utf8');
  const match = blade.match(/<script>([\s\S]*?)<\/script>\s*@endsection/);
  if (!match || !match[1]) {
    throw new Error('Business settings inline script not found');
  }
  return match[1];
}

function createMiniJQuery(win) {
  class MiniCollection {
    constructor(elements) {
      this.elements = elements || [];
    }

    each(callback) {
      this.elements.forEach((el, index) => {
        callback.call(el, index, el);
      });
      return this;
    }

    val(value) {
      if (value === undefined) {
        return this.elements[0]?.value ?? '';
      }
      this.elements.forEach((el) => {
        el.value = value;
      });
      return this;
    }

    data(key) {
      const el = this.elements[0];
      if (!el) return undefined;
      const normalized = String(key || '').replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      return el.dataset ? el.dataset[normalized] : undefined;
    }

    is(selector) {
      const el = this.elements[0];
      return !!(el && el.matches && el.matches(selector));
    }

    trigger(eventName) {
      this.elements.forEach((el) => {
        el.dispatchEvent(new win.Event(eventName, { bubbles: true, cancelable: true }));
      });
      return this;
    }

    on(eventName, handler) {
      this.elements.forEach((el) => {
        el.addEventListener(eventName, function (event) {
          handler.call(el, event);
        });
      });
      return this;
    }

    find(selector) {
      const found = this.elements.flatMap((el) => Array.from(el.querySelectorAll(selector)));
      return new MiniCollection(found);
    }

    prop(name, value) {
      if (value === undefined) {
        return this.elements[0]?.[name];
      }
      this.elements.forEach((el) => {
        el[name] = value;
      });
      return this;
    }

    text(value) {
      if (value === undefined) {
        return this.elements[0]?.textContent ?? '';
      }
      this.elements.forEach((el) => {
        el.textContent = value;
      });
      return this;
    }

    attr(name, value) {
      if (value === undefined) {
        return this.elements[0]?.getAttribute(name) ?? undefined;
      }
      this.elements.forEach((el) => {
        el.setAttribute(name, value);
      });
      return this;
    }
  }

  function $(selectorOrCallback) {
    if (typeof selectorOrCallback === 'function') {
      selectorOrCallback();
      return new MiniCollection([]);
    }

    if (selectorOrCallback instanceof MiniCollection) {
      return selectorOrCallback;
    }

    if (typeof selectorOrCallback === 'string') {
      return new MiniCollection(Array.from(win.document.querySelectorAll(selectorOrCallback)));
    }

    if (selectorOrCallback && selectorOrCallback.nodeType) {
      return new MiniCollection([selectorOrCallback]);
    }

    return new MiniCollection([]);
  }

  $.ajax = () => {
    throw new Error('$.ajax not mocked');
  };

  return $;
}

describe('business settings wiring', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    document.body.innerHTML = `
      <meta name="csrf-token" content="csrf-test-token" />
      <form id="businessForm">
        <input data-business="company_name" />
        <input data-business="email" />
        <input data-business="phone" />
        <input data-business="fax" />
        <input data-business="website" />
        <input data-business="address" />
        <select class="select" data-business="country">
          <option value="">Select</option>
          <option value="Indonesia">Indonesia</option>
        </select>
        <select class="select" data-business="state">
          <option value="">Select</option>
          <option value="DKI Jakarta">DKI Jakarta</option>
        </select>
        <select class="select" data-business="city">
          <option value="">Select</option>
          <option value="Jakarta Selatan">Jakarta Selatan</option>
        </select>
        <input data-business="postal_code" />
        <button type="submit">Save</button>
      </form>
      <img id="preview-white_logo" src="" />
      <img id="preview-dark_logo" src="" />
      <img id="preview-white_mini_logo" src="" />
      <img id="preview-dark_mini_logo" src="" />
      <img id="preview-favicon" src="" />
      <img id="preview-apple_icon" src="" />
    `;

    window.AuthApi = {
      getToken: vi.fn(() => 'token-business-test'),
    };

    window.alert = vi.fn();

    const $ = createMiniJQuery(window);
    window.$ = $;
    window.jQuery = $;

    localStorage.setItem('arcav_active_tenant', JSON.stringify({
      companyCode: 'tenant_company_code',
      companyId: 77,
    }));
  });

  it('loads business settings and submits normalized payload to settings endpoint', async () => {
    const $ = window.$;
    const ajaxMock = vi.fn((options) => {
      if (options.type === 'GET' && options.url === '/v1/hcm/settings?group=business') {
        options.success({
          success: true,
          data: {
            business_company_name: 'Arkav - Human Capital Management',
            business_email: null,
            business_phone: '08123456789',
            business_fax: null,
            business_website: null,
            business_address: 'Jl. Sentosa Abadi, Jakarta Selatan',
            business_country: 'Indonesia',
            business_state: 'DKI Jakarta',
            business_city: 'Jakarta Selatan',
            business_postal_code: '123876',
            business_white_logo_path: null,
            business_dark_logo_path: null,
            business_white_mini_logo_path: null,
            business_dark_mini_logo_path: null,
            business_favicon_path: null,
            business_apple_icon_path: null,
          },
        });
        return;
      }

      if (options.type === 'POST' && options.url === '/v1/hcm/settings') {
        options.success({
          success: true,
          message: 'saved',
        });
        return;
      }

      throw new Error('Unexpected ajax call: ' + options.type + ' ' + options.url);
    });

    $.ajax = ajaxMock;

    const script = extractInlineScript();
    window.eval(script);

    expect(ajaxMock).toHaveBeenCalledWith(expect.objectContaining({
      type: 'GET',
      url: '/v1/hcm/settings?group=business',
    }));

    expect($('[data-business="company_name"]').val()).toBe('Arkav - Human Capital Management');
    expect($('[data-business="address"]').val()).toBe('Jl. Sentosa Abadi, Jakarta Selatan');
    expect($('[data-business="postal_code"]').val()).toBe('123876');
    expect($('[data-business="country"]').val()).toBe('Indonesia');

    $('[data-business="company_name"]').val('Arkav Updated');
    $('[data-business="email"]').val('admin@arkav.test');
    $('[data-business="phone"]').val('');

    $('#businessForm').trigger('submit');

    const postCall = ajaxMock.mock.calls.find(([arg]) => arg.type === 'POST');
    expect(postCall).toBeTruthy();

    const requestBody = JSON.parse(postCall[0].data);
    expect(requestBody.group).toBe('business');
    expect(requestBody.settings).toMatchObject({
      company_name: 'Arkav Updated',
      email: 'admin@arkav.test',
      phone: null,
      fax: null,
      website: null,
      address: 'Jl. Sentosa Abadi, Jakarta Selatan',
      country: 'Indonesia',
      state: 'DKI Jakarta',
      city: 'Jakarta Selatan',
      postal_code: '123876',
    });
  });
});
