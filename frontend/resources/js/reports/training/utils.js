/* global bootstrap */

(function (window) {
  'use strict';

  function esc(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function notify(type, message) {
    try {
      if (window.ArcavUi && typeof window.ArcavUi.toast === 'function') {
        window.ArcavUi.toast(type, message);
        return;
      }
    } catch (_) {}
    if (type === 'error') console.error(message);
    else console.log(message);
  }

  function flash(el, type, message) {
    if (!el) return;
    const cls = type === 'error' ? 'alert-danger' : 'alert-success';
    el.innerHTML = `<div class="alert ${cls} mb-3">${esc(message)}</div>`;
    el.style.display = '';
  }

  function apiErrorMessage(err) {
    const e = err?.response?.data ?? err;
    return e?.error?.message || e?.message || 'Terjadi kesalahan. Silakan coba lagi.';
  }

  async function apiRequest(method, url, body) {
    const headers = { 'Content-Type': 'application/json' };
    if (window.axios) {
      try {
        const res = await window.axios.request({
          method,
          url,
          data: body,
          headers,
          withCredentials: true,
        });
        return res.data;
      } catch (err) {
        const status = err?.response?.status;
        const data = err?.response?.data;
        if (status === 401 || data?.error?.code === 'AUTH_UNAUTHORIZED') {
          window.location.replace('/login');
          return new Promise(() => {});
        }
        throw err;
      }
    }

    const res = await fetch(url, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined,
      credentials: 'same-origin',
    });
    const text = await res.text();
    let json;
    try {
      json = text ? JSON.parse(text) : {};
    } catch (_) {
      json = { success: false, error: { message: text || 'Invalid response' } };
    }
    if (!res.ok) {
      if (res.status === 401 || json?.error?.code === 'AUTH_UNAUTHORIZED') {
        window.location.replace('/login');
        return new Promise(() => {});
      }
      throw json;
    }
    return json;
  }

  async function loadMe() {
    try {
      const res = await apiRequest('GET', '/v1/identity/auth/me');
      return res?.data || null;
    } catch (_) {
      return null;
    }
  }

  window.ArcavTrainingUtils = {
    esc,
    notify,
    flash,
    apiErrorMessage,
    apiRequest,
    loadMe,
  };
})(window);
