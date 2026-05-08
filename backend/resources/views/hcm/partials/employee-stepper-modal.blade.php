@php
    $mode = $mode ?? 'add';
    $isEdit = $mode === 'edit';
    $modalId = $isEdit ? 'edit_employee' : 'add_employee';
    $formAttr = $isEdit ? 'data-employee-edit-form' : 'data-employee-add-form';
    $fieldAttr = $isEdit ? 'data-employee-edit-field' : 'data-employee-add-field';
    $title = $isEdit ? 'Edit Employee' : 'Add New Employee';
    $submitLabel = $isEdit ? 'Save Changes' : 'Create Employee';
    $subtitle = $isEdit
        ? 'Update personal, employment, compensation, contract, and compliance data in one guided flow.'
        : 'Create an employee using the new modular HRIS form and normalized payroll structure.';
    $religionOptions = config('hcm.religions', ['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Konghucu']);
    $taxStatusOptions = config('hcm.tax_statuses', ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3']);
    $bankOptions = config('hcm.allowed_bank_names', ['BCA', 'Bank Mandiri', 'BNI', 'BRI', 'BTN', 'CIMB Niaga', 'Permata Bank', 'Danamon', 'BSI', 'OCBC NISP', 'Panin Bank', 'Maybank Indonesia', 'Bank Mega', 'Bank Sinarmas', 'Jenius / BTPN', 'SeaBank', 'Bank Jago']);
@endphp

@once
    <style>
        .employee-stepper-shell .modal-content {
            overflow: hidden;
        }
        .employee-stepper-shell .modal-body {
            max-height: calc(100vh - 220px);
            overflow-y: auto;
            overflow-x: hidden;
        }
        .employee-stepper-shell .modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 3;
            background: #fff;
            border-top: 1px solid var(--bs-border-color, #dee2e6);
        }
        .employee-stepper-shell .employee-stepper-nav {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #fff;
            padding-bottom: .75rem;
        }
        .employee-stepper-shell .employee-step-pane .form-label {
            white-space: normal;
        }
        @media (max-width: 991.98px) {
            .employee-stepper-shell .modal-dialog {
                margin: 0;
                max-width: none;
                height: 100%;
            }
            .employee-stepper-shell .modal-content {
                height: 100%;
                border-radius: 0;
            }
            .employee-stepper-shell .modal-body {
                max-height: none;
            }
            .employee-stepper-shell .modal-footer {
                flex-wrap: wrap;
            }
        }
    </style>
@endonce

<div class="modal fade employee-stepper-shell" id="{{ $modalId }}" tabindex="-1" aria-hidden="true" data-employee-stepper-modal="{{ $mode }}">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header align-items-start">
                <div>
                    <h4 class="modal-title mb-1">{{ $title }}</h4>
                    <p class="text-muted mb-0 small">{{ $subtitle }}</p>
                </div>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ti ti-x"></i>
                </button>
            </div>

            <form action="{{ url(Route::is('employees-grid') ? 'employees-grid' : 'employees') }}" {{ $formAttr }} novalidate>
                <div class="modal-body">
                    <div class="employee-stepper-nav d-flex flex-wrap gap-2 mb-4" data-employee-stepper-nav>
                        <button type="button" class="btn btn-light btn-sm employee-step-btn active" data-employee-step-trigger="0">1. Personal</button>
                        <button type="button" class="btn btn-light btn-sm employee-step-btn" data-employee-step-trigger="1">2. Employment</button>
                        <button type="button" class="btn btn-light btn-sm employee-step-btn" data-employee-step-trigger="2">3. Compensation</button>
                        <button type="button" class="btn btn-light btn-sm employee-step-btn" data-employee-step-trigger="3">4. Bank & Tax</button>
                        <button type="button" class="btn btn-light btn-sm employee-step-btn" data-employee-step-trigger="4">5. Background</button>
                    </div>

                    <div class="alert alert-light border d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
                        <div>
                            <strong class="d-block">Employee ID</strong>
                            <span class="text-muted small" data-employee-modal-employee-no>{{ $isEdit ? 'Load employee data…' : 'ID akan tersedia setelah save' }}</span>
                        </div>
                        <span class="badge bg-soft-primary text-primary" data-employee-step-caption>Step 1 of 5</span>
                    </div>

                    <div class="employee-step-pane" data-employee-step-pane="0">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="name" placeholder="Nama lengkap employee" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" {{ $fieldAttr }}="email" placeholder="email@company.com" required>
                            </div>

                            @unless ($isEdit)
                                <div class="col-md-6">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" {{ $fieldAttr }}="password" autocomplete="new-password" placeholder="Minimal 8 karakter" required>
                                    <small class="text-muted d-block mt-1">Harus 8-64 karakter dan mengandung huruf besar, huruf kecil, serta angka.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" {{ $fieldAttr }}="confirmPassword" autocomplete="new-password" placeholder="Ulangi password" required>
                                </div>
                            @endunless

                            <div class="col-md-6">
                                <label class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="phone" placeholder="08xxxxxxxxxx" inputmode="numeric" maxlength="13" pattern="[0-9]{10,13}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NIK / No. KTP <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="nik" placeholder="16 digit NIK" inputmode="numeric" maxlength="16" pattern="[0-9]{16}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Place of Birth <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="placeOfBirth" placeholder="Kota lahir" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" {{ $fieldAttr }}="dateOfBirth" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender <span class="text-danger">*</span></label>
                                <select class="form-select" {{ $fieldAttr }}="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marital Status <span class="text-danger">*</span></label>
                                <select class="form-select" {{ $fieldAttr }}="maritalStatus" required>
                                    <option value="">Select status</option>
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="divorced">Divorced</option>
                                    <option value="widowed">Widowed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Religion <span class="text-danger">*</span></label>
                                <select class="form-select" {{ $fieldAttr }}="religion" required>
                                    <option value="">Select religion</option>
                                    @foreach ($religionOptions as $religionOption)
                                        <option value="{{ $religionOption }}">{{ $religionOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nationality <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="nationality" placeholder="Indonesia" value="Indonesia" readonly required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bio / Notes</label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="bio" placeholder="Ringkasan singkat atau catatan internal">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address <span class="text-danger">*</span></label>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Province</label>
                                        <select class="form-select" {{ $fieldAttr }}="provinceId" data-employee-wilayah-province required>
                                            <option value="">Select province</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Regency / City</label>
                                        <select class="form-select" {{ $fieldAttr }}="regencyId" data-employee-wilayah-regency required disabled>
                                            <option value="">Select regency</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">District</label>
                                        <select class="form-select" {{ $fieldAttr }}="districtId" data-employee-wilayah-district required disabled>
                                            <option value="">Select district</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Village / Ward</label>
                                        <select class="form-select" {{ $fieldAttr }}="villageId" data-employee-wilayah-village required disabled>
                                            <option value="">Select village</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small text-muted">Address Detail (manual)</label>
                                        <input type="hidden" {{ $fieldAttr }}="address" data-employee-address-autofill>
                                        <textarea class="form-control" rows="2" {{ $fieldAttr }}="addressDetail" placeholder="Street, building, RT/RW, landmark"></textarea>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2">Wilayah tetap dipilih dari dropdown. Isi detail alamat manual jika diperlukan.</small>
                            </div>
                        </div>
                    </div>

                    <div class="employee-step-pane d-none" data-employee-step-pane="1">
                        <div class="row g-3">
                            @include('hcm.partials.employee-modal-org-fields')

                            <div class="col-md-6">
                                <label class="form-label">Employment Status <span class="text-danger">*</span></label>
                                <select class="form-select" {{ $fieldAttr }}="employmentStatus" required>
                                    <option value="active" selected>Active</option>
                                    <option value="probation">Probation</option>
                                    <option value="resigned">Resigned</option>
                                    <option value="terminated">Terminated</option>
                                    <option value="inactive">Inactive (legacy)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Employee Type <span class="text-danger">*</span></label>
                                <select class="form-select" {{ $fieldAttr }}="employeeType" required>
                                    <option value="">Select type</option>
                                    <option value="permanent">Permanent</option>
                                    <option value="contract">Contract</option>
                                    <option value="intern">Intern</option>
                                </select>
                                <small class="text-muted d-block mt-1">Contract type akan diselaraskan otomatis sesuai employee type untuk mencegah data bentrok.</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Effective Start Date</label>
                                <input type="date" class="form-control" {{ $fieldAttr }}="startDate">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Probation End Date</label>
                                <input type="date" class="form-control" {{ $fieldAttr }}="probationEndDate">
                            </div>
                        </div>
                    </div>

                    <div class="employee-step-pane d-none" data-employee-step-pane="2">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Base Salary (IDR) <span class="text-danger">*</span></label>
                                <input type="number" min="0" step="1" class="form-control" {{ $fieldAttr }}="baseSalary" placeholder="0" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contract Type <span class="text-danger">*</span></label>
                                <select class="form-select" {{ $fieldAttr }}="contractType" required>
                                    <option value="permanent" selected>Permanent</option>
                                    <option value="contract">Contract</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contract Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" {{ $fieldAttr }}="contractStartDate" required>
                            </div>
                            <div class="col-md-4 d-none" data-employee-contract-end-wrap>
                                <label class="form-label">Contract End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" {{ $fieldAttr }}="contractEndDate">
                                <small class="text-muted">Wajib hanya untuk contract.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contract Status <span class="text-danger">*</span></label>
                                <select class="form-select" {{ $fieldAttr }}="contractStatus" required>
                                    <option value="active" selected>Active</option>
                                    <option value="ended">Ended</option>
                                    <option value="terminated">Terminated</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="employee-step-pane d-none" data-employee-step-pane="3">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                                <select class="form-select" {{ $fieldAttr }}="bankName" required>
                                    <option value="">Select bank</option>
                                    @foreach ($bankOptions as $bankOption)
                                        <option value="{{ $bankOption }}">{{ $bankOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Bank Account No <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="bankAccountNo" placeholder="Nomor rekening" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Account Holder Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="bankAccountHolderName" placeholder="Nama pemilik rekening" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bank / SWIFT / IFSC Code</label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="bankIfscCode" placeholder="Kode bank atau branch code">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bank Branch</label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="bankBranch" placeholder="Cabang bank">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">NPWP</label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="npwp" placeholder="12.345.678.9-000.000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tax Status</label>
                                <select class="form-select" {{ $fieldAttr }}="taxStatus">
                                    <option value="">Select tax status</option>
                                    @foreach ($taxStatusOptions as $taxStatusOption)
                                        <option value="{{ $taxStatusOption }}">{{ $taxStatusOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">PTKP Status (alias)</label>
                                <select class="form-select" {{ $fieldAttr }}="ptkpStatus">
                                    <option value="">Select PTKP alias</option>
                                    @foreach ($taxStatusOptions as $taxStatusOption)
                                        <option value="{{ $taxStatusOption }}">{{ $taxStatusOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">BPJS Kesehatan No</label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="bpjsKesehatanNo" placeholder="Nomor BPJS Kesehatan">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">BPJS Ketenagakerjaan No</label>
                                <input type="text" class="form-control" {{ $fieldAttr }}="bpjsKetenagakerjaanNo" placeholder="Nomor BPJS Ketenagakerjaan">
                            </div>
                        </div>
                    </div>

                    <div class="employee-step-pane d-none" data-employee-step-pane="4">
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <h6 class="mb-0">Emergency Contacts</h6>
                                    <small class="text-muted">Tambahkan minimal satu kontak darurat yang valid.</small>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-employee-repeat-add="emergencyContacts">Add Contact</button>
                            </div>
                            <div class="row g-2" data-employee-repeatable="emergencyContacts"></div>
                            <template data-employee-repeatable-template="emergencyContacts">
                                <div class="col-12" data-repeat-row>
                                    <div class="border rounded p-3 bg-light-subtle">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label">Name</label>
                                                <input type="text" class="form-control" data-repeat-key="name" placeholder="Nama kontak">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Relationship</label>
                                                <input type="text" class="form-control" data-repeat-key="relationship" placeholder="Spouse / Parent">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Phone</label>
                                                <input type="text" class="form-control" data-repeat-key="phone" placeholder="08xxxxxxxxxx" inputmode="numeric" maxlength="13" pattern="[0-9]{10,13}">
                                            </div>
                                            <div class="col-md-2 text-md-end">
                                                <button type="button" class="btn btn-outline-danger btn-sm w-100" data-employee-repeat-remove>Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <h6 class="mb-0">Education History</h6>
                                    <small class="text-muted">Masukkan riwayat pendidikan utama.</small>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-employee-repeat-add="educationItems">Add Education</button>
                            </div>
                            <div class="row g-2" data-employee-repeatable="educationItems"></div>
                            <template data-employee-repeatable-template="educationItems">
                                <div class="col-12" data-repeat-row>
                                    <div class="border rounded p-3 bg-light-subtle">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label">Institution</label>
                                                <input type="text" class="form-control" data-repeat-key="institution" placeholder="Universitas / Sekolah">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Degree</label>
                                                <input type="text" class="form-control" data-repeat-key="degree" placeholder="S1, SMA, Diploma">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Start Year</label>
                                                <input type="number" min="1900" max="2100" class="form-control" data-repeat-key="startYear" placeholder="2018">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">End Year</label>
                                                <input type="number" min="1900" max="2100" class="form-control" data-repeat-key="endYear" placeholder="2022">
                                            </div>
                                            <div class="col-md-1 text-md-end">
                                                <button type="button" class="btn btn-outline-danger btn-sm w-100" data-employee-repeat-remove>×</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <h6 class="mb-0">Experience History</h6>
                                    <small class="text-muted">Catat pengalaman kerja utama untuk payroll/HR review.</small>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-employee-repeat-add="experienceItems">Add Experience</button>
                            </div>
                            <div class="row g-2" data-employee-repeatable="experienceItems"></div>
                            <template data-employee-repeatable-template="experienceItems">
                                <div class="col-12" data-repeat-row>
                                    <div class="border rounded p-3 bg-light-subtle">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label">Company</label>
                                                <input type="text" class="form-control" data-repeat-key="company" placeholder="Nama perusahaan">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Position</label>
                                                <input type="text" class="form-control" data-repeat-key="position" placeholder="Jabatan">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Start Date</label>
                                                <input type="date" class="form-control" data-repeat-key="startDate">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">End Date</label>
                                                <input type="date" class="form-control" data-repeat-key="endDate">
                                            </div>
                                            <div class="col-md-1 text-md-end">
                                                <button type="button" class="btn btn-outline-danger btn-sm w-100" data-employee-repeat-remove>×</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        @unless ($isEdit)
                            <div class="mt-4 pt-3 border-top">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" {{ $fieldAttr }}="dataDisclosureAcknowledged" id="{{ $mode }}_data_disclosure_acknowledged" required>
                                    <label class="form-check-label" for="{{ $mode }}_data_disclosure_acknowledged">
                                        Saya mengakui pemrosesan dan disclosure data employee sesuai kebijakan perusahaan. <span class="text-danger">*</span>
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1">Persetujuan ini wajib sebelum data employee baru disimpan.</small>
                            </div>
                        @endunless
                    </div>
                </div>

                <div class="modal-footer justify-content-between">
                    <div class="small text-muted">Use Next/Back to move between sections before saving.</div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-outline-secondary d-none" data-employee-step-prev>Back</button>
                        <button type="button" class="btn btn-primary" data-employee-step-next>Next</button>
                        <button type="submit" class="btn btn-primary d-none" data-employee-step-submit>{{ $submitLabel }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
