<?php

namespace App\Http\Controllers\Api\Employee\Concerns;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeProfile;
use App\Models\HcmRole;
use App\Models\HcmScheduleTiming;
use App\Models\HcmUserRole;
use App\Modelsser;
use App\Services\EmployeeCountValidator;
use Database\Seeders\HcmUserManagementSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

trait HandlesEmployeeCoreBulk
{}
