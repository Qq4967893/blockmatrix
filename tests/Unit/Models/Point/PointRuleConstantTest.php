<?php

namespace Tests\Unit\Models\Point;

use App\Constants\FieldType;
use App\Models\Point\PointRuleConstant;
use Scaffold\Framework\Enums\TypePointEvent;
use Tests\TestCase;

class PointRuleConstantTest extends TestCase
{
    public function test_trade_account_type_uses_scaffold_select_options(): void
    {
        app()->setLocale('zh_CN');

        $scaffoldDetail = collect(TypePointEvent::details())
            ->firstWhere('value', TypePointEvent::OpenTrade->value);
        $expectedOptions = collect($scaffoldDetail['variable'] ?? [])
            ->firstWhere('variable', 'trade_account_type')['options'] ?? [];

        $openTrade = collect(PointRuleConstant::eventOptions())
            ->firstWhere('key', TypePointEvent::OpenTrade->value);
        $tradeAccountType = collect($openTrade['event_params'] ?? [])
            ->firstWhere('param_key', 'trade_account_type');

        $this->assertSame((string) FieldType::SELECT, $tradeAccountType['type']);
        $this->assertSame($expectedOptions, $tradeAccountType['options']);
    }
}
