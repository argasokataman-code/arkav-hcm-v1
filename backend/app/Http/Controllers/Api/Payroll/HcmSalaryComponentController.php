<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\EmployeeBenefit;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmBpjsGovernancePolicy;
use App\Models\HcmEmployeeAllowancePolicy;
use App\Models\HcmEmployeePayrollItemAssignment;
use App\Models\HcmPayrollItem;
use App\Models\HcmSalaryComponent;
use App\Models\HcmSalaryComponentCategory;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HcmSalaryComponentController extends Controller
{
    use ChecksPermissions;
    use HandlesSalaryComponentCategories;
    use HandlesSalaryComponentCrud;
    use HandlesSalaryComponentEmployeeProfiles;
}