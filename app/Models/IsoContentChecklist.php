<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IsoContentChecklist extends Model
{
    use SoftDeletes;

    protected $table = 'iso_content_checklists';
    protected $guarded = ['id'];

    protected $casts = [
        'name'        => 'array',
        'description' => 'array',
        'schema'      => 'array',
        'scoring'     => 'array',
        'is_active'   => 'boolean',
        'version' => 'integer',
        'version_history' => 'array',
    ];

    public function scopeActive($q)    { return $q->where('is_active', true); }
    public function scopeByKey($q, $k) { return $q->where('technical_key', $k); }

    public function allQuestions(): array
    {
        $out = [];
        foreach ($this->schema ?? [] as $section) {
            foreach ($section['questions'] ?? [] as $q) {
                $out[] = $q + ['_section' => $section['key'] ?? null];
            }
        }
        return $out;
    }

    public function questionCount(): int
    {
        return count($this->allQuestions());
    }
}