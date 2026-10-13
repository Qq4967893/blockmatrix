<?php

namespace Tests\Unit\Services\Api;

use App\Repositories\Api\CouponRepository;
use App\Repositories\Api\GrantRuleRepository;
use App\Repositories\Api\UserCouponRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Services\Api\GrantRuleService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class GrantRuleServiceUserValidationMessageTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_message_lists_not_exist_user_codes(): void
    {
        app()->setLocale('zh_CN');

        $message = $this->makeService()->userValidationFailureMessage([
            'valid_count' => 0,
            'valid_data' => [],
            'invalid_count' => 3,
            'invalid_data' => [
                ['user_code' => '10001', 'reason' => trans('user.message.not_exist')],
                ['user_code' => '10002', 'reason' => trans('user.message.not_exist')],
                ['user_code' => '10003', 'reason' => trans('user.message.not_exist')],
            ],
        ]);

        $this->assertSame('客户不存在：10001,10002,10003', $message);
    }

    public function test_message_groups_reasons_with_user_codes(): void
    {
        app()->setLocale('zh_CN');

        $message = $this->makeService()->userValidationFailureMessage([
            'valid_count' => 1,
            'valid_data' => [
                ['user_code' => 10001, 'name' => 'Existing Client', 'status' => 1, 'status_text' => '正常'],
            ],
            'invalid_count' => 2,
            'invalid_data' => [
                ['user_code' => 'abc', 'reason' => trans('grant_rule.message.invalid_user_code_format')],
                ['user_code' => '10003', 'reason' => trans('user.message.not_exist')],
            ],
        ]);

        $this->assertSame('用户ID格式错误：abc；客户不存在：10003', $message);
    }

    public function test_message_keeps_reason_without_user_code(): void
    {
        app()->setLocale('zh_CN');

        $message = $this->makeService()->userValidationFailureMessage([
            'valid_count' => 0,
            'valid_data' => [],
            'invalid_count' => 1,
            'invalid_data' => [
                ['user_code' => '', 'reason' => trans('grant_rule.message.user_code_limit_exceeded', ['count' => 500])],
            ],
        ]);

        $this->assertSame('用户ID数量不能超过500个', $message);
    }

    public function test_message_falls_back_to_generic_text_without_details(): void
    {
        app()->setLocale('zh_CN');

        $message = $this->makeService()->userValidationFailureMessage([
            'valid_count' => 0,
            'valid_data' => [],
            'invalid_count' => 2,
            'invalid_data' => [],
        ]);

        $this->assertSame(trans('grant_rule.message.user_validation_failed'), $message);
    }

    private function makeService(): GrantRuleService
    {
        return new GrantRuleService(
            Mockery::mock(CouponRepository::class),
            Mockery::mock(GrantRuleRepository::class),
            Mockery::mock(UserRepository::class),
            Mockery::mock(UserCouponRepository::class),
            Mockery::mock(UserOperationLogRepository::class),
            Mockery::mock(AdminUserRepository::class),
        );
    }
}
