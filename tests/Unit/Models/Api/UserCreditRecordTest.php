<?php

namespace Tests\Unit\Models\Api;

use App\Models\Api\UserCreditRecord;
use Scaffold\Framework\Enums\CreditBizType;
use Tests\TestCase;

class UserCreditRecordTest extends TestCase
{
    public function test_credit_record_constants_and_casts_are_defined_on_model(): void
    {
        $record = new UserCreditRecord;

        $this->assertSame(DB_API, $record->getConnectionName());
        $this->assertSame('user_credit_record', $record->getTable());
        $this->assertSame(0, UserCreditRecord::STATUS_UNAVAILABLE);
        $this->assertSame(1, UserCreditRecord::STATUS_AVAILABLE);
        $this->assertSame('USDT', UserCreditRecord::CURRENCY);
        $this->assertSame(CreditBizType::class, $record->getCasts()['biz_type']);
        $this->assertSame('array', $record->getCasts()['unlock_config']);
        $this->assertSame('datetime', $record->getCasts()['end_at']);
    }
}
