<?php

namespace App\Services\UnitConversion;

use App\Models\UserFormatPreference;

/**
 * Durable storage for the three display-format preferences: date format, time
 * format and week start.
 *
 * These have never had a column on `users`. The original controller passed them
 * to update(), where Eloquent's fillable filter discarded them, so they lived
 * only in a cookie and the session. Suppressing cookies for signed-in users to
 * correct the 502 of 24 August left the session as their only home, and the
 * client reported the date format not surviving a refresh.
 *
 * They are stored in user_format_preferences, this project's own M3 table. The
 * live `users` table is untouched, so the guard test asserting that no
 * preference operation writes to `users` continues to hold, and a rollback of
 * this project's migrations loses nothing that existed before it.
 *
 * Deliberately separate from PreferenceRepository::formatPreferences(), whose
 * five keys are the published shape of the M4 API preferences response. Adding
 * keys there would change a documented contract for a web-only concern.
 */
final class DisplayPreferenceRepository
{
    public const FIELDS = ['date_format', 'time_format', 'week_start'];

    /** Stored display preferences, null where nothing is set. */
    public function all($user = null): array
    {
        $user = $this->resolveUser($user);
        $row  = $user === null ? null : UserFormatPreference::where('user_id', $user->id)->first();

        return [
            'date_format' => $row->date_format ?? null,
            'time_format' => $row->time_format ?? null,
            'week_start'  => $row->week_start ?? null,
        ];
    }

    /**
     * Store one or more display preferences. Unknown keys are ignored.
     *
     * @throws Exceptions\UnitConversionException when no user is available
     */
    public function set(array $values, $user = null): array
    {
        $user = $this->resolveUser($user);

        if ($user === null) {
            throw new Exceptions\UnitConversionException('Cannot store display preferences without a user.');
        }

        $clean = array_intersect_key($values, array_flip(self::FIELDS));

        if ($clean === []) {
            return $this->all($user);
        }

        UserFormatPreference::updateOrCreate(['user_id' => $user->id], $clean);

        return $this->all($user);
    }

    private function resolveUser($user)
    {
        if (! empty($user)) {
            return $user;
        }

        return (function_exists('auth') && auth()->check()) ? auth()->user() : null;
    }
}
