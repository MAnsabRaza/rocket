<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * POST /api/v1/convert
 *
 * Units are validated by the service rather than here, because the service
 * resolves aliases: "lbs" is a valid input that canonicalises to "lb". A rule
 * listing canonical names only would reject aliases the rest of the system
 * accepts.
 *
 * The value is bounded. Unbounded, a magnitude near the top of the double range
 * multiplied by a conversion factor produces INF, which json_encode cannot
 * represent, so the endpoint whose purpose is defined errors returned a 500
 * instead. The bound is far above any physical measurement this system stores.
 */
class ConvertRequest extends ApiFormRequest
{
    public const MAX_MAGNITUDE = 1.0e15;

    public function rules(): array
    {
        return [
            'value'    => ['required', 'numeric', 'between:-' . self::MAX_MAGNITUDE . ',' . self::MAX_MAGNITUDE],
            'category' => ['required', 'string', Rule::in(array_keys(config('units.conversions', [])))],
            'from'     => ['required', 'string', 'max:40'],
            'to'       => ['required', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.between' => 'The value is outside the range this API converts.',
        ];
    }
}
