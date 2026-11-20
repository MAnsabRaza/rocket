<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One unit category with its selectable units.
 *
 * Units are already filtered to active ones, so a unit deactivated under D-08
 * never reaches this layer.
 */
class UnitCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'category'  => (string) $this['category'],
            'base_unit' => $this['base_unit'],
            'precision' => (int) $this['precision'],
            'units'     => array_map(
                fn (array $unit) => (new UnitResource($unit))->resolve($request),
                $this['units']
            ),
        ];
    }
}
