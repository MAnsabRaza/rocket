<?php

namespace App\Models;

use App\Services\IsoOverlay\ImmutableVersionException;
use Illuminate\Database\Eloquent\Model;

/**
 * The overlay record. Its details (name, description, framework label, FlinkISO
 * standard) are recorded in each version and hashed with it; the copy kept here
 * is only the display copy of one version's details (see detailsVersion()), and
 * the model refuses any change that does not match that version. The code never
 * changes. current_version_id and is_active are operational state, audited.
 */
class IsoOverlay extends Model
{
    protected $table = 'iso_overlays';

    protected $guarded = ['id'];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::updating(function (IsoOverlay $overlay) {
            if ($overlay->isDirty('code')) {
                throw new ImmutableVersionException("The code of overlay {$overlay->id} cannot be changed.");
            }

            $dirty = array_intersect(IsoOverlayVersion::DETAIL_FIELDS, array_keys($overlay->getDirty()));
            if (!$dirty) {
                return;
            }

            $source = $overlay->detailsVersion();
            $expected = $source && $source->hasRecordedDetails() ? IsoOverlayVersion::canonicalMeta($source->meta) : null;
            $actual = IsoOverlayVersion::canonicalMeta($overlay->only(IsoOverlayVersion::DETAIL_FIELDS));

            if ($expected === null || $expected !== $actual) {
                throw new ImmutableVersionException("The details of overlay {$overlay->id} are recorded in its versions and can only be changed in a draft version.");
            }
        });
    }

    /**
     * The version whose details this record displays: the current version, else the
     * latest published or archived version, else the latest draft.
     */
    public function detailsVersion(): ?IsoOverlayVersion
    {
        if ($this->current_version_id) {
            $current = IsoOverlayVersion::where('overlay_id', $this->id)->find($this->current_version_id);
            if ($current) {
                return $current;
            }
        }

        return IsoOverlayVersion::where('overlay_id', $this->id)->where('status', '!=', IsoOverlayVersion::DRAFT)->orderByDesc('version_no')->first()
            ?? IsoOverlayVersion::where('overlay_id', $this->id)->orderByDesc('version_no')->first();
    }

    public function draftVersion(): ?IsoOverlayVersion
    {
        return IsoOverlayVersion::where('overlay_id', $this->id)->where('status', IsoOverlayVersion::DRAFT)->orderByDesc('version_no')->first();
    }

    public function versions()
    {
        return $this->hasMany(IsoOverlayVersion::class, 'overlay_id')->orderBy('version_no');
    }

    public function currentVersion()
    {
        return $this->belongsTo(IsoOverlayVersion::class, 'current_version_id');
    }

    public function assignments()
    {
        return $this->hasMany(IsoOverlayAssignment::class, 'overlay_id');
    }

    public function localized(string $field, ?string $locale = null): ?string
    {
        return static::pickLocale($this->{$field}, $locale);
    }

    /**
     * Returns the value for the requested locale, falling back to the configured
     * fallback locale, then to the first value present.
     */
    public static function pickLocale($values, ?string $locale = null): ?string
    {
        if (!is_array($values) || empty($values)) {
            return null;
        }

        $locale = strtolower($locale ?? app()->getLocale());
        $fallback = config('iso_overlay.fallback_locale', 'en');

        foreach ([$locale, $fallback] as $candidate) {
            if (isset($values[$candidate]) && $values[$candidate] !== '') {
                return $values[$candidate];
            }
        }

        return reset($values) ?: null;
    }
}
