<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single selectable unit.
 *
 * Presentation lives here rather than in the controller, so the JSON contract
 * has one owner. Labels are supplied already resolved, because resolving them
 * needs the category: lb is Pounds under mass and Pounds-force under force.
 */
class UnitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'code'        => (string) $this['code'],
            'label'       => (string) $this['label'],
            'short_label' => (string) $this['short_label'],
            'is_base'     => (bool) $this['is_base'],
        ];
    }
}
