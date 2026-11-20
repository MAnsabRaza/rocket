<?php

/**
 * Display and preference formatting (M3).
 *
 * Kept separate from config/units.php deliberately. That file holds the
 * conversion factors the 354-row M2 baseline depends on, and nothing here
 * needs to touch them.
 *
 * Every unit named below is validated against config('units') at runtime. An
 * entry naming a unit the installation does not define is ignored and the
 * category falls back to its base unit, so a wrong entry degrades rather
 * than breaking the display path.
 */
return [

    /*
     * One preference that sets every category at once.
     * Applied only when a user has explicitly chosen a system; the default is
     * null, so out-of-the-box display is unchanged.
     */
    'measurement_systems' => [

        'metric' => [
            'length'          => 'cm',
            'area'            => 'm2',
            'mass'            => 'kg',
            'speed'           => 'kmh',
            'temperature'     => 'c',
            'force'           => 'n',
            'volume'          => 'l',
            'energy'          => 'kwh',
            'heat_flow_rate'  => 'w',
        ],

        'imperial' => [
            'length'          => 'in',
            'area'            => 'in2',
            'mass'            => 'lb',
            'speed'           => 'mph',
            'temperature'     => 'f',
            'force'           => 'lb',
            'volume'          => 'gal',
            'energy'          => 'btu',
            'heat_flow_rate'  => 'btuh',
        ],
    ],

    /*
     * Number separators per locale. Extend as locales are confirmed; adding one
     * is a config entry, not a code change.
     */
    'locales' => [
        'en' => ['decimal_separator' => '.', 'thousands_separator' => ',', 'percent_format' => ':value%'],
        'ar' => ['decimal_separator' => '.', 'thousands_separator' => ',', 'percent_format' => ':value%'],
        'el' => ['decimal_separator' => ',', 'thousands_separator' => '.', 'percent_format' => ':value%'],
        'de' => ['decimal_separator' => ',', 'thousands_separator' => '.', 'percent_format' => ':value%'],
        'fr' => ['decimal_separator' => ',', 'thousands_separator' => ' ', 'percent_format' => ':value%'],
        'es' => ['decimal_separator' => ',', 'thousands_separator' => '.', 'percent_format' => ':value%'],
        'tr' => ['decimal_separator' => ',', 'thousands_separator' => '.', 'percent_format' => ':value%'],
    ],

    'fallback_locale' => 'en',

    /*
     * CLDR plural rule set per locale.
     *
     * one_other  en, de, es, tr, nl and most Germanic and Romance languages
     * french     fr, pt-BR: an integer part of 0 or 1 takes the singular
     * arabic     ar: six categories (zero, one, two, few, many, other)
     * polish     pl: three categories for integers
     * none       ja, zh, ko, tr-style languages with no plural inflection
     *
     * An unmapped locale falls back to one_other. Matching also tries the base
     * tag, so "ar-EG" picks up "ar".
     */
    'plural_rules' => [
        'en' => 'one_other',
        'de' => 'one_other',
        'es' => 'one_other',
        'tr' => 'one_other',
        'fr' => 'french',
        'ar' => 'arabic',
        'el' => 'one_other',
        'pl' => 'polish',
        'ja' => 'none',
        'zh' => 'none',
    ],

    /*
     * Localized unit names, keyed by locale, then unit, then CLDR category.
     *
     * EMPTY BY DESIGN. English falls through to display_labels and
     * singular_labels below, so current output is unchanged.
     *
     * This is the slot the client's translated unit names drop into once the
     * locale list is supplied. Translations are client content and are not
     * invented here. A locale needs only the categories its rule set produces:
     * two for English, two for Greek, six for Arabic.
     *
     *   'ar' => [
     *       'kg' => [
     *           'zero' => '...', 'one'  => '...', 'two'   => '...',
     *           'few'  => '...', 'many' => '...', 'other' => '...',
     *       ],
     *   ],
     *
     * A missing category falls back to 'other', then to the fallback locale,
     * then to the existing English label, so a partial translation degrades
     * rather than breaking the display path.
     */
    'unit_names' => [
        'en' => [],
        'el' => [
            'cm'    => ['other' => 'Εκατοστά'],
            'in'    => ['other' => 'Ίντσες'],
            'ft'    => ['other' => 'Πόδια'],
            'cm2'   => ['other' => 'Τετραγωνικά εκατοστά'],
            'm2'    => ['other' => 'Τετραγωνικά μέτρα'],
            'in2'   => ['other' => 'Τετραγωνικές ίντσες'],
            'kg'    => ['other' => 'Κιλά'],
            'lb'    => ['other' => 'Λίβρες'],
            'n'     => ['other' => 'Νιούτον'],
            'kmh'   => ['other' => 'Χιλιόμετρα ανά ώρα'],
            'mph'   => ['other' => 'Μίλια ανά ώρα'],
            'km'    => ['other' => 'Χιλιόμετρα'],
            'c'     => ['other' => 'Βαθμοί Κελσίου'],
            'f'     => ['other' => 'Βαθμοί Φαρενάιτ'],
            'k'     => ['other' => 'Κέλβιν'],
            'l'     => ['other' => 'Λίτρα'],
            'm3'    => ['other' => 'Κυβικά μέτρα'],
            'in3'   => ['other' => 'Κυβικές ίντσες'],
            'gal'   => ['other' => 'Γαλόνια'],
            'btu'   => ['other' => 'Βρετανικές θερμικές μονάδες'],
            'erg'   => ['other' => 'Εργκ'],
            'j'     => ['other' => 'Τζάουλ'],
            'cal'   => ['other' => 'Θερμίδες'],
            'kwh'   => ['other' => 'Κιλοβατώρες'],
            'w'     => ['other' => 'Βατ'],
            'btuh'  => ['other' => 'Βρετανικές θερμικές μονάδες ανά ώρα'],
        ],
        'ar' => [
            'cm'    => ['other' => 'سنتيمتر'],
            'in'    => ['other' => 'بوصة'],
            'ft'    => ['other' => 'قدم'],
            'cm2'   => ['other' => 'سنتيمتر مربع'],
            'm2'    => ['other' => 'متر مربع'],
            'in2'   => ['other' => 'بوصة مربعة'],
            'kg'    => ['other' => 'كيلوغرام'],
            'lb'    => ['other' => 'رطل'],
            'km'    => ['other' => 'كيلومتر'],
            'kmh'   => ['other' => 'كيلومتر في الساعة'],
            'mph'   => ['other' => 'ميل في الساعة'],
            'c'     => ['other' => 'درجة مئوية'],
            'f'     => ['other' => 'درجة فهرنهايت'],
            'k'     => ['other' => 'كلفن'],
            'n'     => ['other' => 'نيوتن'],
            'l'     => ['other' => 'لتر'],
            'm3'    => ['other' => 'متر مكعب'],
            'in3'   => ['other' => 'بوصة مكعبة'],
            'gal'   => ['other' => 'غالون'],
            'btu'   => ['other' => 'وحدة حرارية بريطانية'],
            'erg'   => ['other' => 'إرغ'],
            'j'     => ['other' => 'جول'],
            'cal'   => ['other' => 'سعرة حرارية'],
            'kwh'   => ['other' => 'كيلوواط ساعة'],
            'w'     => ['other' => 'واط'],
            'btuh'  => ['other' => 'وحدة حرارية بريطانية في الساعة'],
        ],
    ],

    /*
     * Singular and plural rendering.
     *
     * OFF by default. config('units.display_labels') is already plural, so
     * format(1, 'km') returns "1.00 Kilometers" today. Switching this on by
     * default would alter output the client accepted at M2. The capability is
     * built and configurable; the default preserves the M2 guarantee.
     */
    'pluralize' => false,

    'singular_labels' => [
        'cm' => 'Centimeter',  'in'  => 'Inch',         'ft'  => 'Foot',
        'cm2'=> 'Square Centimeter', 'm2' => 'Square Meter', 'in2' => 'Square Inch',
        'kg' => 'Kilogram',    'lb'  => 'Pound',
        'km' => 'Kilometer',    'mph' => 'Mile per Hour',
        'c'  => 'Degree Celsius', 'f' => 'Degree Fahrenheit', 'k' => 'Kelvin',
        'n'  => 'Newton',
        'l'  => 'Liter',       'm3'  => 'Cubic Meter',  'in3' => 'Cubic Inch', 'gal' => 'Gallon',
        'btu'=> 'BTU',         'erg' => 'Erg',          'j'   => 'Joule',
        'cal'=> 'Calorie',     'kwh' => 'Kilowatt Hour',
        'w'  => 'Watt',        'btuh'=> 'BTU per Hour',
    ],
];
