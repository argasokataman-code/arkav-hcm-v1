<?php



namespace App\Http\Controllers\Api\Auth;



use App\Http\Controllers\Controller;

use App\Http\Controllers\Api\Auth\Concerns\HandlesAuthCore;
use App\Http\Controllers\Api\Auth\Concerns\HandlesAuthProfile;
use App\Http\Controllers\Api\Auth\Concerns\HandlesAuthCompany;
use App\Http\Controllers\Api\Auth\Concerns\HandlesAuthPermissions;
use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Mail\RegisterSuccessMailable;

use App\Models\AuthToken;

use App\Models\Company;

use App\Models\CompanySetting;

use App\Models\CompanyUser;

use App\Models\EmployeeProfile;

use App\Models\Invoice;

use App\Models\HcmPermission;

use App\Models\User;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\RateLimiter;

use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Str;

use Illuminate\Validation\Rule;
use App\Http\Controllers\Api\Api\Auth\Concerns\HandlesAuthCore;
use App\Http\Controllers\Api\Api\Auth\Concerns\HandlesAuthProfile;
use App\Http\Controllers\Api\Api\Auth\Concerns\HandlesAuthCompany;
use App\Http\Controllers\Api\Api\Auth\Concerns\HandlesAuthPermissions;



class AuthController extends Controller

{
    use ChecksPermissions;
    use HandlesAuthCore;
    use HandlesAuthProfile;
    use HandlesAuthCompany;
    use HandlesAuthPermissions;
}