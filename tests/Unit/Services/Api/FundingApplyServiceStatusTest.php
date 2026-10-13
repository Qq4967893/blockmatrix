<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\FundingApply;
use App\Services\Api\FundingApplyService;
use ReflectionClass;
use Tests\TestCase;

class FundingApplyServiceStatusTest extends TestCase
{
    public function test_pending_record_without_user_is_displayed_as_abnormal(): void
    {
        $this->assertSame(
            FundingApply::STATUS_FAILED,
            $this->resolveDisplayStatus(FundingApply::STATUS_PENDING, 0)
        );
    }

    public function test_pending_record_with_user_remains_pending(): void
    {
        $this->assertSame(
            FundingApply::STATUS_PENDING,
            $this->resolveDisplayStatus(FundingApply::STATUS_PENDING, 10001)
        );
    }

    private function resolveDisplayStatus(int $status, int $userCode): int
    {
        $reflection = new ReflectionClass(FundingApplyService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('resolveApplyDisplayStatusCode');
        $method->setAccessible(true);

        return $method->invoke($service, $status, $userCode);
    }
}
