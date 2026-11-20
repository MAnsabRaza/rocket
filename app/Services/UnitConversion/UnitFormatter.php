<?php

namespace App\Services\UnitConversion;

/**
 * Display formatting for converted values (M3).
 *
 * Replaces the inline number_format() call in UnitConversionService::format().
 * With no preferences set and no options passed, output is byte-identical to
 * M2, which the formatter test suite asserts directly.
 *
 * Three things are configurable that were previously hardcoded:
 *
 *   decimal and thousands separators   per locale, or per user preference
 *   display precision                  per user preference
 *   singular and plural unit labels    per config, off by default
 *
 * The M2 significance rule still governs the floor: a preference may raise the
 * decimal places shown, but may never lower them far enough to render a
 * non-zero value as zero. That rule is why the 54 approved corrections display
 * at all, and a user preference must not be able to undo it.
 */
final class UnitFormatter
{
    /** Matches UnitConversionService. Two decimals unless that discards the value. */
    private const BASE_PRECISION = 2;

    /** Never exceed this, to stay inside double precision. */
    private const MAX_PRECISION = 12;

    public function __construct(
        private readonly UnitResolver $resolver,
        private readonly PreferenceRepository $preferences
    ) {
    }

    /**
     * Format a value with its unit label.
     *
     * @param array $options user, locale, decimal_separator, thousands_separator,
     *                       display_precision, pluralize. Any omitted key falls
     *                       back to the user preference, then the locale, then
     *                       the configured default.
     */
    public function format(float $value, string $unit, bool $short = false, array $options = []): string
    {
        $resolved  = $this->resolveOptions($options);
        $precision = $this->precisionFor($value, $resolved['display_precision']);

        $number = number_format(
            $value,
            $precision,
            $resolved['decimal_separator'],
            $resolved['thousands_separator']
        );

        return $number . ' ' . $this->labelFor(
            $unit,
            $value,
            $short,
            $resolved['pluralize'],
            $resolved['locale']
        );
    }

    /** Convenience wrapper matching the existing formatShort() behaviour. */
    public function formatShort(float $value, string $unit, array $options = []): string
    {
        return $this->format($value, $unit, true, $options);
    }

    /**
     * Label for a unit, in the correct plural form for the locale.
     *
     * Plural category comes from PluralRules, not from a comparison against
     * one. English has two forms, Polish three, Arabic six, and inflecting at
     * exactly one is an English rule that would need rebuilding in M6.
     *
     * Localized names are looked up in config('unit_formatting.unit_names'),
     * keyed by locale, then unit, then CLDR category. That table is empty by
     * default, so English output falls through to the existing display_labels
     * and singular_labels and is unchanged. Populating it for a locale is a
     * configuration change, and it is where the client's translated unit names
     * will go once the locale list is supplied.
     *
     * Short labels are symbols and never inflect.
     */
    public function labelFor(
        string $unit,
        float $value,
        bool $short = false,
        bool $pluralize = false,
        ?string $locale = null
    ): string {
        $config = $this->resolver->config();

        if ($short) {
            return $config['short_labels'][$unit] ?? $unit;
        }

        $fallbackLocale = config('unit_formatting.fallback_locale', 'en');
        $locale       ??= $fallbackLocale;
        $category       = $pluralize ? PluralRules::categoryFor($locale, $value) : 'other';

        $names = config("unit_formatting.unit_names.{$locale}.{$unit}")
            ?? config("unit_formatting.unit_names.{$fallbackLocale}.{$unit}")
            ?? [];

        if (isset($names[$category])) {
            return $names[$category];
        }

        if (isset($names['other'])) {
            return $names['other'];
        }

        $plural = $config['display_labels'][$unit] ?? $unit;

        if ($pluralize && $category === 'one') {
            return config("unit_formatting.singular_labels.{$unit}", $plural);
        }

        return $plural;
    }

    /** CLDR plural category a value takes in a locale. Exposed for the API in M4. */
    public function pluralCategory(float $value, ?string $locale = null): string
    {
        return PluralRules::categoryFor(
            $locale ?? config('unit_formatting.fallback_locale', 'en'),
            $value
        );
    }

    /**
     * Decimal places to display.
     *
     * With no preference this reproduces the M2 rule exactly. With a preference
     * it is honoured, except that it is raised where necessary to keep a
     * non-zero value visible.
     */
    public function precisionFor(float $value, ?int $preferred = null): int
    {
        if ($preferred === null) {
            return $this->significantPrecision($value);
        }

        $preferred = max(0, min(self::MAX_PRECISION, $preferred));

        return max($preferred, $this->minimumVisiblePrecision($value));
    }

    /** The M2 rule, unchanged: two decimals unless that would discard the value. */
    private function significantPrecision(float $value): int
    {
        if (round($value, self::BASE_PRECISION) != 0.0 || $value == 0.0) {
            return self::BASE_PRECISION;
        }

        for ($precision = self::BASE_PRECISION + 1; $precision <= self::MAX_PRECISION; $precision++) {
            if (round($value, $precision) != 0.0) {
                return min(self::MAX_PRECISION, $precision + 2);
            }
        }

        return self::MAX_PRECISION;
    }

    /** Fewest decimals at which a non-zero value still shows something. */
    private function minimumVisiblePrecision(float $value): int
    {
        if ($value == 0.0) {
            return 0;
        }

        for ($precision = 0; $precision <= self::MAX_PRECISION; $precision++) {
            if (round($value, $precision) != 0.0) {
                return $precision;
            }
        }

        return self::MAX_PRECISION;
    }

    /** Merge explicit options over user preferences over locale defaults. */
    private function resolveOptions(array $options): array
    {
        $prefs = $this->preferences->formatPreferences($options['user'] ?? null);

        $locale = $options['locale']
            ?? $prefs['locale']
            ?? (function_exists('app') ? app()->getLocale() : null)
            ?? config('unit_formatting.fallback_locale', 'en');

        $fallback = config('unit_formatting.locales.' . config('unit_formatting.fallback_locale', 'en'))
            ?? ['decimal_separator' => '.', 'thousands_separator' => ','];

        $localeDefaults = config('unit_formatting.locales.' . $locale) ?? $fallback;

        return [
            'locale' => $locale,

            'decimal_separator' => $options['decimal_separator']
                ?? $prefs['decimal_separator']
                ?? $localeDefaults['decimal_separator'],

            'thousands_separator' => $options['thousands_separator']
                ?? $prefs['thousands_separator']
                ?? $localeDefaults['thousands_separator'],

            'display_precision' => $options['display_precision']
                ?? $prefs['display_precision']
                ?? null,

            'pluralize' => $options['pluralize']
                ?? (bool) config('unit_formatting.pluralize', false),
        ];
    }
}
