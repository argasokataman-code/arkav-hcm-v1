import { describe, test, expect, beforeEach } from 'vitest';
import { setupCommonMocks } from './helpers/form-validation-test';

function buildDom() {
    var fields = ["employee_id","termination_date"];
    document.body.innerHTML = '<form id="arcav_termination_modal">' +
        fields.map(function(f) {
            return '<input id="' + f + '" required><div class="invalid-feedback">Required</div>';
        }).join('') +
        '<button type="submit">Save</button></form>';
}

describe('termination form validation', function () {
    beforeEach(function () {
        document.body.innerHTML = '';
        setupCommonMocks();
        buildDom();
    });

    test('rejects empty required fields', function () {
        var form = document.getElementById('arcav_termination_modal');
        var result = window.ArcavValidation.validateForm(form);
        expect(result).toBe(false);
        expect(form.classList.contains('was-validated')).toBe(true);
        var el0 = document.getElementById('employee_id');
        expect(el0.classList.contains('is-invalid')).toBe(true);
        var el1 = document.getElementById('termination_date');
        expect(el1.classList.contains('is-invalid')).toBe(true);
    });

    test('accepts filled required fields', function () {
        var el0 = document.getElementById('employee_id'); if (el0) el0.value = 'test';
        var el1 = document.getElementById('termination_date'); if (el1) el1.value = 'test';
        var form = document.getElementById('arcav_termination_modal');
        var result = window.ArcavValidation.validateForm(form);
        expect(result).toBe(true);
    });
});
