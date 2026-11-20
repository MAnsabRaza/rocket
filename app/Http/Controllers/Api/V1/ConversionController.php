<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BatchConvertRequest;
use App\Http\Requests\Api\V1\ConvertRequest;
use App\Http\Resources\V1\BatchResultResource;
use App\Http\Resources\V1\ConversionResultResource;
use App\Services\UnitConversion\BatchConverter;
use App\Services\UnitConversion\Exceptions\UnitConversionException;
use App\Services\UnitConversionService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/convert and /api/v1/convert/batch
 *
 * Thin wrappers over service code delivered and tested in M2. convertResult()
 * never throws, so a failed conversion is reported through the result object
 * rather than an exception trace.
 *
 * Chained conversion exists in BatchConverter::convertChain() and is tested,
 * but no endpoint exposes it because the specification's endpoint list does not
 * include one.
 */
class ConversionController extends ApiController
{
    public function convert(ConvertRequest $request, UnitConversionService $service): JsonResponse
    {
        $result = $service->convertResult(
            (float) $request->input('value'),
            $request->input('category'),
            $request->input('from'),
            $request->input('to')
        );

        $payload = (new ConversionResultResource($result))->resolve($request);

        if ($result->successful()) {
            return $this->ok('converted', trans('public.retrieved'), $payload);
        }

        return $this->fail($result->outcome->value, $result->message ?? 'Conversion failed.', $payload);
    }

    /**
     * Per-item status, per the specification.
     *
     * A well-formed batch returns HTTP 200 even when individual items fail. One
     * bad item must not invalidate the rest, so failure is reported per item
     * inside the payload rather than by the status code. The counts make a
     * partial failure obvious without inspecting every item.
     */
    public function batch(BatchConvertRequest $request, BatchConverter $converter): JsonResponse
    {
        try {
            $result = $converter->convertBatch($request->input('conversions'));
        } catch (UnitConversionException $e) {
            return $this->fail('batch_rejected', $e->getMessage());
        }

        return $this->ok(
            'converted',
            trans('public.retrieved'),
            (new BatchResultResource($result))->resolve($request)
        );
    }
}
