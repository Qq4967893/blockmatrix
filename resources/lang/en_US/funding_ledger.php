<?php

return [
    'operation_source' => [
        1 => 'System',
        2 => 'Manual',
    ],

    'direction' => [
        1 => 'Grant',
        -1 => 'Deduct',
    ],

    'management_type' => [
        9 => 'Negative Balance Compensation',
        13 => 'Bonus',
        14 => 'Red Packet',
    ],

    'message' => [
        'single_failed' => 'Processing failed: :details',
        'batch_failed' => 'Batch processing completed, but some users failed: :details',
        'failed_item' => ':user_code: :reason',
        'failed_separator' => '; ',
    ],

    'export' => [
        'title_management' => ['User ID', 'Operation Source', 'Direction', 'Fund Type', 'Created At (GMT+0)'],
    ],
];
