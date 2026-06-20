<?php

namespace App\Http\Controllers\Api\Leave\Concerns;

use App\Models\CompanyUser;
use App\Models\EmployeeLeaveBalance;
use App\Models\HcmLeaveTypeSetting;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeaveLedger;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyAssignment;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestBreakdown;
use App\Models\LeaveType;
use App\Modelsser;
use App\Models\AttendanceRecord;
use App\Support\Exports\TabularExportResponse;
use App\Services\Hcm\LeaveLedgerService;
use App\Services\Hcm\LeaveWorkingDayCalculator;
use App\Notifications\LeaveRequestedNotification;
use App\Notifications\LeaveApprovedNotification;
use App\Notifications\LeaveRejectedNotification;
use App\Notifications\LeaveCancelledNotification;
use App\Notifications\LeaveApprovalRequestedNotification;
use App\Notifications\LeaveNextApproverNotification;
use App\Services\ApprovalConfigService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use \App\Http\Controllers\Api\Concerns\LogsHcmActivity;

trait HandlesLeaveRequestApproval
{

}
