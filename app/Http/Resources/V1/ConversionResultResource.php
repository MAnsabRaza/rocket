<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A conversion outcome.
 *
 * Replaces ConversionResult::toArray() on the API boundary. The domain object
 * keeps its own array form for internal use; how it is presented over HTTP is
 * decided here, so a change to the wire format cannot silently alter the
 * service layer the M2 baseline depends on.
 *
 * value is null on failure and never falls back to the input.
 */
class ConversionResultResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'outcome'   => $this->outcome->value,
            'value'     => $this->value,
            'input'     => $this->input,
            'category'  => $this->category,
            'from_unit' => $this->fromUnit,
            'to_unit'   => $this->toUnit,
            'precision' => $this->precision,
            'message'   => $this->message,
        ];
    }
}
