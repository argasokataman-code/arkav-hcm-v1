const fs = require('fs');

const forms = [
    { name: 'salary-component', formId: 'arcav_add_salary_component', fields: ['sc_code','sc_name'] },
    { name: 'payroll-item', formId: 'arcav_payroll_item_add', fields: ['code','name'] },
    { name: 'overtime-type', formId: 'arcav_add_ot_type', fields: ['ot_code','ot_name'] },
    { name: 'shift', formId: 'arcav_add_shift', fields: ['shift_name','shift_start_time'] },
    { name: 'holiday', formId: 'arcav_add_holiday', fields: ['holiday_name','holiday_date'] },
    { name: 'leave', formId: 'arcav_add_leave', fields: ['leave_type_id','leave_start_date','leave_end_date'] },
    { name: 'performance', formId: 'arcav_perf_template_modal', fields: ['template_name'] },
    { name: 'document-center', formId: 'arcav_doc_upload_modal', fields: ['doc_name','doc_file'] },
    { name: 'ticket', formId: 'arcav_ticket_create_modal', fields: ['ticket_title','ticket_description'] },
    { name: 'trainer', formId: 'arcav_trainer_modal', fields: ['trainer_name','trainer_email'] },
    { name: 'training', formId: 'arcav_training_modal', fields: ['training_title','training_type'] },
    { name: 'goal', formId: 'arcav_goal_modal', fields: ['goal_title','goal_type'] },
    { name: 'promotion', formId: 'arcav_promotion_modal', fields: ['employee_id','promotion_title'] },
    { name: 'resignation', formId: 'arcav_resignation_modal', fields: ['employee_id','resignation_date'] },
    { name: 'termination', formId: 'arcav_termination_modal', fields: ['employee_id','termination_date'] },
    { name: 'employee-stepper', formId: 'employee_stepper_form', fields: ['employee_name','employee_email'] },
];

const testDir = '/Users/vanviakingali/arcav_new_v2/backend/tests/ui';

for (const form of forms) {
    const file = testDir + '/' + form.name + '-form-validation.test.js';
    const f = JSON.stringify(form.fields);
    const eachField = form.fields.map(function(f, i) {
        return '        var el' + i + " = document.getElementById('" + f + "');\n" +
               '        expect(el' + i + ".classList.contains('is-invalid')).toBe(true);";
    }).join('\n');

    const fillFields = form.fields.map(function(f, i) {
        return '        var el' + i + " = document.getElementById('" + f + "'); if (el" + i + ") el" + i + ".value = 'test';";
    }).join('\n');

    const content = `import { describe, test, expect, beforeEach } from 'vitest';
import { setupCommonMocks } from './helpers/form-validation-test';

function buildDom() {
    var fields = ${f};
    document.body.innerHTML = '<form id="${form.formId}">' +
        fields.map(function(f) {
            return '<input id="' + f + '" required><div class="invalid-feedback">Required</div>';
        }).join('') +
        '<button type="submit">Save</button></form>';
}

describe('${form.name} form validation', function () {
    beforeEach(function () {
        document.body.innerHTML = '';
        setupCommonMocks();
        buildDom();
    });

    test('rejects empty required fields', function () {
        var form = document.getElementById('${form.formId}');
        var result = window.ArcavValidation.validateForm(form);
        expect(result).toBe(false);
        expect(form.classList.contains('was-validated')).toBe(true);
${eachField}
    });

    test('accepts filled required fields', function () {
${fillFields}
        var form = document.getElementById('${form.formId}');
        var result = window.ArcavValidation.validateForm(form);
        expect(result).toBe(true);
    });
});
`;

    fs.writeFileSync(file, content);
    console.log('OK: ' + form.name);
}

console.log('\\nDone: 16 files');
