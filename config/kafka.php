<?php

return [
    /*
     * Model events are produced by Scaffold\Framework\Jobs\ProduceModelEventJob
     * to the shared php-common topic.
     */
    'model_event' => [
        'brokers' => env('KAFKA_BROKERS', ''),
        'topic' => env('KAFKA_MODEL_EVENT_TOPIC', 'php-common'),
        'group_id' => env('KAFKA_MODEL_EVENT_GROUP_ID', 'backend-crm-model-event'),
        // A newly deployed consumer only processes events produced after it starts.
        'auto_offset_reset' => env('KAFKA_MODEL_EVENT_AUTO_OFFSET_RESET', 'latest'),
    ],
];
