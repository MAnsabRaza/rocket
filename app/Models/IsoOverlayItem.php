<?php

namespace App\Models;

use App\Services\IsoOverlay\ImmutableVersionException;
use Illuminate\Database\Eloquent\Model;

/**
 * An item can only be created, changed or removed while its version is a draft.
 */
class IsoOverlayItem extends Model
{
    protected $table = 'iso_overlay_items';

    protected $guarded = ['id'];

    protected $casts = [
        'label' => 'array',
        'data' => 'array',
        'sort_order' => 'integer',
    ];

    protected static function booted()
    {
        $guard = function (IsoOverlayItem $item) {
            $versionId = $item->getOriginal('version_id') ?? $item->version_id;
            $status = IsoOverlayVersion::query()->whereKey($versionId)->value('status');

            if ($status !== IsoOverlayVersion::DRAFT) {
                throw new ImmutableVersionException("Items of version {$versionId} cannot be changed (status: " . ($status ?? 'missing') . ').');
            }

            if ($item->exists && $item->isDirty('version_id')) {
                throw new ImmutableVersionException('An item cannot be moved to another version.');
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }

    public function version()
    {
        return $this->belongsTo(IsoOverlayVersion::class, 'version_id');
    }

    public function localizedLabel(?string $locale = null): ?string
    {
        return IsoOverlay::pickLocale($this->label, $locale);
    }
}
