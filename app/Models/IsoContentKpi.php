<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IsoContentKpi extends Model
{
    use SoftDeletes;

    protected $table = 'iso_content_kpis';
    protected $guarded = ['id'];

    protected $casts = [
        'name'        => 'array',
        'description' => 'array',
        'thresholds'  => 'array',
        'roles'       => 'array',
        'source'      => 'array',
        'is_active'   => 'boolean',
        'version' => 'integer',
        'version_history' => 'array',
    ];

    public function scopeActive($q)      { return $q->where('is_active', true); }
    public function scopeCategory($q,$c) { return $q->where('category', $c); }
    public function scopeByKey($q, $k)   { return $q->where('technical_key', $k); }
}