<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureClassification extends Model
{
    protected $table = 'feature_classifications';

    protected $fillable = [
        'feature_code',
        'tier',
    ];

    public const TIER_MVP = 'mvp';
    public const TIER_ADDON = 'addon';

    public $timestamps = true;
}
