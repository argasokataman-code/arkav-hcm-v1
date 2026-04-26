{{-- Shared by Add / Edit employee modals (employees list). Options filled by employees-data.js. --}}
<div class="col-md-6">
    <div class="mb-3">
        <label class="form-label">Departemen <span class="text-danger">*</span></label>
        <select class="form-select" data-employee-add-field="departmentId" data-employee-edit-field="departmentId" data-employee-org-department required>
            <option value="">— Pilih —</option>
        </select>
    </div>
</div>
<div class="col-md-6">
    <div class="mb-3">
        <label class="form-label">Jabatan (master) <span class="text-danger">*</span></label>
        <select class="form-select" data-employee-add-field="designationId" data-employee-edit-field="designationId" data-employee-org-designation required>
            <option value="">— Pilih —</option>
        </select>
    </div>
</div>
<div class="col-md-6">
    <div class="mb-3">
        <label class="form-label">Tim (Team)</label>
        <select class="form-select" data-employee-add-field="teamId" data-employee-edit-field="teamId" data-employee-org-team>
            <option value="">— Pilih Team (opsional) —</option>
        </select>
    </div>
</div>
