<?php

namespace Tests\Unit;

use App\Http\Requests\CreditFlow\CreditFlowListRequest;
use Illuminate\Support\Facades\Validator;
use Scaffold\Framework\Enums\TypeCreditLedger;
use Tests\TestCase;

class CreditFlowListRequestTest extends TestCase
{
    public function test_ledger_fund_types_are_accepted(): void
    {
        $request = new CreditFlowListRequest;

        foreach (TypeCreditLedger::cases() as $type) {
            self::assertFalse(
                Validator::make(['fund_type' => $type->value], $request->rules())->fails(),
                "Fund type {$type->value} should be accepted.",
            );
        }
    }

    public function test_legacy_and_unknown_fund_types_are_rejected(): void
    {
        $request = new CreditFlowListRequest;

        foreach ([0, 17, 18, 99] as $fundType) {
            self::assertTrue(
                Validator::make(['fund_type' => $fundType], $request->rules())->fails(),
                "Fund type {$fundType} should be rejected.",
            );
        }
    }

    public function test_credit_types_are_accepted(): void
    {
        $request = new CreditFlowListRequest;

        foreach ([0, 1] as $creditType) {
            self::assertFalse(
                Validator::make(['credit_type' => $creditType], $request->rules())->fails(),
                "Credit type {$creditType} should be accepted.",
            );
        }
    }

    public function test_unknown_credit_types_are_rejected(): void
    {
        $request = new CreditFlowListRequest;

        foreach ([2, 99, 'x'] as $creditType) {
            self::assertTrue(
                Validator::make(['credit_type' => $creditType], $request->rules())->fails(),
                "Credit type {$creditType} should be rejected.",
            );
        }
    }

    public function test_pagination_normalization_values_remain_accepted(): void
    {
        $request = new CreditFlowListRequest;

        self::assertFalse(
            Validator::make(['limit' => 0, 'offset' => -1], $request->rules())->fails(),
        );
    }
}
