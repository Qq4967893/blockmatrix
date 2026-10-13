<?php

namespace App\Jobs;

use App\Services\Api\WalletImportExecuteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WalletImportExecuteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(private readonly int $jobId)
    {
        $this->onQueue('wallet_batch_import');
    }

    public function handle(WalletImportExecuteService $service): void
    {
        $service->executeBatch($this->jobId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Wallet import execute job failed.', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);

        app(WalletImportExecuteService::class)->markJobAbnormal($this->jobId, $exception->getMessage());
    }
}
