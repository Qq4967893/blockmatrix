<?php

return [
    // Flux gRPC host; fallback to existing trade system endpoints if unset
    'flux_host' => env('FLUX_GRPC_HOST', env('GRPC_LIVE_URL', env('GRPC_TRIAL_URL', ''))),

    // Kafka topic for vault events
    'flux_vault_topic' => env('FLUX_VAULT_TOPIC', 'flux-vault-events'),

    // Mepay payout callback url
    'mepay_callback_url' => env('MEPAY_CALL_BACK', ''),
];
