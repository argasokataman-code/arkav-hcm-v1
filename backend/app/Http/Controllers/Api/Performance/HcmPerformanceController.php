<?php



namespace App\Http\Controllers\Api\Performance;



use App\Http\Controllers\Api\Concerns\ChecksPermissions;

use App\Http\Controllers\Controller;

use App\Models\CompanyUser;

use App\Models\EmployeeProfile;

use App\Models\LeaveRequest;

use App\Models\PerformanceCycle;

use App\Models\PerformanceIndicatorItem;

use App\Models\PerformanceIndicatorTemplate;

use App\Models\PerformanceGoal;

use App\Models\PerformanceGoalType;

use App\Models\PerformanceReview;

use App\Models\PerformanceReviewScore;

use App\Models\User;

use App\Notifications\PerformanceReviewCreatedNotification;

use App\Notifications\PerformanceReviewSubmittedNotification;

use App\Notifications\PerformanceReviewManagerReviewedNotification;

use App\Notifications\PerformanceReviewFinalizedNotification;

use Illuminate\Database\Eloquent\Builder;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Illuminate\Validation\ValidationException;



class HcmPerformanceController extends Controller

{
    use ChecksPermissions;
    use HandlesPerformanceGoals;
    use HandlesPerformanceIndicators;
    use HandlesPerformanceCycles;
    use HandlesPerformanceReviews;
}