import { describe, test, expect, beforeEach } from 'vitest';
import { validateComplianceFields } from '../resources/js/employees/employees-compensation-forms';

describe('validateComplianceFields', () => {
    let form;

    beforeEach(() => {
        document.body.innerHTML = `
            <form>
                <input data-employee-add-field="npwp" value="" />
                <input data-employee-add-field="bpjsKesehatanNo" value="" />
                <input data-employee-add-field="bpjsKetenagakerjaanNo" value="" />
            </form>
        `;
        form = document.querySelector('form');
    });

    test('validates NPWP correctly', () => {
        const npwpInput = form.querySelector('[data-employee-add-field="npwp"]');
        npwpInput.value = '123456789012345';
        expect(validateComplianceFields(form)).toBe(true);

        npwpInput.value = 'invalid-npwp';
        expect(validateComplianceFields(form)).toBe(false);
    });

    test('validates BPJS Kesehatan correctly', () => {
        const bpjsKesInput = form.querySelector('[data-employee-add-field="bpjsKesehatanNo"]');
        bpjsKesInput.value = '12345678901';
        expect(validateComplianceFields(form)).toBe(true);

        bpjsKesInput.value = 'invalid-bpjs';
        expect(validateComplianceFields(form)).toBe(false);
    });

    test('validates BPJS Ketenagakerjaan correctly', () => {
        const bpjsKetInput = form.querySelector('[data-employee-add-field="bpjsKetenagakerjaanNo"]');
        bpjsKetInput.value = '123456789012';
        expect(validateComplianceFields(form)).toBe(true);

        bpjsKetInput.value = 'invalid-bpjs';
        expect(validateComplianceFields(form)).toBe(false);
    });
});