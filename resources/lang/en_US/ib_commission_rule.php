<?php

return [
    'name_exists' => 'Commission rule name already exists. Duplicate items: :details',
    'base_commission_rate_exists' => 'Base commission rate already exists. :name current rate: :rate%',
    'missing_listed_sources' => 'Some listed sources are not configured',
    'review_status' => [
        0 => 'Draft',
        1 => 'Pending review',
        2 => 'Approved',
        3 => 'Rejected',
        4 => 'Disabled',
    ],
    'effective_status' => [
        0 => 'Pending',
        1 => 'Effective',
    ],
];
