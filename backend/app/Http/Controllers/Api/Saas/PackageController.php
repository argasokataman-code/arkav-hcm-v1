<?php



namespace App\Http\Controllers\Api\Saas;



use App\Http\Controllers\Controller;

use App\Models\FeatureClassification;

use App\Models\Package;

use App\Models\PackageAddon;

use App\Models\PackageFeature;

use App\Services\PackageFeatureCatalogRuntimeService;

use Illuminate\Database\Eloquent\Builder;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;

use Illuminate\Support\Str;



class PackageController extends Controller

{
    use ChecksPermissions;
    use HandlesPackageCrud;
    use HandlesPackageFeatures;
    use HandlesPackageAddons;
}