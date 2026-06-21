<?php

namespace App\Models;

use App\Casts\EncryptedOrPlaintext;
use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeBenefit extends Model
{
    use AssignsUuid, SoftDeletes;

    protected $table = 'employee_benefits';

    protected $fillable = [
        'employee_id',
        'bpjs_kesehatan_no',
        'bpjs_ketenagakerjaan_no',
        'effective_date',
        'end_date',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        // UU PDP Encryption — C5
        // UU PDP Encryption — C5
        // Using custom EncryptedOrPlaintext cast for backward compatibility with existing plaintext data
        'bpjs_kesehatan_no' => EncryptedOrPlaintext::class,
        'bpjs_ketenagakerjaan_no' => EncryptedOrPlaintext::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
