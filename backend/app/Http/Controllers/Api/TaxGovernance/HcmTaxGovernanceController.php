<?php

namespace App\Http\Controllers\Api\TaxGovernance;

use App\Events\TaxGovernancePolicyTransitioned;
use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Api\TaxGovernance\Concerns\HandlesPlatformTaxGovernance;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmBillingTaxPolicy;
use App\Models\HcmSalaryComponent;
use App\Models\HcmTaxGovernanceBreakGlassRequest;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\HcmTaxGovernancePolicyEvent;
use App\Models\HcmTaxGovernanceProjection;
use App\Models\HcmTaxGovernanceAnomaly;
use App\Models\User;
use App\Services\BillingTaxCalculationService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

use App\Http\Controllers\Api\TaxGovernance\Concerns\HandlesTaxPolicyCrud;

use App\Http\Controllers\Api\TaxGovernance\Concerns\HandlesTaxAuditReports;

use App\Http\Controllers\Api\TaxGovernance\Concerns\HandlesTaxBreakGlass;

use App\Http\Controllers\Api\TaxGovernance\Concerns\HandlesTaxAnomalyManagement;

use App\Http\Controllers\Api\TaxGovernance\Concerns\HandlesTaxSharedUtilities;
use App\Http\Controllers\Api\Api\TaxGovernance\Concerns\HandlesPlatformTaxGovernance;
use App\Http\Controllers\Api\Api\TaxGovernance\Concerns\HandlesTaxPolicyCrud;
use App\Http\Controllers\Api\Api\TaxGovernance\Concerns\HandlesTaxAuditReports;
use App\Http\Controllers\Api\Api\TaxGovernance\Concerns\HandlesTaxBreakGlass;
use App\Http\Controllers\Api\Api\TaxGovernance\Concerns\HandlesTaxAnomalyManagement;
use App\Http\Controllers\Api\Api\TaxGovernance\Concerns\HandlesTaxSharedUtilities;

class HcmTaxGovernanceController extends Controller
{
    use ChecksPermissions;
    use HandlesPlatformTaxGovernance;
    use HandlesTaxPolicyCrud;
    use HandlesTaxAuditReports;
    use HandlesTaxBreakGlass;
    use HandlesTaxAnomalyManagement;
    use HandlesTaxSharedUtilities;
}