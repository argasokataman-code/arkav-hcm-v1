<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'asset_category_id',
        'asset_code',
        'name',
        'brand',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_price',
        'condition',
        'status',
        'location',
        'notes',
        'warranty_start_date',
        'warranty_end_date',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'warranty_start_date' => 'date',
        'warranty_end_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->where('active_token', 'active');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AssetLog::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssetAttachment::class);
    }
}