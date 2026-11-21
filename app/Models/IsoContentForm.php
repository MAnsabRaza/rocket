<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IsoContentForm extends Model
{
    use SoftDeletes;

    protected $table = 'iso_content_forms';
    protected $guarded = ['id'];

    protected $casts = [
        'name'        => 'array',
        'description' => 'array',
        'schema'      => 'array',
        'approval'    => 'array',
        'is_active'   => 'boolean',
        'version' => 'integer',
        'version_history' => 'array',
    ];

    public function scopeActive($q)      { return $q->where('is_active', true); }
    public function scopeCategory($q,$c) { return $q->where('category', $c); }
    public function scopeByKey($q, $k)   { return $q->where('technical_key', $k); }

    public function allFields(): array
    {
        $out = [];
        foreach ($this->schema ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $f) {
                $out[] = $f + ['_section' => $section['key'] ?? null];
            }
        }
        return $out;
    }
}