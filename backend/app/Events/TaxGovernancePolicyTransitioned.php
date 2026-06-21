<?php

namespace App\Events;

use App\Models\HcmTaxGovernancePolicy;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaxGovernancePolicyTransitioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HcmTaxGovernancePolicy $policy,
        public string $previousStatus,
        public string $newStatus,
        public int $actorUserId,
    ) {}
}
