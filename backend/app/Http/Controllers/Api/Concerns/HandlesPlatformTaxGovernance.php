<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Http\Controllers\Api\TaxGovernance\Concerns\HandlesPlatformTaxGovernance as TaxGovernancePlatformTrait;

trait HandlesPlatformTaxGovernance
{
    use TaxGovernancePlatformTrait;
}
