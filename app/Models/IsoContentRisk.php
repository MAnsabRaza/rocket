<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IsoContentRisk extends Model
{
    use SoftDeletes;

    protected $table = 'iso_content_risks';
    protected $guarded = ['id'];

    protected $casts = [
        'name'                => 'array',
        'description'         => 'array',
        'causes'              => 'array',
        'consequences'        => 'array',
        'mitigations'         => 'array',
        'controls'            => 'array',
        'is_active'           => 'boolean',
        'version' => 'integer',
        'version_history' => 'array',
        'default_probability' => 'integer',
        'default_impact'      => 'integer',
        'probability_scale'   => 'integer',
        'impact_scale'        => 'integer',
        'levels'              => 'array',
        'residual'            => 'array',
    ];

    public function scopeActive($q)      { return $q->where('is_active', true); }
    public function scopeCategory($q,$c) { return $q->where('category', $c); }
    public function scopeByKey($q, $k)   { return $q->where('technical_key', $k); }
}