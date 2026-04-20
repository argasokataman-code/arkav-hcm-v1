import { afterEach, beforeEach, vi } from 'vitest';

beforeEach(() => {
  localStorage.clear();
});

afterEach(() => {
  vi.restoreAllMocks();
  delete window.bootstrap;
  delete window.AuthApi;
  delete window.AuthPermissions;
  delete window.TransactionsManager;
  delete window.__ARCAV_DISABLE_REDIRECTS__;
  delete window.__ARCAV_LAST_REDIRECT__;
  document.body.innerHTML = '';
  localStorage.clear();
});
