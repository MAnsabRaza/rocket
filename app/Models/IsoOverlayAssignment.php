<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsoOverlayAssignment extends Model
{
    protected $table = 'iso_overlay_assignments';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function overlay()
    {
        return $this->belongsTo(IsoOverlay::class, 'overlay_id');
    }
}
