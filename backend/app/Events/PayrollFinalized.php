<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollFinalized
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $payrollRunId,
        public ?int $actorUserId = null,
    ) {}
}
