<?php

/**
 * Allowed conversion paths and extensible unit metadata (M3).
 *
 * Both default to empty, which means unrestricted and no metadata. Nothing in
 * the 354-row M2 baseline changes unless an entry is added here deliberately.
 */
return [

    /*
     * Permitted conversion routes per category.
     *
     * An empty or absent category means every pair is allowed, which is the
     * current behaviour. Listing pairs switches that category to allow-list
     * mode: anything not listed raises ConversionPathNotAllowedException.
     * Identity conversion is always permitted.
     *
     * Direction is significant, so both directions must be listed if both are
     * wanted:
     *
     *   'temperature' => [
     *       ['c', 'f'], ['f', 'c'],
     *       ['c', 'k'], ['k', 'c'],
     *   ],
     */
    'allowed_paths' => [
        // intentionally empty: no category is restricted at present
    ],

    /*
     * Arbitrary per-unit metadata, carried through without interpretation.
     *
     * The conversion engine never reads these keys, so a consumer can attach
     * what it needs without a schema change:
     *
     *   'mass' => [
     *       'kg' => ['system' => 'metric',   'si' => true,  'symbol_position' => 'suffix'],
     *       'lb' => ['system' => 'imperial', 'si' => false],
     *   ],
     */
    'metadata' => [
        // intentionally empty
    ],
];
