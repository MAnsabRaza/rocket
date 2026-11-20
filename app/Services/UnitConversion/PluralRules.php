<?php

namespace App\Services\UnitConversion;

/**
 * CLDR plural categories (M3).
 *
 * The execution guide flags this explicitly: plural forms are trivial in
 * English and are not in Arabic or Polish, and doing it in M3 avoids rebuilding
 * it in M6. English has two forms, Polish three, Arabic six. A formatter that
 * inflects at exactly one is an English rule wearing a general name.
 *
 * Rule sets are assigned per locale in config('unit_formatting.plural_rules'),
 * so adding a locale is configuration rather than code. An unmapped locale
 * falls back to one/other, which is correct for the majority of European
 * languages and harmless for the rest.
 *
 * Categories returned match CLDR: zero, one, two, few, many, other.
 */
final class PluralRules
{
    public const CATEGORIES = ['zero', 'one', 'two', 'few', 'many', 'other'];

    /** CLDR plural category for a count in a locale. */
    public static function categoryFor(?string $locale, float $count): string
    {
        $rule = self::ruleFor($locale);

        return match ($rule) {
            'none'   => 'other',
            'french' => self::french($count),
            'arabic' => self::arabic($count),
            'polish' => self::polish($count),
            default  => self::oneOther($count),
        };
    }

    /** Rule set assigned to a locale, matching on the base tag too ("ar-EG" uses "ar"). */
    public static function ruleFor(?string $locale): string
    {
        if ($locale === null || $locale === '') {
            return 'one_other';
        }

        $map  = config('unit_formatting.plural_rules', []);
        $base = preg_split('/[-_]/', $locale)[0] ?? $locale;

        return (string) ($map[$locale] ?? $map[$base] ?? 'one_other');
    }

    /** True when the value has no fractional part, which most CLDR rules require. */
    private static function isWhole(float $count): bool
    {
        return abs($count) == floor(abs($count));
    }

    /** en, de, es, tr, nl and most Germanic and Romance languages. */
    private static function oneOther(float $count): string
    {
        return (self::isWhole($count) && abs($count) == 1.0) ? 'one' : 'other';
    }

    /** fr, pt-BR. CLDR: integer part of 0 or 1 takes the singular. */
    private static function french(float $count): string
    {
        $integerPart = floor(abs($count));

        return ($integerPart == 0.0 || $integerPart == 1.0) ? 'one' : 'other';
    }

    /** ar. Six categories, the reason this class exists. */
    private static function arabic(float $count): string
    {
        if (! self::isWhole($count)) {
            return 'other';
        }

        $n = (int) abs($count);

        if ($n === 0) {
            return 'zero';
        }

        if ($n === 1) {
            return 'one';
        }

        if ($n === 2) {
            return 'two';
        }

        $mod100 = $n % 100;

        if ($mod100 >= 3 && $mod100 <= 10) {
            return 'few';
        }

        if ($mod100 >= 11 && $mod100 <= 99) {
            return 'many';
        }

        return 'other';
    }

    /** pl. Three categories for integers. */
    private static function polish(float $count): string
    {
        if (! self::isWhole($count)) {
            return 'other';
        }

        $n = (int) abs($count);

        if ($n === 1) {
            return 'one';
        }

        $mod10  = $n % 10;
        $mod100 = $n % 100;

        if ($mod10 >= 2 && $mod10 <= 4 && ! ($mod100 >= 12 && $mod100 <= 14)) {
            return 'few';
        }

        return 'many';
    }
}
