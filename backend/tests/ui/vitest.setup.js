import { afterEach, beforeEach, vi } from 'vitest';

beforeEach(() => {
  localStorage.clear();
});

afterEach(() => {
  // Ensure timers are cleared and restored to real timers to avoid leaking
  // fake timers across tests which can cause hook timeouts.
  try {
    if (typeof vi.clearAllTimers === 'function') vi.clearAllTimers();
  } catch (e) {
    // ignore
  }
  try {
    vi.useRealTimers();
  } catch (e) {
    // ignore
  }
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
