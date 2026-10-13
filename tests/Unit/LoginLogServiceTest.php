<?php

namespace Tests\Unit;

use App\Repositories\Api\CountryRepository;
use App\Repositories\Api\LoginLogRepository;
use App\Services\Api\LoginLogService;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Scaffold\Framework\Models\Api\LoginLog;

class LoginLogServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->instance('config', new ConfigRepository(['app.locale' => 'zh_CN']));

        $loader = new ArrayLoader();
        $loader->addMessages('zh_CN', 'user', [
            'boolean' => ['yes' => '是', 'no' => '否'],
            'login_type' => ['email' => '邮箱登录', 'wallet' => '钱包登录', 'line' => 'LINE 登录', 'telegram' => 'Telegram 登录', 'apple' => 'Apple 登录', 'google' => 'Google 登录'],
        ]);
        $loader->addMessages('en_US', 'user', [
            'boolean' => ['yes' => 'Yes', 'no' => 'No'],
            'login_type' => ['email' => 'Email Login', 'wallet' => 'Wallet Login', 'line' => 'LINE Login', 'telegram' => 'Telegram Login', 'apple' => 'Apple Login', 'google' => 'Google Login'],
        ]);

        $translator = new Translator($loader, 'zh_CN');
        $container->instance('translator', $translator);
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_options_return_all_scaffold_login_types(): void
    {
        app('translator')->setLocale('en_US');

        $service = new LoginLogService(
            Mockery::mock(LoginLogRepository::class),
            Mockery::mock(CountryRepository::class)
        );

        self::assertSame([
            ['key' => LoginLog::TYPE_EMAIL, 'value' => 'Email Login'],
            ['key' => LoginLog::TYPE_WALLET, 'value' => 'Wallet Login'],
            ['key' => LoginLog::TYPE_LINE, 'value' => 'LINE Login'],
            ['key' => LoginLog::TYPE_TELEGRAM, 'value' => 'Telegram Login'],
            ['key' => LoginLog::TYPE_APPLE, 'value' => 'Apple Login'],
            ['key' => LoginLog::TYPE_GOOGLE, 'value' => 'Google Login'],
        ], $service->options()['login_types']);
    }

    public function test_get_list_formats_login_log_rows(): void
    {
        $filters = [
            'keywords' => '862023036',
            'keyword_type' => 'user_code',
            'is_latest' => 1,
            'country' => 'CHN',
            'login_type' => LoginLog::TYPE_EMAIL,
            'login_time_start' => '2026-07-29 00:00:00',
            'login_time_end' => '2026-07-29 23:59:59',
        ];

        $row = (object) [
            'id' => 10,
            'request_id' => 'req-1',
            'user_code' => 862023036,
            'email' => 'user@example.com',
            'is_latest' => 1,
            'ip' => '127.0.0.1',
            'country' => 'CHN',
            'user_agent' => 'Mozilla/5.0',
            'login_type' => LoginLog::TYPE_EMAIL,
            'created_at' => '2026-07-29 09:07:47',
            'device_uuid' => 'device-uuid-1',
            'timezone' => 'Asia/Shanghai',
            'canvas_hash' => 'canvas-hash-1',
            'audio_hash' => 'audio-hash-1',
            'screen_resolution' => '1920x1080',
            'device_hash' => 'device-hash-1',
        ];

        $loginLogRepo = Mockery::mock(LoginLogRepository::class);
        $loginLogRepo->shouldReceive('getList')
            ->once()
            ->with($filters, 20, 0)
            ->andReturn([[$row], 1]);

        $countryRepo = Mockery::mock(CountryRepository::class);
        $countryRepo->shouldReceive('getByAlphas')
            ->once()
            ->with(['CHN'], ['alpha', 'name'])
            ->andReturn([
                ['alpha' => 'CHN', 'name' => '{"zh_CN":"中国","en_US":"China"}'],
            ]);

        $service = new LoginLogService($loginLogRepo, $countryRepo);

        self::assertSame([
            'data' => [[
                'id' => 10,
                'request_id' => 'req-1',
                'user_code' => 862023036,
                'email' => 'user@example.com',
                'is_latest' => 1,
                'is_latest_text' => '是',
                'ip' => '127.0.0.1',
                'country' => 'CHN',
                'country_text' => '中国',
                'user_agent' => 'Mozilla/5.0',
                'device_uuid' => 'device-uuid-1',
                'timezone' => 'Asia/Shanghai',
                'canvas_hash' => 'canvas-hash-1',
                'audio_hash' => 'audio-hash-1',
                'screen_resolution' => '1920x1080',
                'device_hash' => 'device-hash-1',
                'login_type' => LoginLog::TYPE_EMAIL,
                'login_type_text' => '邮箱登录',
                'login_time' => '2026-07-29 09:07:47',
            ]],
            'total_count' => 1,
        ], $service->getList($filters, 20, 0));
    }
}
