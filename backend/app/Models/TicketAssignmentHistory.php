<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAssignmentHistory extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'ticket_id',
        'actor_user_id',
        'from_assignee_user_id',
        'to_assignee_user_id',
        'note',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function fromAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_assignee_user_id');
    }

    public function toAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_assignee_user_id');
    }
}
