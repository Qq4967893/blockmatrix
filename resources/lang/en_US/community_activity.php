<?php

return [
    'status' => [
        0 => 'Pending',
        1 => 'Online',
        2 => 'Voided',
        'unknown' => 'Unknown',
    ],
    'publish_type' => [
        0 => 'Scheduled',
        1 => 'Manual',
        'unknown' => 'Unknown',
    ],
    'message' => [
        'void_success' => 'Voided successfully',
        'voided_no_action' => 'The activity has been voided and cannot be operated again.',
        'publish_success' => 'Published successfully',
        'only_pending_can_publish' => 'Only pending activities can be published.',
        'activity_ended' => 'The activity has ended and cannot be published.',
    ],
];
