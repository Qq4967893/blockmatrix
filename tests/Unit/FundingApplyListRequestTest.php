<?php

namespace Tests\Unit;

use App\Http\Requests\FundingApply\FundingApplyListRequest;
use App\Models\Api\FundingApply;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FundingApplyListRequestTest extends TestCase
{
    public function test_deposit_status_options_include_pending(): void
    {
        $this->assertSame([
            FundingApply::STATUS_PENDING,
            FundingApply::STATUS_APPROVED,
            FundingApply::STATUS_FAILED,
        ], FundingApply::DEPOSIT_STATUS_OPTIONS);
    }

    public function test_pending_deposit_status_is_accepted(): void
    {
        $request = new FundingApplyListRequest;
        $validator = Validator::make(['status' => FundingApply::STATUS_PENDING], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_unsupported_deposit_status_is_rejected(): void
    {
        $request = new FundingApplyListRequest;
        $validator = Validator::make(['status' => 1], $request->rules());

        $this->assertTrue($validator->fails());
    }
}
