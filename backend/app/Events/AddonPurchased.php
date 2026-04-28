<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AddonPurchased
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $purchaseTransactionId,
        public ?int $actorUserId = null,
    ) {
    }
}
