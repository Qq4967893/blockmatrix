<?php

use Scaffold\FluxWallet\Facades\FluxWallet;

return [
    'config_type' => [
        FluxWallet::OperationWithdrawal => 'Withdrawal',
        FluxWallet::OperationDeposit => 'Deposit',
    ],
    'status' => [
        'enabled' => 'Enabled',
        'not_enabled' => 'Not Enabled',
        'disabled' => 'Disabled',
    ],
    'message' => [
        'close_before_delete' => 'Please close the configuration before deleting it.',
        'already_enabled' => 'The configuration is already enabled.',
        'already_disabled' => 'The configuration is already disabled.',
        'config_conflict' => ':network is already associated with PSP: :psp and cannot be configured again.',
        'enable_conflict' => ':network is already associated with PSP: :psp and cannot be enabled again.',
        'invalid_state' => 'The route state does not allow this action. Refresh the list and retry.',
        'duplicate_config' => 'A :config_type configuration for :currency on the :network network with the :channel channel already exists and cannot be created again.',
        'route_conflict' => 'A :network network configuration for the same type and currency already exists. To change the channel, please modify the existing configuration.',
    ],
];
