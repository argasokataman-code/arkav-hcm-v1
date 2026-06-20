<?php



namespace App\Http\Controllers\Api\Dashboard;



use App\Http\Controllers\Api\Concerns\ChecksPermissions;

use App\Http\Controllers\Controller;

use App\Models\AttendanceRecord;

use App\Models\Department;

use App\Models\EmployeeProfile;

use App\Models\HcmLeaveTypeSetting;

use App\Models\HcmManualActivity;

use App\Models\HcmPayrollLine;

use App\Models\HcmPayrollPeriod;

use App\Models\HcmPayrollRun;

use App\Models\HcmPromotion;

use App\Models\HcmResignation;

use App\Models\HcmTermination;

use App\Models\HcmTraining;

use App\Models\Holiday;

use App\Models\Company;

use App\Models\Invoice;

use App\Models\LeaveRequest;

use App\Models\Subscription;

use App\Models\OvertimeRequest;

use App\Models\PerformanceReview;

use App\Models\User;

use App\Support\Exports\TabularExportResponse;

use Carbon\Carbon;

use Illuminate\Database\Eloquent\Builder;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Validation\Rule;

use Symfony\Component\HttpFoundation\StreamedResponse;

use App\Http\Controllers\Api\Dashboard\Concerns\HandlesDashboardCrud;

use App\Http\Controllers\Api\Dashboard\Concerns\HandlesDashboardReports;



class HcmDashboardController extends Controller

{
    use ChecksPermissions;
    use HandlesDashboardCrud;
    use HandlesDashboardReports;
}