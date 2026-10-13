<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\UserCreditRecord;
use App\Repositories\Api\UserCreditLedgerRepository;
use App\Services\Api\UserService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class UserServiceCreditBonusOverviewTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('zh_CN');
    }

    public function test_credit_bonus_overview_reads_status_and_ledger_unlock_amount(): void
    {
        $ledgerRepo = Mockery::mock(UserCreditLedgerRepository::class);
        $ledgerRepo->shouldReceive('sumUnlockByCreditRecordId')
            ->once()
            ->with(11)
            ->andReturn(25.0);

        $overview = $this->invokeBuildOverview(new UserCreditRecord([
            'id' => 11,
            'grant_amount' => 100,
            'status' => UserCreditRecord::STATUS_AVAILABLE,
        ]), $ledgerRepo);

        $this->assertSame(100.0, $overview['bonus_amount']);
        $this->assertSame(25.0, $overview['unlocked_bonus_amount']);
        $this->assertSame(UserCreditRecord::STATUS_AVAILABLE, $overview['bonus_status']);
        $this->assertSame('可用', $overview['bonus_status_text']);
    }

    public function test_credit_bonus_overview_marks_unavailable_record_without_ledger_query(): void
    {
        $ledgerRepo = Mockery::mock(UserCreditLedgerRepository::class);
        $ledgerRepo->shouldReceive('sumUnlockByCreditRecordId')
            ->once()
            ->with(12)
            ->andReturn(0.0);

        $overview = $this->invokeBuildOverview(new UserCreditRecord([
            'id' => 12,
            'grant_amount' => 100,
            'status' => UserCreditRecord::STATUS_UNAVAILABLE,
        ]), $ledgerRepo);

        $this->assertSame(100.0, $overview['bonus_amount']);
        $this->assertSame(0.0, $overview['unlocked_bonus_amount']);
        $this->assertSame(UserCreditRecord::STATUS_UNAVAILABLE, $overview['bonus_status']);
        $this->assertSame('不可用', $overview['bonus_status_text']);
    }

    public function test_credit_bonus_overview_uses_unavailable_status_without_a_record(): void
    {
        $ledgerRepo = Mockery::mock(UserCreditLedgerRepository::class);
        $ledgerRepo->shouldNotReceive('sumUnlockByCreditRecordId');

        $overview = $this->invokeBuildOverview(null, $ledgerRepo);

        $this->assertSame(0.0, $overview['bonus_amount']);
        $this->assertSame(0.0, $overview['unlocked_bonus_amount']);
        $this->assertSame(UserCreditRecord::STATUS_UNAVAILABLE, $overview['bonus_status']);
        $this->assertSame('不可用', $overview['bonus_status_text']);
    }

    private function invokeBuildOverview(?UserCreditRecord $record, UserCreditLedgerRepository $ledgerRepo): array
    {
        $reflection = new ReflectionClass(UserService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $property = $reflection->getProperty('userCreditLedgerRepo');
        $property->setAccessible(true);
        $property->setValue($service, $ledgerRepo);

        $method = new ReflectionMethod(UserService::class, 'buildCreditBonusOverview');
        $method->setAccessible(true);

        return $method->invoke($service, $record);
    }
}
