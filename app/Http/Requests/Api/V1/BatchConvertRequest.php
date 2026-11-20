<?php

namespace App\Http\Requests\Api\V1;

/**
 * POST /api/v1/convert/batch
 *
 * The size limit is enforced here so an oversized batch is rejected before any
 * conversion runs. BatchConverter enforces it again, which is deliberate: the
 * service must not depend on a controller having checked first.
 *
 * Each item's value carries the same magnitude bound as the single endpoint,
 * for the same reason.
 *
 * Category is deliberately NOT restricted to the configured list here, unlike
 * the single-conversion request. A batch must report an unknown category as a
 * per-item failure rather than rejecting the whole request, which is the
 * behaviour the specification asks for and the reference documents.
 */
class BatchConvertRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $max = (int) config('units.max_batch_size', 500);
        $mag = ConvertRequest::MAX_MAGNITUDE;

        return [
            'conversions'            => ['required', 'array', 'min:1', 'max:' . $max],
            'conversions.*.value'    => ['required', 'numeric', 'between:-' . $mag . ',' . $mag],
            'conversions.*.category' => ['required', 'string', 'max:40'],
            'conversions.*.from'     => ['required', 'string', 'max:40'],
            'conversions.*.to'       => ['required', 'string', 'max:40'],
        ];
    }
}
