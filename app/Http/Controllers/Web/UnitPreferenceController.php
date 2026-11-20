<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\UserUnitPreference;
use App\Services\UnitConversion\DisplayPreferenceRepository;
use App\Services\UnitConversion\PreferenceRepository;
use App\Services\UnitConversion\UnitResolver;
use App\Services\UnitConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class UnitPreferenceController extends Controller
{
    public function update(Request $request, UnitConversionService $unitService)
    {
        $rules = [
            'preferred_date_format' => 'nullable|string|max:30',
            'preferred_custom_date_format' => 'nullable|string|max:30',
            'preferred_time_format' => 'nullable|string|max:30',
            'preferred_custom_time_format' => 'nullable|string|max:30',
            'preferred_week_start' => 'nullable|string|max:10',
            'previous_url' => 'nullable|string',

            // M3 format preferences. Without rules these keys are dropped by
            // validate(), which returns only the validated subset.
            'measurement_system'  => 'nullable|string|in:' . implode(',', array_keys(config('unit_formatting.measurement_systems', []))),
            'locale'              => 'nullable|string|max:10',
            'decimal_separator'   => 'nullable|string|max:4',
            'thousands_separator' => 'nullable|string|max:4',
            'display_precision'   => 'nullable|integer|min:0|max:12',
        ];

        foreach ($unitService->getUnitTypes() as $type) {
            $rules["preferred_{$type}_unit"] = 'nullable|string|max:10';
        }

        $data = $request->validate($rules);

        $currentUser = $request->user() ?: (auth()->check() ? auth()->user() : null);
        $repository  = app(PreferenceRepository::class);
        $resolver    = app(UnitResolver::class);
        $displayRepo = app(DisplayPreferenceRepository::class);

        $preferences = [];

        $dateFormat = ($data['preferred_date_format'] ?? null) === 'custom'
            ? ($data['preferred_custom_date_format'] ?? null)
            : ($data['preferred_date_format'] ?? null);

        $timeFormat = ($data['preferred_time_format'] ?? null) === 'custom'
            ? ($data['preferred_custom_time_format'] ?? null)
            : ($data['preferred_time_format'] ?? null);

        // Cookies are queued for guests only. An authenticated user's
        // preferences persist in the database and session, and every queued
        // cookie is an encrypted Set-Cookie header of roughly 600 bytes. The
        // form posts every field, so queueing all of them produced ~7KB of
        // response headers, which exceeds the nginx proxy buffer and surfaces
        // as a 502 on this installation.
        $queueCookies = $currentUser === null;

        foreach ([
            'preferred_date_format' => $dateFormat,
            'preferred_time_format' => $timeFormat,
            'preferred_week_start' => $data['preferred_week_start'] ?? null,
        ] as $key => $value) {
            if (!empty($value)) {
                $preferences[$key] = $value;
                if ($queueCookies) {
                    Cookie::queue($key, $value, 30 * 24 * 60);
                }
                session([$key => $value]);
            }
        }

        // Date format, time format and week start now persist durably.
        //
        // They have never had a column on `users`; the original update() call
        // passed them through the fillable filter, which discarded them, so
        // they lived only in a cookie and the session. Suppressing cookies for
        // signed-in users to fix the 502 left the session as their only home,
        // and a lost session lost the preference. They are stored here in
        // user_format_preferences, which is this project's own table, so the
        // guard asserting that no preference save writes to `users` still holds.
        if ($currentUser !== null) {
            $displayData = array_filter([
                'date_format' => $dateFormat,
                'time_format' => $timeFormat,
                'week_start'  => $data['preferred_week_start'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');

            if ($displayData !== []) {
                $displayRepo->set($displayData, $currentUser);
            }
        }

        // A measurement system is being APPLIED only when the posted value
        // differs from the stored one.
        //
        // The previous revision tested !empty($data['measurement_system']),
        // but the form posts that field on every save. Once a system was
        // selected the flag was true forever, so every subsequent save deleted
        // the user's per-category overrides and discarded the posted units.
        // Choosing Btu while Metric was selected therefore reverted to kW-h,
        // and so did changing an unrelated preference such as Number Format.
        // A form that does not carry the field at all is neither applying nor
        // clearing a system. This installation has a second, older picker at
        // views/web/default/includes/top_nav/units.blade.php which posts to
        // this same route with hidden inputs for length, mass and area and no
        // measurement_system field. Reading an absent field and an empty field
        // as the same thing made that form look like a switch to Per Category,
        // which froze the preset into explicit rows.
        $systemSubmitted = $request->has('measurement_system');
        $postedSystem    = $data['measurement_system'] ?? null;
        $storedSystem    = $currentUser === null
            ? null
            : ($repository->formatPreferences($currentUser)['measurement_system'] ?? null);

        $systemChanged  = $systemSubmitted
            && (string) ($postedSystem ?? '') !== (string) ($storedSystem ?? '');
        $applyingSystem = $systemChanged && ! empty($postedSystem);
        $clearingSystem = $systemChanged && empty($postedSystem);

        // What every category resolves to right now, captured before anything
        // is written. A posted unit equal to this is the form re-posting the
        // checked radio, not a choice; a posted unit different from it is a
        // genuine override. Distinguishing the two is what stops nine explicit
        // rows being created on every save, and it is also what keeps a guest's
        // response down to a few cookies instead of twelve.
        $resolvedBefore = [];
        foreach ($unitService->getUnitTypes() as $type) {
            $resolvedBefore[$type] = $unitService->getPreferredUnit($type, $currentUser);
        }

        foreach ($unitService->getUnitTypes() as $type) {
            $key = "preferred_{$type}_unit";

            if ($applyingSystem || empty($data[$key])) {
                continue;
            }

            if (! $unitService->isValidUnit($type, $data[$key])) {
                continue;
            }

            $canonical = $resolver->resolveUnit($type, $data[$key]) ?? $data[$key];

            // Leaving a system for Per Category freezes what is on screen, so
            // the display does not jump to base units the moment the preset is
            // removed. Every posted unit is stored in that one case.
            if (! $clearingSystem && $canonical === ($resolvedBefore[$type] ?? null)) {
                continue;
            }

            $preferences[$key] = $canonical;
            if ($queueCookies) {
                Cookie::queue($key, $canonical, 30 * 24 * 60);
            }
            session([$key => $canonical]);
        }

        if ($currentUser !== null) {
            // Unit categories are matched against the configured type list, never
            // derived from the key. Keys such as preferred_date_format share the
            // same prefix and are not unit categories: slicing that key yielded
            // "date_f" and raised UnknownUnitCategoryException.
            $unitKeys = [];
            foreach ($unitService->getUnitTypes() as $type) {
                $unitKeys["preferred_{$type}_unit"] = $type;
            }

            if ($applyingSystem) {
                UserUnitPreference::where('user_id', $currentUser->id)->delete();

                foreach (array_keys($unitKeys) as $sessionKey) {
                    session()->forget($sessionKey);
                }
            }

            foreach ($preferences as $key => $value) {
                if (isset($unitKeys[$key])) {
                    $repository->setPreferredUnit($unitKeys[$key], $value, $currentUser);
                }
            }

            $formatKeys = ['measurement_system', 'locale', 'decimal_separator', 'thousands_separator', 'display_precision'];
            $formatData = array_intersect_key($data, array_flip($formatKeys));

            if (!empty($formatData)) {
                $repository->setFormatPreferences($formatData, $currentUser);
            }
        }

        return !empty($data['previous_url'])
            ? redirect($data['previous_url'])
            : redirect()->back();
    }
}
