<?php

return [
    'status' => [
        0         => 'Disabled',
        1         => 'Enabled',
        'unknown' => 'Unknown',
    ],

    'event' => [
        'registered'              => 'Registration Success',
        'prop_challenge_succeed'  => 'Challenge Success',
        'unknown'                 => 'Unknown',
    ],

    'message' => [
        'duplicate_name'      => 'Badge name already exists',
        'duplicate_short_name'=> 'Badge short name already exists',
        'duplicate_lang'      => 'Language entries cannot be duplicated',
        'name_required'       => 'At least one localized name is required',
        'time_required'       => 'Please provide both start and end times',
        'time_invalid'        => 'Invalid time format',
        'time_future_only'    => 'Time window must be in the future',
        'time_sequence'       => 'End time must be later than start time',
    ],
];
