<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

/**
 * Validation failures return the same envelope as every other v1 response.
 *
 * Laravel's default 422 body uses a different shape, which would leave the API
 * with two error formats: one for validation and one for everything else.
 *
 * Failures are logged. The specification's observability requirement names API
 * validation failures explicitly, and without this a caller sending malformed
 * requests leaves no trace on the server at all.
 */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        Log::notice('unit-api: validation failed', [
            'route'   => optional($this->route())->getName(),
            'user_id' => optional($this->user())->id,
            'fields'  => array_keys($validator->errors()->toArray()),
        ]);

        throw new HttpResponseException(response()->json([
            'success' => 0,
            'status'  => 'validation_failed',
            'message' => $validator->errors()->first(),
            'data'    => ['errors' => $validator->errors()->toArray()],
        ], 422));
    }
}
