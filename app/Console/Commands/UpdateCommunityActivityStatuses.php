<?php

namespace App\Console\Commands;

use App\Services\Api\CommunityActivityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateCommunityActivityStatuses extends Command
{
    protected $signature = 'community-activities:update-statuses';

    protected $description = 'Automatically publish and void community activities by activity time';

    public function handle(CommunityActivityService $service): int
    {
        $summary = $service->updateActivityStatuses();

        if ($summary['voided'] > 0 || $summary['online'] > 0) {
            Log::info('Community activity status refresh completed.', $summary);
        }

        $this->info(sprintf('voided=%d online=%d', $summary['voided'], $summary['online']));

        return self::SUCCESS;
    }
}
