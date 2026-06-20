<?php

namespace App\Http\Controllers\Api\Termination;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;

use App\Http\Controllers\Api\Termination\Concerns\HandlesTerminationChecklistItems;
use App\Http\Controllers\Api\Termination\Concerns\HandlesTerminationCrud;
use App\Http\Controllers\Api\Termination\Concerns\HandlesTerminationNormalizers;
use App\Http\Controllers\Api\Termination\Concerns\HandlesTerminationSettlementCalculation;
use App\Http\Controllers\Api\Termination\Concerns\HandlesTerminationSettlementPreview;
use App\Http\Controllers\Api\Termination\Concerns\HandlesTerminationWorkflow;
use App\Http\Controllers\Api\Api\Termination\Concerns\HandlesTerminationCrud;
use App\Http\Controllers\Api\Api\Termination\Concerns\HandlesTerminationSettlementPreview;
use App\Http\Controllers\Api\Api\Termination\Concerns\HandlesTerminationSettlementCalculation;

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