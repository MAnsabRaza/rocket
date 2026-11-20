<?php

namespace App\Services\UnitConversion;

use App\Models\UserFormatPreference;
use App\Models\UserUnitPreference;

/**
 * Reads and writes user unit and formatting preferences (M3).
 *
 * Resolution order for a unit preference, per decision 2:
 *
 *   1. user_unit_preferences              the normalised store
 *   2. users.preferred_{category}_unit     legacy column, where one exists
 *   3. session, then cookie                as the existing service already did
 *   4. the measurement-system preset       one preference covering all categories
 *   5. the category's base unit            final fallback
 *
 * No existing account loses its preference. Legacy columns are read until a
 * preference is explicitly saved, at which point the normalised row wins. Legacy
 * columns are never written and never removed, so a rollback loses nothing.
 */
final class PreferenceRepository
{
    public function __construct(private readonly UnitResolver $resolver) {}

    /** Preferred unit for a category, or null if none is set anywhere. */
    public function preferredUnit(string $category, $user = null): ?string
    {
        $user = $this->resolveUser($user);

        if ($user !== null) {
            $stored = UserUnitPreference::query()
                ->where('user_id', $user->id)
                ->where('category', $category)
                ->value('unit');

            if ($canonical = $this->valid($category, $stored)) {
                return $canonical;
            }

            $legacyColumn = UserUnitPreference::LEGACY_COLUMNS[$category] ?? null;
            if ($legacyColumn !== null) {
                if ($canonical = $this->valid($category, $user->{$legacyColumn} ?? null)) {
                    return $canonical;
                }
            }
        }

        foreach ([$this->fromSession($category), $this->fromCookie($category)] as $value) {
            if ($canonical = $this->valid($category, $value)) {
                return $canonical;
            }
        }

        return null;
    }

    /** Preferred unit, falling back to the measurement system, then the base unit. */
    public function preferredUnitOrBase(string $category, $user = null): ?string
    {
        if ($explicit = $this->preferredUnit($category, $user)) {
            return $explicit;
        }

        if ($system = $this->formatPreferences($user)['measurement_system'] ?? null) {
            if ($fromSystem = $this->unitForSystem((string) $system, $category)) {
                return $fromSystem;
            }
        }

        return $this->resolver->config()['base_units'][$category] ?? null;
    }

    /**
     * Unit a measurement system prescribes for a category, or null.
     *
     * Validated against the live unit configuration, so a preset naming a unit
     * this installation does not define is ignored rather than propagated into
     * the display path.
     */
    public function unitForSystem(string $system, string $category): ?string
    {
        $configured = config("unit_formatting.measurement_systems.{$system}.{$category}");

        return $configured === null ? null : $this->valid($category, $configured);
    }

    /** Measurement systems this installation defines. */
    public function availableSystems(): array
    {
        return array_keys(config('unit_formatting.measurement_systems', []));
    }

    /** One entry per configured category. */
    public function allPreferences($user = null): array
    {
        $out = [];
        foreach (array_keys($this->resolver->config()['conversions'] ?? []) as $category) {
            $out[$category] = $this->preferredUnitOrBase($category, $user);
        }

        return $out;
    }

    /**
     * Store a unit preference. Writes only to the normalised table.
     *
     * @throws Exceptions\UnknownUnitException      when the unit is invalid for the category
     * @throws Exceptions\UnitConversionException   when no user is available
     */
    public function setPreferredUnit(string $category, string $unit, $user = null): string
    {
        $user = $this->resolveUser($user);

        if ($user === null) {
            throw new Exceptions\UnitConversionException('Cannot store a preference without a user.');
        }

        if (! $this->resolver->categoryExists($category)) {
            throw Exceptions\UnknownUnitCategoryException::for($category);
        }

        $canonical = $this->resolver->resolveUnit($category, $unit);

        if ($canonical === null) {
            throw Exceptions\UnknownUnitException::for($unit, $category);
        }

        UserUnitPreference::updateOrCreate(
            ['user_id' => $user->id, 'category' => $category],
            ['unit' => $canonical]
        );

        return $canonical;
    }

    /** Formatting preferences with sensible defaults. */
    public function formatPreferences($user = null): array
    {
        $user = $this->resolveUser($user);
        $row  = $user === null ? null : UserFormatPreference::where('user_id', $user->id)->first();

        return [
            'measurement_system'  => $row->measurement_system ?? null,
            'locale'              => $row->locale ?? null,
            'decimal_separator'   => $row->decimal_separator ?? null,
            'thousands_separator' => $row->thousands_separator ?? null,
            'display_precision'   => isset($row->display_precision) ? (int) $row->display_precision : null,
        ];
    }

    public function setFormatPreferences(array $values, $user = null): array
    {
        $user = $this->resolveUser($user);

        if ($user === null) {
            throw new Exceptions\UnitConversionException('Cannot store preferences without a user.');
        }

        $allowed = ['measurement_system', 'locale', 'decimal_separator', 'thousands_separator', 'display_precision'];

        UserFormatPreference::updateOrCreate(
            ['user_id' => $user->id],
            array_intersect_key($values, array_flip($allowed))
        );

        return $this->formatPreferences($user);
    }

    /** Canonical unit name if the value is valid for the category, otherwise null. */
    private function valid(string $category, $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return $this->resolver->resolveUnit($category, (string) $value);
    }

    private function resolveUser($user)
    {
        if (! empty($user)) {
            return $user;
        }

        return (function_exists('auth') && auth()->check()) ? auth()->user() : null;
    }

    private function fromSession(string $category)
    {
        return function_exists('session') ? session("preferred_{$category}_unit") : null;
    }

    private function fromCookie(string $category)
    {
        return (function_exists('request') && request()) ? request()->cookie("preferred_{$category}_unit") : null;
    }
}
