<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UpdatePreferencesRequest;
use App\Http\Resources\V1\PreferenceResource;
use App\Services\UnitConversion\Exceptions\UnitConversionException;
use App\Services\UnitConversion\PreferenceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GET and PUT /api/v1/preferences
 *
 * Reads and writes through the same repository as the web and panel flows, so
 * the API cannot drift from the interface. Legacy columns stay read-only
 * fallback here as everywhere else.
 *
 * Both endpoints act on the authenticated caller only. No route accepts a user
 * identifier, so one caller cannot reach another's preferences by construction
 * rather than by a check that could be forgotten.
 *
 * The write is transactional. Without that, a request naming several categories
 * of which one is invalid persisted the categories before it and then returned
 * 422, so a caller was told the request failed while part of it had been
 * applied. Both this class and the API reference state that a rejected write
 * changes nothing, and that is now true rather than intended.
 */
class PreferenceController extends ApiController
{
    public function show(Request $request, PreferenceRepository $preferences): JsonResponse
    {
        return $this->ok(
            'retrieved',
            trans('public.retrieved'),
            $this->payload($request, $request->user(), $preferences)
        );
    }

    public function update(UpdatePreferencesRequest $request, PreferenceRepository $preferences): JsonResponse
    {
        $user = $request->user();

        $format = array_intersect_key(
            $request->validated(),
            array_flip(['measurement_system', 'locale', 'decimal_separator', 'thousands_separator', 'display_precision'])
        );

        $units = (array) $request->input('units', []);

        try {
            DB::transaction(function () use ($units, $format, $preferences, $user) {
                foreach ($units as $category => $unit) {
                    $preferences->setPreferredUnit((string) $category, (string) $unit, $user);
                }

                if ($format !== []) {
                    $preferences->setFormatPreferences($format, $user);
                }
            });
        } catch (UnitConversionException $e) {
            // Nothing was persisted: the transaction rolled back.
            Log::notice('unit-api: preference write rejected', [
                'user_id'    => $user->id ?? null,
                'categories' => array_keys($units),
                'reason'     => $e->getMessage(),
            ]);

            return $this->fail('invalid_preference', $e->getMessage());
        }

        // Sensitive-change audit trail, required by the specification's secure
        // access principle. Values are recorded, not the request body.
        Log::info('unit-api: preferences updated', [
            'user_id'    => $user->id ?? null,
            'categories' => array_keys($units),
            'format'     => array_keys($format),
        ]);

        return $this->ok('updated', trans('public.updated'), $this->payload($request, $user, $preferences));
    }

    private function payload(Request $request, $user, PreferenceRepository $preferences): array
    {
        return (new PreferenceResource([
            'units'             => $preferences->allPreferences($user),
            'format'            => $preferences->formatPreferences($user),
            'available_systems' => $preferences->availableSystems(),
        ]))->resolve($request);
    }
}
