<?php

namespace App\Models;

use App\Casts\EncryptedOrPlaintext;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeeTaxProfile extends Model
{
    use AssignsUuid, SoftDeletes;


    protected $fillable = [
        'employee_id',
        'npwp',
        'tax_status',
        'ptkp_status',
        'effective_date',
        'end_date',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        // UU PDP Encryption — C5
        // UU PDP Encryption — C5
        // Using custom EncryptedOrPlaintext cast for backward compatibility with existing plaintext data
        'npwp' => EncryptedOrPlaintext::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
