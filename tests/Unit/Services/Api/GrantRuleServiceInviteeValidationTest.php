<?php

namespace Tests\Unit\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Api\GrantRule;
use App\Repositories\Api\CouponRepository;
use App\Repositories\Api\GrantRuleRepository;
use App\Repositories\Api\UserCouponRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Services\Api\GrantRuleService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use Scaffold\Framework\Enums\TypeGrantEvent;
use Tests\TestCase;

class GrantRuleServiceInviteeValidationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_create_reports_all_missing_inviter_user_codes(): void
    {
        app()->setLocale('zh_CN');

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getByUserCodes')
            ->once()
            ->with([10001, 111, 222], ['user_code', 'username'])
            ->andReturn([
                ['user_code' => 10001, 'username' => 'Existing Client'],
            ]);

        $service = $this->makeService(userRepo: $userRepo);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('111用户不存在；222用户不存在');

        $service->create($this->inviteePayload([
            'inviter_user_code' => [10001, 111, 222],
            'relation' => 'or',
        ]));
    }

    public function test_update_validates_template_array_inviter_user_codes(): void
    {
        app()->setLocale('zh_CN');

        $grantRule = new GrantRule;
        $grantRule->id = 10;
        $grantRule->status = GrantRule::STATUS_DISABLED;

        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $grantRuleRepo->shouldReceive('findById')->once()->with(10)->andReturn($grantRule);

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getByUserCodes')
            ->once()
            ->with([111, 222], ['user_code', 'username'])
            ->andReturn([]);

        $service = $this->makeService(
            grantRuleRepo: $grantRuleRepo,
            userRepo: $userRepo
        );

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('111用户不存在；222用户不存在');

        $service->update(10, $this->inviteePayload([
            ['param_key' => 'inviter_user_code', 'param_value' => [111, 222]],
            ['param_key' => 'relation', 'param_value' => 'or'],
        ]));
    }

    public function test_create_reports_invalid_and_missing_inviter_user_codes(): void
    {
        app()->setLocale('zh_CN');

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getByUserCodes')
            ->once()
            ->with([10001, 222], ['user_code', 'username'])
            ->andReturn([
                ['user_code' => 10001, 'username' => 'Existing Client'],
            ]);

        $service = $this->makeService(userRepo: $userRepo);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('abc用户ID格式错误；222用户不存在');

        $service->create($this->inviteePayload([
            'inviter_user_code' => [10001, 'abc', 222],
            'relation' => 'or',
        ]));
    }

    public function test_inviter_user_codes_are_normalized_to_integer_array(): void
    {
        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getByUserCodes')
            ->once()
            ->with([295317246, 123456789], ['user_code', 'username'])
            ->andReturn([
                ['user_code' => 295317246, 'username' => 'Client A'],
                ['user_code' => 123456789, 'username' => 'Client B'],
            ]);

        $service = $this->makeService(userRepo: $userRepo);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('validateAndNormalizeInviteeRegisteredUsers');
        $method->setAccessible(true);

        $payload = $method->invoke($service, [
            'event_key' => TypeGrantEvent::InviteeRegistered->value,
            'event_params' => [
                'relation' => 'or',
                'inviter_user_code' => [295317246, 123456789],
            ],
        ]);

        $this->assertSame(
            [295317246, 123456789],
            $payload['event_params']['inviter_user_code']
        );
    }

    private function makeService(
        ?GrantRuleRepository $grantRuleRepo = null,
        ?UserRepository $userRepo = null
    ): GrantRuleService {
        return new GrantRuleService(
            Mockery::mock(CouponRepository::class),
            $grantRuleRepo ?? Mockery::mock(GrantRuleRepository::class),
            $userRepo ?? Mockery::mock(UserRepository::class),
            Mockery::mock(UserCouponRepository::class),
            Mockery::mock(UserOperationLogRepository::class),
            Mockery::mock(AdminUserRepository::class),
        );
    }

    /**
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $eventParams
     * @return array<string, mixed>
     */
    private function inviteePayload(array $eventParams): array
    {
        return [
            'name' => 'Invitee registered grant rule',
            'target_type' => GrantRule::TARGET_COUPON,
            'target' => 100,
            'grant_method' => GrantRule::GRANT_METHOD_AUTO,
            'event_key' => TypeGrantEvent::InviteeRegistered->value,
            'event_params' => $eventParams,
            'start_at' => '2026-08-24 00:00:00',
            'end_at' => '2026-08-31 23:59:59',
        ];
    }
}
