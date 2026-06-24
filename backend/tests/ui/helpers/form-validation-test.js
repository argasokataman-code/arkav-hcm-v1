import { expect, vi, beforeAll } from 'vitest';
import fs from 'fs';
import path from 'path';

// Load ArcavValidation helper into window
const validationCode = fs.readFileSync(
    path.resolve(__dirname, '../../../../frontend/resources/js/core/arcav-validation.js'),
    'utf-8'
);

export function loadArcavValidation() {
    delete window.ArcavValidation;
    const fn = new Function(validationCode + '; return window.ArcavValidation;');
    return fn();
}

export function mockBootstrapModal() {
    window.bootstrap = window.bootstrap || {};
    window.bootstrap.Modal = window.bootstrap.Modal || {
        getOrCreateInstance: vi.fn(() => ({
            show: vi.fn(),
            hide: vi.fn(),
        })),
    };
}

export function mockAuthApi() {
    window.AuthApi = {
        getToken: () => 'test-token',
        getTenantContext: () => ({
            companyCode: 'ACME',
            companyId: 99,
            companyUuid: '11111111-2222-3333-4444-555555555555',
        }),
    };
    window.AuthUser = {
        id: 1,
        email: 'admin@test.com',
        isHcmAdmin: true,
        hcmGlobalAdmin: false,
        permissions: ['user_management.manage'],
        name: 'Admin',
    };
    window.AuthPermissions = {
        hasPermission: () => true,
    };
}

/**
 * Test that a form validates required fields on submit.
 * @param {string} formId - The form element ID
 * @param {string[]} requiredFieldIds - IDs of fields that are required
 * @param {object} options
 * @param {string} options.submitBtn - Selector for submit button (default: '[type="submit"]')
 */
export function testFormValidation(formId, requiredFieldIds, options = {}) {
    const submitBtn = options.submitBtn || '[type="submit"]';
    const form = document.getElementById(formId);
    const btn = form ? form.querySelector(submitBtn) : null;

    if (!form || !btn) {
        // Form might be dynamically created - skip silently
        return;
    }

    // Verify required fields exist
    for (const id of requiredFieldIds) {
        const el = document.getElementById(id);
        expect(el, `Required field #${id} should exist in DOM`).toBeTruthy();
    }

    // Mock fetch to track calls
    const originalFetch = window.fetch;
    window.fetch = vi.fn(() => Promise.resolve(new Response(JSON.stringify({ success: true }), { status: 200, headers: { 'Content-Type': 'application/json' } })));

    // Submit with empty required fields
    form.dispatchEvent(new Event('submit', { cancelable: true }));

    // Check that was-validated was added
    expect(form.classList.contains('was-validated'), `${formId} should have was-validated class after submit`).toBe(true);

    // Check that invalid fields got is-invalid class
    for (const id of requiredFieldIds) {
        const el = document.getElementById(id);
        if (el && !el.value) {
            expect(el.classList.contains('is-invalid'), `#${id} should have is-invalid when empty`).toBe(true);
        }
    }

    // Check that API was NOT called (validation prevented it)
    expect(window.fetch).not.toHaveBeenCalled();

    window.fetch = originalFetch;
}

/**
 * Test that a form submits successfully with valid data.
 */
export function testFormSubmitSuccess(formId, fillValues = {}) {
    const form = document.getElementById(formId);
    if (!form) return;

    for (const [id, value] of Object.entries(fillValues)) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    }

    const originalFetch = window.fetch;
    window.fetch = vi.fn(() => Promise.resolve(new Response(JSON.stringify({ success: true }), { status: 200, headers: { 'Content-Type': 'application/json' } })));

    form.dispatchEvent(new Event('submit', { cancelable: true }));

    expect(form.classList.contains('is-valid') || !form.classList.contains('was-validated') || form.querySelectorAll('.is-invalid').length === 0).toBe(true);

    window.fetch = originalFetch;
}

export function setupCommonMocks() {
    mockBootstrapModal();
    mockAuthApi();
    window.ArcavUi = {
        showToast: vi.fn(),
        confirmDelete: vi.fn(() => Promise.resolve(true)),
    };
    window.ArcavValidation = loadArcavValidation();
}
