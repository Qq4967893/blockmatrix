<?php

namespace Tests\Feature;

use App\Http\Requests\GrantRule\GrantRuleStoreRequest;
use App\Models\Api\GrantRule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Redirector;
use Tests\TestCase;

class GrantRuleRequestTest extends TestCase
{
    public function test_store_supports_all_grant_target_types(): void
    {
        foreach (GrantRule::SUPPORTED_TARGET_TYPES as $targetType) {
            $this->makeStoreRequest([
                'target_type' => $targetType,
                'target' => 100,
                'event_params' => [
                    'trade_account_type' => 0,
                    'relation' => 'and',
                ],
            ])->validateResolved();
        }

        $this->assertTrue(true);
    }

    public function test_store_rejects_unsupported_grant_target_type(): void
    {
        $this->expectException(HttpResponseException::class);

        $this->makeStoreRequest([
            'target_type' => 99,
        ])->validateResolved();
    }

    public function test_total_count_is_required_and_zero_means_unlimited(): void
    {
        $this->makeStoreRequest([
            'total_count' => 0,
            'event_params' => [
                'trade_account_type' => 0,
                'relation' => 'and',
            ],
        ])->validateResolved();

        $this->assertTrue(true);
    }

    public function test_total_count_is_required(): void
    {
        $payload = $this->storePayload();
        unset($payload['total_count']);

        $this->expectException(HttpResponseException::class);

        $this->makeStoreRequestFrom($payload)->validateResolved();
    }

    public function test_manual_coupon_delivery_allows_total_count_to_be_omitted(): void
    {
        $payload = $this->storePayload();
        unset($payload['total_count'], $payload['event_key']);
        $payload['grant_method'] = GrantRule::GRANT_METHOD_MANUAL;
        $payload['user_codes'] = [10001];

        $this->makeStoreRequestFrom($payload)->validateResolved();

        $this->assertTrue(true);
    }

    public function test_manual_delivery_allows_total_count_to_be_omitted_for_all_target_types(): void
    {
        foreach (GrantRule::SUPPORTED_TARGET_TYPES as $targetType) {
            $payload = $this->storePayload();
            unset($payload['total_count'], $payload['event_key']);
            $payload['target_type'] = $targetType;
            $payload['grant_method'] = GrantRule::GRANT_METHOD_MANUAL;
            $payload['user_codes'] = [10001];

            $this->makeStoreRequestFrom($payload)->validateResolved();
        }

        $this->assertTrue(true);
    }

    public function test_manual_delivery_accepts_comma_separated_user_codes(): void
    {
        $payload = $this->storePayload();
        unset($payload['total_count'], $payload['event_key']);
        $payload['grant_method'] = GrantRule::GRANT_METHOD_MANUAL;
        $payload['user_codes'] = '10001，10002， 10003';

        $request = $this->makeStoreRequestFrom($payload);
        $request->validateResolved();

        $this->assertSame([10001, 10002, 10003], $request->input('user_codes'));
    }

    public function test_auto_delivery_still_requires_total_count_for_non_coupon_target(): void
    {
        $payload = $this->storePayload();
        unset($payload['total_count']);
        $payload['target_type'] = GrantRule::TARGET_POINTS_XP;

        $this->expectException(HttpResponseException::class);

        $this->makeStoreRequestFrom($payload)->validateResolved();
    }

    public function test_auto_close_trade_allows_selected_event_params_only(): void
    {
        $request = $this->makeStoreRequest([
            'event_params' => [
                'count' => 1,
                'position_size' => 1,
                'relation' => 'or',
            ],
        ]);

        $request->validateResolved();

        $this->assertTrue(true);
    }

    public function test_auto_close_trade_ignores_blank_unselected_template_params(): void
    {
        $request = $this->makeStoreRequest([
            'event_params' => [
                ['param_key' => 'count', 'param_value' => 1],
                ['param_key' => 'position_size', 'param_value' => 1],
                ['param_key' => 'lots', 'param_value' => ''],
                ['param_key' => 'margin_order', 'param_value' => ''],
                ['param_key' => 'profit', 'param_value' => ''],
                ['param_key' => 'relation', 'param_value' => 'or'],
            ],
        ]);

        $request->validateResolved();

        $this->assertTrue(true);
    }

    public function test_auto_close_trade_requires_at_least_one_business_event_param(): void
    {
        $request = $this->makeStoreRequest([
            'event_params' => [
                'relation' => 'or',
            ],
        ]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    public function test_invitee_registered_allows_event_params_to_be_omitted(): void
    {
        $request = $this->makeStoreRequest([
            'event_key' => 'invitee_registered',
        ]);

        $request->validateResolved();

        $this->assertTrue(true);
    }

    public function test_invitee_registered_ignores_relation_when_scaffold_defines_none(): void
    {
        $request = $this->makeStoreRequest([
            'event_key' => 'invitee_registered',
            'event_params' => [
                'inviter_user_code' => [295317246, 123456789],
                'relation' => 'or',
            ],
        ]);

        $request->validateResolved();

        $this->assertTrue(true);
    }

    public function test_invitee_registered_allows_optional_inviter_user_code(): void
    {
        $request = $this->makeStoreRequest([
            'event_key' => 'invitee_registered',
            'event_params' => [],
        ]);

        $request->validateResolved();

        $this->assertTrue(true);
    }

    public function test_invitee_registered_allows_inviter_users_without_relation(): void
    {
        $request = $this->makeStoreRequest([
            'event_key' => 'invitee_registered',
            'event_params' => [
                'inviter_user_code' => [295317246, 123456789],
            ],
        ]);

        $request->validateResolved();

        $this->assertTrue(true);
    }

    public function test_auto_event_allows_relation_to_be_omitted(): void
    {
        $request = $this->makeStoreRequest([
            'event_key' => 'registered',
            'event_params' => [],
        ]);

        $request->validateResolved();

        $this->assertTrue(true);
    }

    public function test_auto_trade_event_allows_relation_to_be_omitted(): void
    {
        $request = $this->makeStoreRequest([
            'event_params' => [
                'trade_account_type' => 0,
            ],
        ]);

        $request->validateResolved();

        $this->assertTrue(true);
    }

    public function test_auto_event_rejects_invalid_relation(): void
    {
        $request = $this->makeStoreRequest([
            'event_key' => 'registered',
            'event_params' => [
                'relation' => 'or',
            ],
        ]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    public function test_auto_event_with_defined_params_rejects_null_event_params(): void
    {
        $request = $this->makeStoreRequest([
            'event_params' => null,
        ]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    private function makeStoreRequest(array $overrides): GrantRuleStoreRequest
    {
        $payload = array_replace_recursive($this->storePayload(), $overrides);

        return $this->makeStoreRequestFrom($payload);
    }

    private function makeStoreRequestFrom(array $payload): GrantRuleStoreRequest
    {
        $request = GrantRuleStoreRequest::create('/api/grant_rules', 'POST', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function storePayload(): array
    {
        return [
            'name' => 'grant rule',
            'target_type' => 1,
            'target' => 10001,
            'grant_method' => 2,
            'total_count' => 20,
            'start_at' => '2026-07-17 00:00:00',
            'end_at' => '2026-07-17 00:00:00',
            'event_key' => 'close_trade',
        ];
    }
}
