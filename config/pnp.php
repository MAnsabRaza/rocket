<?php

return [
    // Public acquisition query keys map to private user_acquisitions columns.
    'acquisition_parameters' => [
        'src' => 'acquisition_source',
        'ref' => 'external_referral_key',
        'campaign' => 'campaign_key',
        'target' => 'target_reference',
    ],
    // Validated HTTPS destinations are limited to these exact hosts and path prefixes.
    'continue_parameter' => 'continue',
    'redirect_allowlist' => [
        ['host' => 'prosnpros.com', 'path_prefix' => '/'],
        ['host' => 'www.prosnpros.com', 'path_prefix' => '/'],
    ],
    'parameter_max_length' => 100,
    'continue_max_length' => 2000,
];
