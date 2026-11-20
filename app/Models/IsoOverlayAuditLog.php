<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only record of every administrative change to overlays.
 */
class IsoOverlayAuditLog extends Model
{
    protected $table = 'iso_overlay_audit_log';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (IsoOverlayAuditLog $log) {
            $log->created_at = $log->created_at ?? now();
        });

        static::updating(function () {
            throw new \RuntimeException('Audit log entries cannot be changed.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Audit log entries cannot be deleted.');
        });
    }
}
