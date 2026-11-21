<?php

/*
|--------------------------------------------------------------------------
| M8/M9 ISO Overlay Template Engine Configuration
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
|   library   (M9) ref_id points to the default content library tables
*/

return [

    'locales' => ['en', 'el', 'ar'],

    'fallback_locale' => 'en',

    'assignment_targets' => ['organization', 'department', 'process', 'entity'],

    // M9: Extend ref sources so OverlayService::normaliseItem accepts 'library'
    'ref_sources' => ['none', 'flinkiso', 'optional', 'library'],

    'item_types' => [

        'clause' => [
            'label' => 'Clause',
            'ref' => ['source' => 'flinkiso', 'table' => 'clauses'],
            'data' => [],
        ],

        'module' => [
            'label' => 'Module',
            'ref' => ['source' => 'optional'],
            'data' => [],
        ],

        'department' => [
            'label' => 'Department',
            'ref' => ['source' => 'flinkiso', 'table' => 'departments'],
            'data' => [],
        ],

        'process' => [
            'label' => 'Process',
            'ref' => ['source' => 'flinkiso', 'table' => 'processes'],
            'data' => [],
        ],

        'role' => [
            'label' => 'Role',
            'ref' => ['source' => 'optional'],
            'data' => [],
        ],

        // M9 Updated: Workflow now uses the default content library with structured data rules
        'workflow' => [
            'label' => 'Workflow',
            'ref'   => ['source' => 'library', 'table' => 'iso_content_workflows'],
            'data'  => [],
        ],

        // M9 Updated: Checklist now uses the default content library with structured data rules
        'checklist' => [
            'label' => 'Audit Checklist',
            'ref'   => ['source' => 'library', 'table' => 'iso_content_checklists'],
            'data'  => [],
        ],

        // M9 Updated: Form now uses the default content library with structured data rules
        'form' => [
            'label' => 'Form Template',
            'ref'   => ['source' => 'library', 'table' => 'iso_content_forms'],
            'data'  => [],
        ],

        // KPIs are structured, versioned overlay data (decision 58).
        'kpi' => [
            'label' => 'KPI',
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

        // Risks are structured, versioned overlay data (decision 58).
        'risk' => [
            'label' => 'Risk',
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
