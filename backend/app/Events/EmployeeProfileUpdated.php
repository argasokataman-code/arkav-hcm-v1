<?php

namespace App\Events;

use App\Models\EmployeeProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeProfileUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $changedFields  List of field names that changed
     */
    public function __construct(
        public readonly EmployeeProfile $profile,
        public readonly array $changedFields = [],
        public readonly ?string $actorUuid = null,
    ) {}
}
