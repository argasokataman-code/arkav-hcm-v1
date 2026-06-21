<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $subscriptionId,
        public ?int $actorUserId = null,
    ) {}
}
