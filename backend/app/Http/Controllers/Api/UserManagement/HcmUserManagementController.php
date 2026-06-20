<?php



namespace App\Http\Controllers\Api\UserManagement;



use App\Http\Controllers\Api\Concerns\ChecksPermissions;

use App\Http\Controllers\Controller;

use App\Models\CompanyUser;

use App\Models\HcmPermission;

use App\Models\HcmRole;

use App\Models\HcmUserRole;

use App\Models\HcmUserRoleAudit;

use App\Support\Exports\TabularExportResponse;

use App\Models\User;

use App\Support\Hcm\HcmFeatureEntitlementResolver;

use Database\Seeders\HcmUserManagementSeeder;

use Illuminate\Database\Eloquent\Builder;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Str;

use Illuminate\Validation\Rule;



class HcmUserManagementController extends Controller

{
    use ChecksPermissions;
    use HandlesUserManagementUsers;
    use HandlesUserManagementRoles;
    use HandlesUserManagementAssignments;
}