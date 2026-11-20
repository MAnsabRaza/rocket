<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpIdMapping extends Model
{
    use HasFactory;

    protected $table = 'erp_id_mappings';

    protected $fillable = [
        'vendor_id', 'entity_type', 'local_id', 'remote_id', 'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(\App\User::class, 'vendor_id');
    }
}
