<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IsoContentWorkflow extends Model
{
    use SoftDeletes;

    protected $table = 'iso_content_workflows';
    protected $guarded = ['id'];

    protected $casts = [
        'name'        => 'array',
        'description' => 'array',
        'states'      => 'array',
        'transitions' => 'array',
        'roles'       => 'array',
        'settings'    => 'array',
        'is_active'   => 'boolean',
        'version' => 'integer',
        'version_history' => 'array',
    ];

    public function scopeActive($q)      { return $q->where('is_active', true); }
    public function scopeCategory($q, $c) { return $q->where('category', $c); }
    public function scopeByKey($q, $k)   { return $q->where('technical_key', $k); }

    public function initialState(): ?array
    {
        return collect($this->states)->firstWhere('kind', 'initial')
            ?? ($this->states[0] ?? null);
    }

    public function transitionsFrom(string $stateKey): array
    {
        return array_values(array_filter(
            $this->transitions ?? [],
            fn($t) => ($t['from'] ?? null) === $stateKey
        ));
    }
}