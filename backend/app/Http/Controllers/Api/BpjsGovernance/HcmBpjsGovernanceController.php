<?php



namespace App\Http\Controllers\Api\BpjsGovernance;



use App\Http\Controllers\Api\Concerns\ChecksPermissions;

use App\Http\Controllers\Controller;

use App\Models\CompanyUser;

use App\Models\EmployeeBenefit;

use App\Models\EmployeeProfile;

use App\Models\HcmBpjsGovernancePolicy;

use App\Models\HcmBpjsGovernancePolicyHistory;

use App\Models\HcmBpjsGovernanceRateBaseline;

use App\Models\HcmSalaryComponent;

use App\Models\User;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Carbon;

use Illuminate\Validation\Rule;

use Illuminate\Validation\ValidationException;

use App\Http\Controllers\Api\BpjsGovernance\Concerns\HandlesBpjsCrud;

use App\Http\Controllers\Api\BpjsGovernance\Concerns\HandlesBpjsReports;



class HcmBpjsGovernanceController extends Controller

{
    use ChecksPermissions;
    use HandlesBpjsCrud;
    use HandlesBpjsReports;
}