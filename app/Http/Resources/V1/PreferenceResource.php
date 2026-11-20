<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The caller's preferences.
 *
 * units carries an entry for every configured category, falling back to the
 * base unit. null inside format means not set, so the locale default applies;
 * it does not mean empty.
 */
class PreferenceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'units'  => $this['units'],
            'format' => [
                'measurement_system'  => $this['format']['measurement_system'] ?? null,
                'locale'              => $this['format']['locale'] ?? null,
                'decimal_separator'   => $this['format']['decimal_separator'] ?? null,
                'thousands_separator' => $this['format']['thousands_separator'] ?? null,
                'display_precision'   => $this['format']['display_precision'] ?? null,
            ],
            'available_systems' => array_values($this['available_systems']),
        ];
    }
}
