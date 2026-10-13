<?php

namespace App\Console\Commands;

use App\Services\Api\FundingApplyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoRetryWithdrawals extends Command
{
    protected $signature = 'withdrawals:auto-wallet-retry';

    protected $description = 'Automatically retry wallet withdrawals that Walletd reported as not submitted to PSP';

    public function handle(FundingApplyService $service): int
    {
        $summary = $service->retryDueWithdrawals();

        if ($summary['scanned'] > 0) {
            Log::info('Automatic wallet withdrawal retry completed.', $summary);
        }

        $this->info(sprintf(
            'scanned=%d retried=%d skipped=%d exhausted=%d failed=%d',
            $summary['scanned'],
            $summary['retried'],
            $summary['skipped'],
            $summary['exhausted'],
            $summary['failed'],
        ));

        return self::SUCCESS;
    }
}
