<?php
// database/seeders/content/checklists.php

return [
    [
        'technical_key' => 'iso9001_internal_audit',
        'standard_ref'  => 'ISO 9001:2015',
        'name'          => ['en' => 'ISO 9001 Internal Audit Checklist', 'ar' => 'قائمة تدقيق داخلي ISO 9001'],
        'description'   => ['en' => 'Core QMS clauses for internal audits.'],
        'version'       => 1,
        'scoring'       => [
            'pass_threshold' => 80,
            'weighting'      => 'equal',
            'finding_types'  => ['conformity','minor_nc','major_nc','observation','ofi'],
            'evidence_types' => ['document', 'photo', 'interview', 'observation'],
            'evidence_required_for' => ['minor_nc', 'major_nc'],
            'findings'       => [
                'conformity'    => ['label' => ['en' => 'Conformity'],                 'severity' => 'none',  'requires_action' => false],
                'minor_nc'      => ['label' => ['en' => 'Minor Nonconformity'],        'severity' => 'minor',  'requires_action' => true],
                'major_nc'      => ['label' => ['en' => 'Major Nonconformity'],        'severity' => 'major',  'requires_action' => true],
                'observation'   => ['label' => ['en' => 'Observation'],                'severity' => 'info',   'requires_action' => false],
                'ofi'           => ['label' => ['en' => 'Opportunity for Improvement'], 'severity' => 'info',  'requires_action' => false],
            ],
            'conformity'     => [
                'calculation'      => 'percentage',
                'pass_threshold'   => 80,
                'partial_threshold'=> 60,
                'criteria'         => ['en' => 'Pass at 80% or above; partial conformity between 60% and 79%; fail below 60%.'],
            ],
            'corrective_action_templates' => [
                ['root_cause_category' => 'process',   'action_type' => 'corrective', 'due_days' => 30],
                ['root_cause_category' => 'people',    'action_type' => 'corrective', 'due_days' => 14],
                ['root_cause_category' => 'supplier',  'action_type' => 'corrective', 'due_days' => 45],
                ['root_cause_category' => 'document',  'action_type' => 'corrective', 'due_days' => 7],
            ],
        ],
        'schema'        => [
            [
                'key'   => 'ctx',
                'label' => ['en' => '4. Context of the Organisation', 'ar' => '4. سياق المنظمة'],
                'questions' => [
                    ['key' => 'ctx_1', 'type' => 'yesno', 'required' => true,
                     'label'    => ['en' => 'Are internal/external issues monitored?', 'ar' => 'هل تتم مراقبة القضايا الداخلية والخارجية؟'],
                     'guidance' => ['en' => 'Review SWOT, PESTLE, stakeholder register.']],
                    ['key' => 'ctx_2', 'type' => 'yesno', 'required' => true,
                     'label' => ['en' => 'Is the scope of the QMS defined and documented?', 'ar' => 'هل نطاق نظام الجودة محدد وموثق؟']],
                    ['key' => 'ctx_3', 'type' => 'text', 'required' => false,
                     'label' => ['en' => 'Notes on interested parties', 'ar' => 'ملاحظات حول الأطراف المعنية']],
                ],
            ],
            [
                'key'   => 'leadership',
                'label' => ['en' => '5. Leadership', 'ar' => '5. القيادة'],
                'questions' => [
                    ['key' => 'ld_1', 'type' => 'yesno', 'required' => true,
                     'label' => ['en' => 'Is the quality policy communicated and understood?', 'ar' => 'هل سياسة الجودة مُعلنة ومفهومة؟']],
                    ['key' => 'ld_2', 'type' => 'rating', 'required' => true, 'scale' => [1,5],
                     'label' => ['en' => 'Top management engagement (1-5)', 'ar' => 'مستوى تفاعل الإدارة العليا (1-5)']],
                ],
            ],
            [
                'key'   => 'performance',
                'label' => ['en' => '9. Performance Evaluation', 'ar' => '9. تقييم الأداء'],
                'questions' => [
                    ['key' => 'pe_1', 'type' => 'yesno', 'required' => true,
                     'label' => ['en' => 'Are internal audits planned and conducted?', 'ar' => 'هل تُخطط وتُنفذ التدقيقات الداخلية؟']],
                    ['key' => 'pe_2', 'type' => 'yesno', 'required' => true,
                     'label' => ['en' => 'Is management review performed at planned intervals?', 'ar' => 'هل تتم مراجعة الإدارة في الفترات المخططة؟']],
                ],
            ],
        ],
    ],

    [
        'technical_key' => 'supplier_audit',
        'standard_ref'  => null,
        'name'          => ['en' => 'Approved Supplier Audit', 'ar' => 'تدقيق المورد المعتمد'],
        'description'   => ['en' => 'Supplier qualification checklist.'],
        'version'       => 1,
        'scoring'       => [
            'pass_threshold' => 70,
            'weighting'      => 'equal',
            'finding_types'  => ['conformity','minor_nc','major_nc','observation','ofi'],
            'evidence_types' => ['document', 'photo', 'interview', 'observation'],
            'evidence_required_for' => ['minor_nc', 'major_nc'],
            'conformity'     => [
                'calculation'       => 'percentage',
                'pass_threshold'    => 70,
                'partial_threshold' => 50,
            ],
            'corrective_action_templates' => [
                ['root_cause_category' => 'supplier', 'action_type' => 'corrective', 'due_days' => 45],
            ],
        ],
        'schema'        => [
            [
                'key'   => 'general',
                'label' => ['en' => 'General Information', 'ar' => 'معلومات عامة'],
                'questions' => [
                    ['key' => 'g_1', 'type' => 'text',  'required' => true,  'label' => ['en' => 'Company registration number']],
                    ['key' => 'g_2', 'type' => 'yesno', 'required' => true,  'label' => ['en' => 'Valid ISO 9001 certificate?']],
                    ['key' => 'g_3', 'type' => 'choice','required' => true,  'label' => ['en' => 'Business continuity plan in place?'],
                     'options' => [['value'=>'yes','label'=>'Yes'],['value'=>'partial','label'=>'Partial'],['value'=>'no','label'=>'No']]],
                ],
            ],
        ],
    ],
];