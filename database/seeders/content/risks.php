<?php
// database/seeders/content/risks.php
//
// Module 4: default risk library. The shared matrix below is copied into every
// entry so each risk can be scored and rendered as a heat map without any
// per-standard code. Categories cover operational, financial, compliance,
// strategic, reputational, environmental and health & safety risks.

$matrix = [
    'probability_scale' => 5,
    'impact_scale'      => 5,
    'scoring_rule'      => 'multiply',
    'levels'            => [
        ['max_score' => 4,  'label' => ['en' => 'Low',      'ar' => 'منخفض'], 'color' => '#198754'],
        ['max_score' => 9,  'label' => ['en' => 'Medium',   'ar' => 'متوسط'], 'color' => '#ffc107'],
        ['max_score' => 15, 'label' => ['en' => 'High',     'ar' => 'مرتفع'], 'color' => '#fd7e14'],
        ['max_score' => 25, 'label' => ['en' => 'Critical', 'ar' => 'حرج'],   'color' => '#dc3545'],
    ],
    'residual'          => [
        'rule'             => 'Residual score = residual probability × residual impact after controls.',
        'accept_threshold' => 9,
        'review_triggers'  => ['quarterly', 'incident', 'control_change'],
    ],
];

return [
    array_merge($matrix, [
        'technical_key'      => 'risk_single_source_supplier',
        'category'           => 'operational',
        'name'               => ['en' => 'Single-source supplier failure', 'ar' => 'فشل مورد وحيد المصدر'],
        'description'        => ['en' => 'Dependency on a single supplier for a critical component.'],
        'causes'             => [['en' => 'Supplier bankruptcy'], ['en' => 'Logistics disruption']],
        'consequences'       => [['en' => 'Production stoppage'], ['en' => 'Revenue loss']],
        'default_probability'=> 3,
        'default_impact'     => 4,
        'mitigations'        => [
            ['strategy' => 'reduce',   'label' => ['en' => 'Qualify at least one alternate supplier']],
            ['strategy' => 'transfer', 'label' => ['en' => 'Business-interruption insurance']],
        ],
        'controls'           => [
            ['type' => 'preventive', 'label' => ['en' => 'Quarterly supplier performance review']],
            ['type' => 'detective',  'label' => ['en' => 'Financial health monitoring']],
        ],
    ]),

    array_merge($matrix, [
        'technical_key'      => 'risk_data_breach',
        'category'           => 'compliance',
        'name'               => ['en' => 'Personal data breach', 'ar' => 'خرق البيانات الشخصية'],
        'description'        => ['en' => 'Unauthorised access to personal data.'],
        'causes'             => [['en' => 'Phishing'], ['en' => 'Misconfigured access controls']],
        'consequences'       => [['en' => 'Regulatory fine'], ['en' => 'Reputational damage']],
        'default_probability'=> 2,
        'default_impact'     => 5,
        'mitigations'        => [
            ['strategy' => 'reduce', 'label' => ['en' => 'MFA on all admin accounts']],
            ['strategy' => 'reduce', 'label' => ['en' => 'Annual penetration test']],
        ],
        'controls'           => [
            ['type' => 'preventive', 'label' => ['en' => 'Access control reviews']],
            ['type' => 'detective',  'label' => ['en' => 'SIEM alerting']],
            ['type' => 'corrective', 'label' => ['en' => 'Incident response plan']],
        ],
    ]),

    array_merge($matrix, [
        'technical_key'      => 'risk_key_person_dependency',
        'category'           => 'operational',
        'name'               => ['en' => 'Key-person dependency', 'ar' => 'الاعتماد على شخص محوري'],
        'default_probability'=> 3,
        'default_impact'     => 4,
        'mitigations'        => [
            ['strategy' => 'reduce', 'label' => ['en' => 'Document critical processes']],
            ['strategy' => 'reduce', 'label' => ['en' => 'Cross-train at least one backup']],
        ],
    ]),

    array_merge($matrix, [
        'technical_key'      => 'risk_regulatory_change',
        'category'           => 'compliance',
        'name'               => ['en' => 'Regulatory change non-compliance', 'ar' => 'عدم الامتثال لتغير تنظيمي'],
        'default_probability'=> 3,
        'default_impact'     => 4,
        'mitigations'        => [
            ['strategy' => 'reduce', 'label' => ['en' => 'Subscribe to regulatory feeds']],
            ['strategy' => 'accept', 'label' => ['en' => 'Budget for compliance projects']],
        ],
    ]),

    array_merge($matrix, [
        'technical_key'      => 'risk_workplace_injury',
        'category'           => 'health_safety',
        'name'               => ['en' => 'Workplace injury', 'ar' => 'إصابة في مكان العمل'],
        'default_probability'=> 2,
        'default_impact'     => 5,
        'mitigations'        => [
            ['strategy' => 'reduce', 'label' => ['en' => 'PPE enforcement']],
            ['strategy' => 'reduce', 'label' => ['en' => 'Monthly safety walks']],
        ],
    ]),

    array_merge($matrix, [
        'technical_key'      => 'risk_cash_flow',
        'category'           => 'financial',
        'name'               => ['en' => 'Cash-flow shortage', 'ar' => 'نقص التدفق النقدي'],
        'description'        => ['en' => 'Insufficient liquidity to meet near-term obligations.'],
        'causes'             => [['en' => 'Late customer payments'], ['en' => 'Unexpected capital expenditure']],
        'consequences'       => [['en' => 'Inability to pay suppliers'], ['en' => 'Penalty interest']],
        'default_probability'=> 3,
        'default_impact'     => 4,
        'mitigations'        => [
            ['strategy' => 'reduce',   'label' => ['en' => 'Rolling 13-week cash forecast']],
            ['strategy' => 'transfer', 'label' => ['en' => 'Undrawn credit facility']],
        ],
        'controls'           => [
            ['type' => 'detective',  'label' => ['en' => 'Monthly variance review']],
            ['type' => 'preventive', 'label' => ['en' => 'Credit limits on customers']],
        ],
    ]),

    array_merge($matrix, [
        'technical_key'      => 'risk_market_disruption',
        'category'           => 'strategic',
        'name'               => ['en' => 'Market disruption', 'ar' => 'اضطراب السوق'],
        'description'        => ['en' => 'Loss of market share due to a new entrant or technology shift.'],
        'causes'             => [['en' => 'New disruptive competitor'], ['en' => 'Slow product innovation']],
        'consequences'       => [['en' => 'Revenue decline'], ['en' => 'Loss of key customers']],
        'default_probability'=> 2,
        'default_impact'     => 5,
        'mitigations'        => [
            ['strategy' => 'reduce', 'label' => ['en' => 'Annual strategy refresh']],
            ['strategy' => 'reduce', 'label' => ['en' => 'Customer feedback programme']],
        ],
    ]),

    array_merge($matrix, [
        'technical_key'      => 'risk_brand_damage',
        'category'           => 'reputational',
        'name'               => ['en' => 'Brand reputation damage', 'ar' => 'الإضرار بسمعة العلامة'],
        'description'        => ['en' => 'Negative publicity or a public social-media incident.'],
        'causes'             => [['en' => 'Unresolved customer complaint'], ['en' => 'Employee misconduct']],
        'consequences'       => [['en' => 'Customer churn'], ['en' => 'Loss of partnerships']],
        'default_probability'=> 3,
        'default_impact'     => 4,
        'mitigations'        => [
            ['strategy' => 'reduce', 'label' => ['en' => 'Social-media monitoring']],
            ['strategy' => 'reduce', 'label' => ['en' => 'Crisis communication plan']],
        ],
        'controls'           => [
            ['type' => 'detective', 'label' => ['en' => 'Sentiment monitoring']],
        ],
    ]),

    array_merge($matrix, [
        'technical_key'      => 'risk_environmental_spill',
        'category'           => 'environmental',
        'name'               => ['en' => 'Environmental spill', 'ar' => 'انسكاب بيئي'],
        'description'        => ['en' => 'Accidental release of hazardous material.'],
        'causes'             => [['en' => 'Equipment failure'], ['en' => 'Human error during handling']],
        'consequences'       => [['en' => 'Environmental fine'], ['en' => 'Clean-up cost']],
        'default_probability'=> 2,
        'default_impact'     => 5,
        'mitigations'        => [
            ['strategy' => 'reduce', 'label' => ['en' => 'Secondary containment']],
            ['strategy' => 'transfer', 'label' => ['en' => 'Environmental liability insurance']],
        ],
        'controls'           => [
            ['type' => 'preventive', 'label' => ['en' => 'Spill-response training']],
            ['type' => 'corrective', 'label' => ['en' => 'Incident clean-up procedure']],
        ],
    ]),
];
