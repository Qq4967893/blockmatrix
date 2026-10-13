<?php

namespace Tests\Unit;

use App\Constants\BalanceFlowFeeCategory;
use App\Http\Requests\BalanceFlow\BalanceFlowListRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BalanceFlowListRequestTest extends TestCase
{
    public function test_visible_fee_categories_are_accepted(): void
    {
        $request = new BalanceFlowListRequest;

        foreach (BalanceFlowFeeCategory::VISIBLE as $feeCategory) {
            self::assertFalse(
                Validator::make(['fund_type' => $feeCategory], $request->rules())->fails(),
                "Fee category {$feeCategory} should be accepted.",
            );
        }
    }

    public function test_hidden_fee_category_is_rejected(): void
    {
        $request = new BalanceFlowListRequest;

        self::assertTrue(
            Validator::make(['fund_type' => 1], $request->rules())->fails(),
        );
    }

    public function test_visible_fee_sub_types_are_accepted(): void
    {
        $request = new BalanceFlowListRequest;

        foreach (BalanceFlowFeeCategory::VISIBLE_SUB_TYPES as $feeSubType) {
            self::assertFalse(
                Validator::make(['fund_sub_type' => $feeSubType], $request->rules())->fails(),
                "Fee sub type {$feeSubType} should be accepted.",
            );
        }
    }

    public function test_unsupported_fee_sub_type_is_rejected(): void
    {
        $request = new BalanceFlowListRequest;

        self::assertTrue(
            Validator::make(['fund_sub_type' => 1], $request->rules())->fails(),
        );
    }
}
