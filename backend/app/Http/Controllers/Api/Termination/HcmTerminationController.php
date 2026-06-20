<?php

namespace App\Http\Controllers\Api\Termination;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;

class HcmTerminationController extends Controller
{
    use ChecksPermissions;
    use HandlesTerminationChecklistItems;
    use HandlesTerminationNormalizers;
    use HandlesTerminationWorkflow;
    use HandlesTerminationCrud;
    use HandlesTerminationSettlementPreview;
    use HandlesTerminationSettlementCalculation;
}