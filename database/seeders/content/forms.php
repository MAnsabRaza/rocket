<?php
// database/seeders/content/forms.php

return [
    [
        'technical_key' => 'nonconformity_report',
        'category'      => 'nc_report',
        'name'          => ['en' => 'Nonconformity Report', 'ar' => 'تقرير عدم مطابقة'],
        'description'   => ['en' => 'Standard NC report form (ISO 9001 §10.2).'],
        'version'       => 1,
        'schema'        => [
            [
                'key'    => 'identification',
                'label'  => ['en' => 'Identification', 'ar' => 'التعريف'],
                'fields' => [
                    ['key' => 'nc_number',   'type' => 'text',     'required' => true,  'label' => ['en' => 'NC Number'],    'default' => 'auto'],
                    ['key' => 'raised_date', 'type' => 'date',     'required' => true,  'label' => ['en' => 'Date raised']],
                    ['key' => 'raised_by',   'type' => 'user',     'required' => true,  'label' => ['en' => 'Raised by']],
                    ['key' => 'department',  'type' => 'dropdown', 'required' => true,  'label' => ['en' => 'Department'],   'source' => 'departments'],
                    ['key' => 'clause_ref',  'type' => 'text',     'required' => false, 'label' => ['en' => 'ISO clause']],
                ],
            ],
            [
                'key'    => 'description',
                'label'  => ['en' => 'Description', 'ar' => 'الوصف'],
                'fields' => [
                    ['key' => 'requirement', 'type' => 'textarea', 'required' => true,  'label' => ['en' => 'Requirement'],      'help' => ['en' => 'What should have happened?']],
                    ['key' => 'evidence',    'type' => 'textarea', 'required' => true,  'label' => ['en' => 'Objective evidence']],
                    ['key' => 'nc_type',     'type' => 'radio',    'required' => true,  'label' => ['en' => 'NC type'],
                     'options' => [['value'=>'minor','label'=>'Minor'], ['value'=>'major','label'=>'Major'], ['value'=>'ofi','label'=>'OFI']]],
                    ['key' => 'attachments', 'type' => 'file',     'required' => false, 'label' => ['en' => 'Evidence files'], 'accept' => ['pdf','jpg','png'], 'max_mb' => 10],
                ],
            ],
            [
                'key'    => 'root_cause',
                'label'  => ['en' => 'Root Cause Analysis', 'ar' => 'تحليل السبب الجذري'],
                'fields' => [
                    ['key' => 'method',     'type' => 'dropdown', 'required' => true,  'label' => ['en' => 'Method'],
                     'options' => [['value'=>'5whys','label'=>'5 Whys'], ['value'=>'fishbone','label'=>'Fishbone'], ['value'=>'fmea','label'=>'FMEA']]],
                    ['key' => 'root_cause', 'type' => 'textarea', 'required' => true,  'label' => ['en' => 'Root cause']],
                ],
            ],
            [
                'key'    => 'action',
                'label'  => ['en' => 'Corrective Action', 'ar' => 'الإجراء التصحيحي'],
                'fields' => [
                    ['key' => 'immediate_action',  'type' => 'textarea', 'required' => true,  'label' => ['en' => 'Immediate action']],
                    ['key' => 'corrective_action', 'type' => 'textarea', 'required' => true,  'label' => ['en' => 'Corrective action']],
                    ['key' => 'responsible',       'type' => 'user',     'required' => true,  'label' => ['en' => 'Responsible person']],
                    ['key' => 'due_date',          'type' => 'date',     'required' => true,  'label' => ['en' => 'Due date']],
                ],
            ],
            [
                'key'    => 'verification',
                'label'  => ['en' => 'Verification', 'ar' => 'التحقق'],
                'fields' => [
                    ['key' => 'effective',     'type' => 'radio',     'required' => true,  'label' => ['en' => 'Effective?'],
                     'options' => [['value'=>'yes','label'=>'Yes'], ['value'=>'no','label'=>'No']]],
                    ['key' => 'verified_by',   'type' => 'user',      'required' => true,  'label' => ['en' => 'Verified by']],
                    ['key' => 'verified_date', 'type' => 'date',      'required' => true,  'label' => ['en' => 'Verification date']],
                    ['key' => 'signature',     'type' => 'signature', 'required' => true,  'label' => ['en' => 'Verifier signature']],
                ],
            ],
        ],
        'approval'      => [
            'chain' => [
                ['role' => 'quality_owner',   'required' => true],
                ['role' => 'quality_manager', 'required' => true],
            ],
        ],
    ],

    [
        'technical_key' => 'management_review_minutes',
        'category'      => 'mgmt_review',
        'name'          => ['en' => 'Management Review Minutes', 'ar' => 'محضر مراجعة الإدارة'],
        'description'   => ['en' => 'ISO 9001 §9.3 management review record.'],
        'version'       => 1,
        'schema'        => [
            [
                'key'    => 'meeting',
                'label'  => ['en' => 'Meeting Details', 'ar' => 'تفاصيل الاجتماع'],
                'fields' => [
                    ['key' => 'meeting_date', 'type' => 'date',  'required' => true,  'label' => ['en' => 'Meeting date']],
                    ['key' => 'chairperson',  'type' => 'user',  'required' => true,  'label' => ['en' => 'Chairperson']],
                    ['key' => 'attendees',    'type' => 'users', 'required' => true,  'label' => ['en' => 'Attendees']],
                ],
            ],
            [
                'key'    => 'inputs',
                'label'  => ['en' => 'Review Inputs (§9.3.2)', 'ar' => 'مدخلات المراجعة'],
                'fields' => [
                    ['key' => 'prev_actions',      'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Status of previous actions']],
                    ['key' => 'audit_results',     'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Audit results']],
                    ['key' => 'customer_feedback', 'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Customer feedback']],
                    ['key' => 'kpi_performance',   'type' => 'textarea', 'required' => true, 'label' => ['en' => 'KPI performance']],
                    ['key' => 'risk_status',       'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Risk & opportunity status']],
                ],
            ],
            [
                'key'    => 'outputs',
                'label'  => ['en' => 'Decisions & Actions (§9.3.3)', 'ar' => 'القرارات والإجراءات'],
                'fields' => [
                    ['key' => 'decisions',        'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Decisions taken']],
                    ['key' => 'resource_needs',   'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Resource needs']],
                    ['key' => 'improvement_opps', 'type' => 'textarea', 'required' => true, 'label' => ['en' => 'Improvement opportunities']],
                    ['key' => 'action_items',     'type' => 'table',    'required' => true, 'label' => ['en' => 'Action items'],
                     'columns' => [
                         ['key'=>'action','label'=>'Action','type'=>'text'],
                         ['key'=>'owner', 'label'=>'Owner', 'type'=>'user'],
                         ['key'=>'due',   'label'=>'Due',   'type'=>'date'],
                     ]],
                ],
            ],
            [
                'key'    => 'signoff',
                'label'  => ['en' => 'Sign-off', 'ar' => 'التوقيع'],
                'fields' => [
                    ['key' => 'chair_signature', 'type' => 'signature', 'required' => true, 'label' => ['en' => 'Chairperson signature']],
                    ['key' => 'minutes_taker',   'type' => 'user',      'required' => true, 'label' => ['en' => 'Minutes taken by']],
                ],
            ],
        ],
        'approval'      => [
            'chain' => [['role' => 'quality_manager', 'required' => true]],
        ],
    ],
];