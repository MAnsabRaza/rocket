<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\UnitCategoryResource;
use App\Services\UnitConversion\UnitFormatter;
use App\Services\UnitConversion\UnitResolver;
use App\Services\UnitConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/units
 *
 * Lists selectable units per category. Inactive units are excluded, so a unit
 * deactivated under D-08 is absent from the API exactly as it is absent from
 * the interface. Converting a value already stored in such a unit still works;
 * it simply cannot be chosen.
 *
 * Labels are category-scoped: mass lb reads Pounds, force lb reads
 * Pounds-force. A locale may be supplied as a query parameter and is used
 * where a translated name exists. Accept-Language is not consulted, which is
 * deliberate for an internal API where the caller states the locale it wants.
 *
 * The locale parameter is bounded and shape-checked before use. It reaches a
 * translation lookup, and an unbounded caller-supplied string has no business
 * getting that far unexamined.
 */
class UnitController extends ApiController
{
    public function index(
        Request $request,
        UnitConversionService $service,
        UnitResolver $resolver,
        UnitFormatter $formatter
    ): JsonResponse {
        $locale     = $this->locale($request);
        $categories = [];

        foreach ($service->getUnitTypes() as $category) {
            $units = [];

            foreach ($resolver->activeUnits($category) as $unit) {
                $default   = $resolver->config()['display_labels'][$unit] ?? $unit;
                $localized = $formatter->labelFor($unit, 2.0, false, false, $locale);

                $units[] = [
                    'code'        => $unit,
                    'label'       => $localized !== $default ? $localized : $resolver->label($category, $unit),
                    'short_label' => $resolver->label($category, $unit, true),
                    'is_base'     => $unit === $service->getBaseUnit($category),
                ];
            }

            $categories[] = [
                'category'  => $category,
                'base_unit' => $service->getBaseUnit($category),
                'precision' => $resolver->precisionFor($category),
                'units'     => $units,
            ];
        }

        return $this->ok('retrieved', trans('public.retrieved'), [
            'categories' => array_map(
                fn (array $c) => (new UnitCategoryResource($c))->resolve($request),
                $categories
            ),
            'rounding_mode'  => $resolver->roundingMode(),
            'max_batch_size' => (int) config('units.max_batch_size', 500),
        ]);
    }

    /** A locale tag, or null. Anything that is not one is treated as absent. */
    private function locale(Request $request): ?string
    {
        $locale = $request->query('locale');

        if (! is_string($locale) || $locale === '') {
            return null;
        }

        return preg_match('/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})?$/', $locale) === 1
            ? $locale
            : null;
    }
}
