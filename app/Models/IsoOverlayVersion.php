<?php

namespace App\Models;

use App\Services\IsoOverlay\ImmutableVersionException;
use Illuminate\Database\Eloquent\Model;

/**
 * Copy-on-write: only a draft may change. The single change allowed after
 * publication is published -> archived, touching status and the archive stamps.
 */
class IsoOverlayVersion extends Model
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';

    /** Content definition used for new publications: items and overlay details. */
    public const HASH_SCHEME = 2;

    /** The overlay details recorded in every version (meta). */
    public const DETAIL_FIELDS = ['name', 'description', 'framework_label', 'flinkiso_standard_id'];

    protected $table = 'iso_overlay_versions';

    protected $guarded = ['id'];

    protected $casts = [
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted()
    {
        static::updating(function (IsoOverlayVersion $version) {
            $was = $version->getOriginal('status');

            if ($was === self::DRAFT) {
                return;
            }

            $dirty = array_keys($version->getDirty());
            $allowed = ['status', 'archived_at', 'archived_by', 'updated_at'];
            $isArchiving = $was === self::PUBLISHED && $version->status === self::ARCHIVED;

            if (!$isArchiving || array_diff($dirty, $allowed)) {
                throw new ImmutableVersionException("Version {$version->id} is {$was} and cannot be changed.");
            }
        });

        static::deleting(function (IsoOverlayVersion $version) {
            if ($version->getOriginal('status') !== self::DRAFT) {
                throw new ImmutableVersionException("Version {$version->id} is not a draft and cannot be deleted.");
            }
        });
    }

    public function overlay()
    {
        return $this->belongsTo(IsoOverlay::class, 'overlay_id');
    }

    public function items()
    {
        return $this->hasMany(IsoOverlayItem::class, 'version_id')->orderBy('type')->orderBy('sort_order')->orderBy('key');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    /**
     * Content definition this version's hash is checked against. A draft always uses
     * the current one; a version published before details were recorded uses 1.
     */
    public function hashScheme(): int
    {
        return $this->isDraft() ? self::HASH_SCHEME : (int)($this->hash_scheme ?: 1);
    }

    public function hasRecordedDetails(): bool
    {
        return is_array($this->meta);
    }

    public function localizedDetail(string $field, ?string $locale = null): ?string
    {
        return IsoOverlay::pickLocale($this->meta[$field] ?? null, $locale);
    }

    /**
     * One canonical form of the overlay details, used for storage, hashing and
     * comparison: fixed keys in sorted order, blank values as null, labels sorted
     * by locale with blank locales removed.
     */
    public static function canonicalMeta($meta): array
    {
        $meta = is_array($meta) ? $meta : [];
        $label = function ($value): array {
            $clean = [];
            foreach (is_array($value) ? $value : [] as $locale => $text) {
                if (is_string($text) && trim($text) !== '') {
                    $clean[(string)$locale] = trim($text);
                }
            }
            ksort($clean);

            return $clean;
        };
        $text = fn($value) => $value === null || trim((string)$value) === '' ? null : trim((string)$value);
        $description = $label($meta['description'] ?? null);

        return [
            'description' => $description ?: null,
            'flinkiso_standard_id' => $text($meta['flinkiso_standard_id'] ?? null),
            'framework_label' => $text($meta['framework_label'] ?? null),
            'name' => $label($meta['name'] ?? null),
        ];
    }
}
