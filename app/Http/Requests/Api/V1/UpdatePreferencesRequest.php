<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * PUT /api/v1/preferences
 *
 * Unit values are checked by the repository so aliases resolve and inactive
 * units are rejected. Everything else is validated here.
 */
class UpdatePreferencesRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'units'               => ['sometimes', 'array'],
            'units.*'             => ['required', 'string', 'max:40'],
            'measurement_system'  => ['sometimes', 'nullable', 'string', Rule::in(array_keys(config('unit_formatting.measurement_systems', [])))],
            'locale'              => ['sometimes', 'nullable', 'string', 'max:10'],
            'decimal_separator'   => ['sometimes', 'nullable', 'string', 'max:4'],
            'thousands_separator' => ['sometimes', 'nullable', 'string', 'max:4'],
            'display_precision'   => ['sometimes', 'nullable', 'integer', 'min:0', 'max:12'],
        ];
    }
}
