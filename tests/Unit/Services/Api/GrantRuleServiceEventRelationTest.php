<?php

namespace Tests\Unit\Services\Api;

use App\Services\Api\GrantRuleService;
use ReflectionClass;
use Scaffold\Framework\Enums\TypeGrantEvent;
use Tests\TestCase;

class GrantRuleServiceEventRelationTest extends TestCase
{
    public function test_normalize_payload_omits_missing_relation(): void
    {
        foreach ([
            TypeGrantEvent::FirstDeposited->value,
            TypeGrantEvent::Deposited->value,
            TypeGrantEvent::OpenTrade->value,
            TypeGrantEvent::CloseTrade->value,
        ] as $eventKey) {
            $result = $this->invokeNormalizeGrantRulePayload($this->autoPayload($eventKey, []));

            $this->assertArrayNotHasKey('relation', $result['event_params'], "Event [{$eventKey}] should not store relation when not provided.");
        }
    }

    public function test_normalize_payload_keeps_provided_relation(): void
    {
        $result = $this->invokeNormalizeGrantRulePayload($this->autoPayload(
            TypeGrantEvent::CloseTrade->value,
            [
                'trade_account_type' => 1,
                'relation' => 'or',
            ]
        ));

        $this->assertSame('or', $result['event_params']['relation']);
    }

    public function test_normalize_payload_fills_missing_numeric_params_with_zero(): void
    {
        $result = $this->invokeNormalizeGrantRulePayload($this->autoPayload(
            TypeGrantEvent::CloseTrade->value,
            ['trade_account_type' => 1]
        ));

        $this->assertSame(1, $result['event_params']['trade_account_type']);
        foreach (['count', 'position_size', 'lots', 'margin_order', 'profit'] as $paramKey) {
            $this->assertSame(0, $result['event_params'][$paramKey], "Param [{$paramKey}] should default to 0.");
        }
    }

    public function test_normalize_payload_does_not_fill_text_params(): void
    {
        $result = $this->invokeNormalizeGrantRulePayload($this->autoPayload(
            TypeGrantEvent::FirstDeposited->value,
            []
        ));

        $this->assertSame(0, $result['event_params']['amount']);
    }

    public function test_normalize_payload_does_not_add_relation_for_invitee_registered(): void
    {
        $result = $this->invokeNormalizeGrantRulePayload($this->autoPayload(
            TypeGrantEvent::InviteeRegistered->value,
            []
        ));

        $this->assertArrayNotHasKey('relation', $result['event_params']);
        $this->assertArrayNotHasKey('inviter_user_code', $result['event_params']);
    }

    /**
     * @return array<string, mixed>
     */
    private function autoPayload(string $eventKey, array $eventParams): array
    {
        return [
            'name' => 'grant rule',
            'target_type' => 2,
            'target' => 100,
            'grant_method' => 2,
            'event_key' => $eventKey,
            'event_params' => $eventParams,
            'total_count' => 0,
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function invokeNormalizeGrantRulePayload(array $payload): array
    {
        $reflection = new ReflectionClass(GrantRuleService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('normalizeGrantRulePayload');
        $method->setAccessible(true);

        return $method->invoke($service, $payload);
    }
}
