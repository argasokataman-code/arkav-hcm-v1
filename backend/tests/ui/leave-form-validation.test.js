import { describe, test, expect, beforeEach } from 'vitest';
import { setupCommonMocks } from './helpers/form-validation-test';

function buildDom() {
    var fields = ["leave_type_id","leave_start_date","leave_end_date"];
    document.body.innerHTML = '<form id="arcav_add_leave">' +
        fields.map(function(f) {
            return '<input id="' + f + '" required><div class="invalid-feedback">Required</div>';
        }).join('') +
        '<button type="submit">Save</button></form>';
}

describe('leave form validation', function () {
    beforeEach(function () {
        document.body.innerHTML = '';
        setupCommonMocks();
        buildDom();
    });

    test('rejects empty required fields', function () {
        var form = document.getElementById('arcav_add_leave');
        var result = window.ArcavValidation.validateForm(form);
        expect(result).toBe(false);
        expect(form.classList.contains('was-validated')).toBe(true);
        var el0 = document.getElementById('leave_type_id');
        expect(el0.classList.contains('is-invalid')).toBe(true);
        var el1 = document.getElementById('leave_start_date');
        expect(el1.classList.contains('is-invalid')).toBe(true);
        var el2 = document.getElementById('leave_end_date');
        expect(el2.classList.contains('is-invalid')).toBe(true);
    });

    test('accepts filled required fields', function () {
        var el0 = document.getElementById('leave_type_id'); if (el0) el0.value = 'test';
        var el1 = document.getElementById('leave_start_date'); if (el1) el1.value = 'test';
        var el2 = document.getElementById('leave_end_date'); if (el2) el2.value = 'test';
        var form = document.getElementById('arcav_add_leave');
        var result = window.ArcavValidation.validateForm(form);
        expect(result).toBe(true);
    });
});
