<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Unit Conversion Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration handles common unit conversions, allowing users to
    | view content in their preferred units.
    |
    */

    'enabled' => env('UNIT_CONVERSION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Base Units
    |--------------------------------------------------------------------------
    | All values are stored in these base units in the database
    */
    'base_units' => [
        'length' => 'cm',
        'area' => 'cm2',
        'mass' => 'kg',
        'speed' => 'kmh',
        'temperature' => 'c',
        'force' => 'n',
        'volume' => 'l',
        'energy' => 'btu',
        'heat_flow_rate' => 'w',
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversion Factors
    |--------------------------------------------------------------------------
    | Conversion factors relative to base units
    */
    'conversions' => [
        'length' => [
            'cm' => 1,
            'in' => 0.393701,
            'ft' => 0.0328084,
        ],
        'area' => [
            'cm2' => 1,
            'm2' => 0.0001,
            'in2' => 0.155,
        ],
        'mass' => [
            'kg' => 1,
            'lb' => 2.20462,
        ],
        'speed' => [
            'kmh' => 1,
            'mph' => 0.621371,
        ],
        'temperature' => [
            'c' => 1,
            'f' => 33.8,
            'k' => 274.15,
        ],
        'force' => [
            'n' => 1,
            'lb' => 0.224809,
        ],
        'volume' => [
            'l' => 1,
            'm3' => 0.001,
            'in3' => 61.0237,
            'gal' => 0.264172,
        ],
        'energy' => [
            'btu' => 1,
            'erg' => 10550558526.2,
            'j' => 1055.06,
            'cal' => 252.164,
            'kwh' => 0.000293071,
        ],
        'heat_flow_rate' => [
            'w' => 1,
            'btuh' => 3.41214,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Display Labels
    |--------------------------------------------------------------------------
    | Human-readable labels for each unit
    */
    'display_labels' => [
        // Length
        'cm' => 'Centimeters',
        'in' => 'Inches',
        'ft' => 'Feet',

        // Area
        'cm2' => 'Square Centimeters',
        'm2' => 'Square Meters',
        'in2' => 'Square Inches',

        // Mass / Force
        'kg' => 'Kilograms',
        'lb' => 'Pounds',
        'n' => 'Newtons',

        // Speed
        'kmh' => 'Kilometers per hour',
        'mph' => 'Miles per hour',
        // 'km' is retained as a legacy alias of 'kmh'. It is not selectable
        // in any category; the label exists so the accepted M2 baseline and
        // the M2/M3 tests that assert it continue to render unchanged.
        'km' => 'Kilometers',

        // Temperature
        'c' => 'Celsius',
        'f' => 'Fahrenheit',
        'k' => 'Kelvin',

        // Volume
        'l' => 'Liters',
        'm3' => 'Cubic Meters',
        'in3' => 'Cubic Inches',
        'gal' => 'Gallons',

        // Energy / heat
        'btu' => 'Btu',
        'erg' => 'Erg',
        'j' => 'Joules',
        'cal' => 'Calories',
        'kwh' => 'Kilowatt hours',
        'w' => 'Watts',
        'btuh' => 'Btu/h',
    ],

    /*
    |--------------------------------------------------------------------------
    | Short Labels
    |--------------------------------------------------------------------------
    | Abbreviated labels for compact display
    */
    'short_labels' => [
        'cm' => 'cm',
        'in' => 'in',
        'ft' => 'ft',
        'cm2' => 'cm2',
        'm2' => 'm2',
        'in2' => 'in2',
        'kg' => 'kg',
        'lb' => 'lb',
        'km' => 'km',
        'mph' => 'mph',
        'c' => 'deg C',
        'f' => 'deg F',
        'k' => 'Kelvin',
        'n' => 'N',
        'l' => 'L',
        'm3' => 'm3',
        'in3' => 'in3',
        'gal' => 'Gallons',
        'btu' => 'Btu',
        'erg' => 'erg',
        'j' => 'J',
        'cal' => 'cal',
        'kwh' => 'kW-h',
        'w' => 'W',
        'btuh' => 'Btu/h',
    ],

    /*
    |--------------------------------------------------------------------------
    | Precision (M2/M3)
    |--------------------------------------------------------------------------
    | Decimal places per category. The values below reproduce the previous
    | hardcoded round($x, 2) exactly, so behaviour is unchanged until a
    | precision change is approved.
    */
    'precision' => [
        'default'        => 2,
        'length'         => 2,
        'area'           => 2,
        'mass'           => 2,
        'speed'          => 2,
        'temperature'    => 2,
        'force'          => 2,
        'volume'         => 2,
        'energy'         => 2,
        'heat_flow_rate' => 2,
    ],

    /*
    | Rounding mode. 'half_up' matches PHP round(), which is the behaviour
    | recorded in the M1 baseline fixture.
    */
    'rounding_mode' => 'half_up',

    /*
    | Category-scoped labels. Resolves the 'lb' collision between mass
    | (pounds) and force (pounds-force), which the flat label maps cannot
    | express. Falls back to display_labels when a category is absent.
    */
    'category_labels' => [
        'mass'  => ['lb' => 'Pounds'],
        'force' => ['lb' => 'Pounds-force'],
    ],

    /*
    | Units retained for historical records but hidden from selection lists.
    */
    'inactive_units' => [
        'energy' => ['erg'],
    ],

    /*
    | Unit aliases (M3). Lowercase alternative names mapped to canonical units.
    | 'lbs' is evidenced: the pre-existing UnitConversionServiceTest calls
    | convertMass(10, 'kg', 'lbs') and receives 10 back unconverted.
    */
    'aliases' => [
        'length' => ['centimeter'=>'cm','centimeters'=>'cm','centimetre'=>'cm','inch'=>'in','inches'=>'in','foot'=>'ft','feet'=>'ft'],
        'mass'   => ['lbs'=>'lb','pound'=>'lb','pounds'=>'lb','kilogram'=>'kg','kilograms'=>'kg','kilo'=>'kg','kilos'=>'kg'],
        'force'  => ['lbf'=>'lb','newton'=>'n','newtons'=>'n'],
        'volume' => ['litre'=>'l','litres'=>'l','liter'=>'l','liters'=>'l','gallon'=>'gal','gallons'=>'gal'],
        'area'   => ['sqcm'=>'cm2','sqm'=>'m2','sqin'=>'in2'],
        'temperature' => ['celsius'=>'c','centigrade'=>'c','fahrenheit'=>'f','kelvin'=>'k'],
        'speed'  => ['km'=>'kmh','kilometer'=>'kmh','kilometers'=>'kmh','kmh'=>'kmh','km/h'=>'kmh','kph'=>'kmh','kilometers_per_hour'=>'kmh','mph'=>'mph','miles_per_hour'=>'mph','mi/h'=>'mph'],
        'energy' => ['joule'=>'j','joules'=>'j','calorie'=>'cal','calories'=>'cal','kilowatt_hour'=>'kwh'],
        'heat_flow_rate' => ['watt'=>'w','watts'=>'w'],
    ],

    /* Maximum items accepted by a single batch conversion (M2/M4). */
    'max_batch_size' => 500,
];
