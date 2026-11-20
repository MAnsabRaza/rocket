<?php

/*
|--------------------------------------------------------------------------
| M8 ISO Overlay Template Engine
|--------------------------------------------------------------------------
| Everything that varies between ISO standards is data held in the overlay
| tables. This file only declares the generic item types an overlay version
| may contain and the rules each type's data must satisfy. A new standard or
| edition needs no change here; a new kind of item is one entry below.
|
| ref.source:
|   flinkiso  ref_id must exist in the named table on the `flinkiso` connection
|   optional  ref_id may be given (free reference) or left empty
|   none      no reference; the item is defined entirely by its data
*/

return [

    'locales' => ['en', 'el', 'ar'],

    'fallback_locale' => 'en',

    'assignment_targets' => ['organization', 'department', 'process', 'entity'],

    'item_types' => [

        'clause' => [
            'ref' => ['source' => 'flinkiso', 'table' => 'clauses'],
            'data' => [],
        ],

        'module' => [
            'ref' => ['source' => 'optional'],
            'data' => [],
        ],

        'department' => [
            'ref' => ['source' => 'flinkiso', 'table' => 'departments'],
            'data' => [],
        ],

        'process' => [
            'ref' => ['source' => 'flinkiso', 'table' => 'processes'],
            'data' => [],
        ],

        'role' => [
            'ref' => ['source' => 'optional'],
            'data' => [],
        ],

        'workflow' => [
            'ref' => ['source' => 'flinkiso', 'table' => 'approval_processes'],
            'data' => [],
        ],

        // Checklists and forms are built with the existing FlinkISO Drag and Drop
        // builder and referenced here by their custom_tables id (decision 57).
        'checklist' => [
            'ref' => ['source' => 'flinkiso', 'table' => 'custom_tables'],
            'data' => [],
        ],

        'form' => [
            'ref' => ['source' => 'flinkiso', 'table' => 'custom_tables'],
            'data' => [],
        ],

        // KPIs and risks are structured, versioned overlay data (decision 58).
        'kpi' => [
            'ref' => ['source' => 'none'],
            'data' => [
                'formula' => 'required|string|max:500',
                'unit' => 'required|string|max:50',
                'frequency' => 'required|in:daily,weekly,monthly,quarterly,yearly',
                'target' => 'required|numeric',
                'direction' => 'required|in:higher_is_better,lower_is_better',
                'thresholds' => 'required|array',
                'thresholds.warning' => 'required|numeric',
                'thresholds.critical' => 'required|numeric',
                'purpose' => 'nullable|string|max:2000',
                'responsible_role' => 'nullable|string|max:120',
                'source' => 'nullable|string|max:255',
            ],
        ],

        'risk' => [
            'ref' => ['source' => 'none'],
            'data' => [
                'category' => 'required|string|max:120',
                'probability_scale' => 'required|integer|min:2|max:10',
                'impact_scale' => 'required|integer|min:2|max:10',
                'scoring_rule' => 'required|in:multiply,add',
                'levels' => 'required|array|min:1',
                'levels.*.max_score' => 'required|numeric',
                'levels.*.label' => 'required|string|max:60',
                'mitigation' => 'nullable|string|max:2000',
                'residual_rule' => 'nullable|string|max:500',
            ],
        ],
    ],
];
