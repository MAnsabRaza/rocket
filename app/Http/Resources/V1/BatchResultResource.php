<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A batch outcome with per-item status.
 *
 * Caller keys are preserved so results can be matched to what was sent, which
 * is why array_map is used rather than a resource collection that would
 * reindex them.
 */
class BatchResultResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'total'     => $this->count(),
            'succeeded' => $this->successCount(),
            'failed'    => $this->failureCount(),
            'items'     => array_map(
                fn ($result) => (new ConversionResultResource($result))->resolve($request),
                $this->results
            ),
        ];
    }
}
