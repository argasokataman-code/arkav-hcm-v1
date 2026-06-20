<?php



namespace App\Http\Controllers\Api\AllowanceGovernance;



use App\Http\Controllers\Api\Concerns\ChecksPermissions;

use App\Http\Controllers\Controller;

use App\Models\CompanyUser;

use App\Models\HcmEmployeeAllowanceAssignment;

use App\Models\HcmEmployeeAllowanceAssignmentHistory;

use App\Models\HcmEmployeePayrollItemAssignment;

use App\Models\HcmEmployeeAllowancePolicy;

use App\Models\HcmEmployeeAllowancePolicyHistory;

use App\Models\HcmPayrollItem;

use App\Models\HcmSalaryComponent;

use App\Models\User;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Carbon;

use Illuminate\Support\Facades\DB;

use Illuminate\Validation\ValidationException;

use App\Http\Controllers\Api\AllowanceGovernance\Concerns\HandlesAllowancePolicies;

use App\Http\Controllers\Api\AllowanceGovernance\Concerns\HandlesAllowanceAssignments;

use App\Http\Controllers\Api\AllowanceGovernance\Concerns\HandlesAllowanceReports;
use App\Http\Controllers\Api\Api\AllowanceGovernance\Concerns\HandlesAllowancePolicies;
use App\Http\Controllers\Api\Api\AllowanceGovernance\Concerns\HandlesAllowanceAssignments;
use App\Http\Controllers\Api\Api\AllowanceGovernance\Concerns\HandlesAllowanceReports;



class HcmEmployeeAllowanceGovernanceController extends Controller

{
    use ChecksPermissions;
    use HandlesAllowancePolicies;
    use HandlesAllowanceAssignments;
    use HandlesAllowanceReports;
}