import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async function () {
  await Promise.resolve();
  await new Promise(function (resolve) { setTimeout(resolve, 0); });
};

function createJQueryStub(selector) {
  let elements = [];

  if (typeof selector === 'string') {
    elements = Array.from(document.querySelectorAll(selector));
  } else if (selector instanceof Element) {
    elements = [selector];
  } else if (Array.isArray(selector)) {
    elements = selector;
  }

  return {
    length: elements.length,
    on(eventName, selectorOrHandler, maybeHandler) {
      const handler = typeof selectorOrHandler === 'function' ? selectorOrHandler : maybeHandler;
      elements.forEach(function (element) {
        element.addEventListener(eventName, handler);
      });
      return this;
    },
    append(html) {
      elements.forEach(function (element) {
        element.insertAdjacentHTML('beforeend', html);
      });
      return this;
    },
    addClass(name) {
      elements.forEach(function (element) {
        element.classList.add.apply(element.classList, name.split(' '));
      });
      return this;
    },
    removeClass(name) {
      elements.forEach(function (element) {
        element.classList.remove.apply(element.classList, name.split(' '));
      });
      return this;
    },
    prop(name, value) {
      elements.forEach(function (element) {
        element[name] = value;
      });
      return this;
    },
    html(value) {
      if (value === undefined) {
        return elements[0] ? elements[0].innerHTML : undefined;
      }
      elements.forEach(function (element) {
        element.innerHTML = value;
      });
      return this;
    },
    text(value) {
      if (value === undefined) {
        return elements[0] ? elements[0].textContent : undefined;
      }
      elements.forEach(function (element) {
        element.textContent = value;
      });
      return this;
    },
    attr(name) {
      return elements[0] ? elements[0].getAttribute(name) : undefined;
    },
    val(value) {
      if (value === undefined) {
        return elements[0] ? elements[0].value : undefined;
      }
      elements.forEach(function (element) {
        element.value = value;
      });
      return this;
    },
    toggle() {
      return this;
    },
    toggleClass(name, force) {
      elements.forEach(function (element) {
        element.classList.toggle(name, force);
      });
      return this;
    },
    slideToggle() {
      return this;
    },
    each(callback) {
      elements.forEach(function (element, index) {
        callback.call(element, index, element);
      });
      return this;
    },
    find(innerSelector) {
      const found = elements.reduce(function (carry, element) {
        return carry.concat(Array.from(element.querySelectorAll(innerSelector)));
      }, []);
      return createJQueryStub(found);
    },
    first() {
      return createJQueryStub(elements[0] ? [elements[0]] : []);
    },
    remove() {
      elements.forEach(function (element) {
        element.remove();
      });
      return this;
    },
  };
}

describe('email compose wiring', function () {
  beforeEach(function () {
    vi.resetModules();

    document.body.innerHTML = [
      '<div data-email-compose-feedback class="alert d-none"></div>',
      '<a href="javascript:void(0);" id="compose_mail">Compose</a>',
      '<div id="compose-view" data-auto-open="0">',
      '  <button type="button" id="compose-close">Close</button>',
      '  <form data-email-compose-form>',
      '    <input name="to" value="recipient@example.com">',
      '    <input name="subject" value="Compose subject">',
      '    <textarea name="message">Compose body</textarea>',
      '    <button type="submit" data-email-compose-submit>Send <i class="ti ti-arrow-right ms-2"></i></button>',
      '  </form>',
      '</div>',
      '<div class="mails-list"></div>',
    ].join('');

    window.$ = createJQueryStub;
    window.jQuery = createJQueryStub;

    window.AuthApi = {
      getToken: vi.fn(function () { return 'compose-token'; }),
      getTenantContext: vi.fn(function () {
        return { companyCode: 'tenant-compose', companyId: 88, companyUuid: 'tenant-uuid-88' };
      }),
    };

    global.FormData = class FormDataStub {
      constructor(form) {
        this.values = new Map();
        Array.from(form.querySelectorAll('input, textarea, select')).forEach((field) => {
          if (!field.name) {
            return;
          }
          this.values.set(field.name, field.value);
        });
      }

      get(name) {
        return this.values.get(name);
      }
    };

    global.fetch = vi.fn(function () {
      return Promise.resolve({
        ok: true,
        json: function () {
          return Promise.resolve({
            success: true,
            data: {
              to: 'recipient@example.com',
              subject: 'Compose subject',
              sentAt: '2026-04-24T18:10:00+00:00',
            },
          });
        },
      });
    });
  });

  it('submits the compose form through the bearer-token API and renders success feedback', async function () {
    await import('../../../frontend/resources/js/email.js');

    document.querySelector('[data-email-compose-form]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(global.fetch).toHaveBeenCalledTimes(1);

    const call = global.fetch.mock.calls[0];
    expect(call[0]).toBe('/v1/hcm/email-settings/compose');
    expect(call[1].method).toBe('POST');
    expect(call[1].credentials).toBe('same-origin');
    expect(call[1].headers.Authorization).toBe('Bearer compose-token');
    expect(call[1].headers['X-Company-Code']).toBe('tenant-compose');
    expect(call[1].headers['X-Company-Id']).toBe('88');

    const payload = JSON.parse(call[1].body);
    expect(payload).toEqual({
      to: 'recipient@example.com',
      subject: 'Compose subject',
      message: 'Compose body',
    });

    expect(document.querySelector('[data-email-compose-feedback]').textContent).toContain('Email berhasil dikirim ke recipient@example.com');
  });

  it('shows warning feedback when api token is unavailable', async function () {
    window.AuthApi.getToken = vi.fn(function () { return null; });
    global.fetch = vi.fn(function (url) {
      if (url === '/api-token') {
        return Promise.resolve({
          ok: false,
          json: function () {
            return Promise.resolve({ success: false });
          },
        });
      }

      return Promise.reject(new Error('Unexpected fetch: ' + url));
    });

    await import('../../../frontend/resources/js/email.js');

    document.querySelector('[data-email-compose-form]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(global.fetch).toHaveBeenCalledTimes(1);
    expect(global.fetch.mock.calls[0][0]).toBe('/api-token');
    expect(document.querySelector('[data-email-compose-feedback]').textContent).toContain('API token tidak ditemukan');
  });
});