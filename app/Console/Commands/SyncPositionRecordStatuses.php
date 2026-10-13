<?php

namespace App\Console\Commands;

use App\Services\Api\PositionRecordStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPositionRecordStatuses extends Command
{
    protected $signature = 'position-records:sync-statuses';

    protected $description = 'Activate started ads and disable expired ads';

    public function handle(PositionRecordStatusService $service): int
    {
        $summary = $service->sync();

        if ($summary['activated'] > 0 || $summary['disabled'] > 0) {
            Log::info('Position record statuses synchronized.', $summary);
        }

        $this->info(sprintf(
            'activated=%d disabled=%d',
            $summary['activated'],
            $summary['disabled'],
        ));

        return self::SUCCESS;
    }
}
