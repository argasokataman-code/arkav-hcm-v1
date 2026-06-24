import { describe, test, expect, beforeEach } from 'vitest';
import { setupCommonMocks } from './helpers/form-validation-test';

function buildDom() {
    var fields = ["code","name"];
    document.body.innerHTML = '<form id="arcav_payroll_item_add">' +
        fields.map(function(f) {
            return '<input id="' + f + '" required><div class="invalid-feedback">Required</div>';
        }).join('') +
        '<button type="submit">Save</button></form>';
}

describe('payroll-item form validation', function () {
    beforeEach(function () {
        document.body.innerHTML = '';
        setupCommonMocks();
        buildDom();
    });

    test('rejects empty required fields', function () {
        var form = document.getElementById('arcav_payroll_item_add');
        var result = window.ArcavValidation.validateForm(form);
        expect(result).toBe(false);
        expect(form.classList.contains('was-validated')).toBe(true);
        var el0 = document.getElementById('code');
        expect(el0.classList.contains('is-invalid')).toBe(true);
        var el1 = document.getElementById('name');
        expect(el1.classList.contains('is-invalid')).toBe(true);
    });

    test('accepts filled required fields', function () {
        var el0 = document.getElementById('code'); if (el0) el0.value = 'test';
        var el1 = document.getElementById('name'); if (el1) el1.value = 'test';
        var form = document.getElementById('arcav_payroll_item_add');
        var result = window.ArcavValidation.validateForm(form);
        expect(result).toBe(true);
    });
});
