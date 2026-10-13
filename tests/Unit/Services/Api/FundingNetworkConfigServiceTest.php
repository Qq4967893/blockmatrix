<?php

namespace Tests\Unit\Services\Api;

use App\Exceptions\BusinessException;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Services\Api\FundingNetworkConfigService;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Scaffold\FluxWallet\Exceptions\FluxWalletException;
use Scaffold\FluxWallet\Facades\FluxWallet;
use Tests\TestCase;

class FundingNetworkConfigServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_options_returns_operation_specific_asset_relations(): void
    {
        app()->setLocale('en_US');

        FluxWallet::shouldReceive('listSupportedAssets')
            ->once()
            ->andReturn([
                [
                    'provider_id' => 'cobo',
                    'chain_id' => 'eip155:11155111',
                    'chain_names' => ['en_US' => 'Ethereum Sepolia Testnet'],
                    'icon_url' => 'https://example.com/eip155_11155111.png',
                    'token_symbol' => 'USDT',
                    'deposit_supported' => true,
                    'withdraw_supported' => true,
                    'enabled' => true,
                ],
                [
                    'provider_id' => 'cobo',
                    'chain_id' => 'tron:0x94a9059e',
                    'chain_names' => ['en_US' => 'Tron Testnet Nile'],
                    'icon_url' => 'https://example.com/tron_0x94a9059e.png',
                    'token_symbol' => 'USDT',
                    'deposit_supported' => true,
                    'withdraw_supported' => true,
                    'enabled' => true,
                ],
                [
                    'provider_id' => 'cregis',
                    'chain_id' => 'tron:0xcd8690dc',
                    'chain_names' => ['en_US' => 'Tron Testnet Shasta'],
                    'icon_url' => 'https://example.com/tron_0xcd8690dc.png',
                    'token_symbol' => 'USDT',
                    'deposit_supported' => true,
                    'withdraw_supported' => true,
                    'enabled' => true,
                ],
                [
                    'provider_id' => 'disabled-provider',
                    'chain_id' => 'tron:0xcd8690dc',
                    'chain_names' => ['en_US' => 'Tron Testnet Shasta'],
                    'icon_url' => 'https://example.com/tron_0xcd8690dc.png',
                    'token_symbol' => 'USDT',
                    'deposit_supported' => true,
                    'withdraw_supported' => true,
                    'enabled' => false,
                ],
            ]);

        $service = new FundingNetworkConfigService(
            Mockery::mock(UserOperationLogRepository::class),
            Mockery::mock(AdminUserRepository::class),
        );

        $options = $service->options();
        $withdrawal = $this->findConfigType($options['config_types'], 'withdrawal');

        $this->assertSame([
            ['code' => 'enabled', 'name' => 'Enabled'],
            ['code' => 'disabled', 'name' => 'Disabled'],
        ], $options['statuses']);

        $shastaRelations = array_values(array_filter(
            $withdrawal['settings']['relations'],
            static fn (array $relation): bool => $relation['network'] === 'tron:0xcd8690dc'
        ));

        $this->assertCount(1, $shastaRelations);
        $this->assertSame('Tron Testnet Shasta', $shastaRelations[0]['network_text'] ?? null);

        $shastaChannels = $shastaRelations[0]['channels'] ?? [];
        $this->assertSame(['cregis', 'disabled-provider'], array_column($shastaChannels, 'code'));
        $this->assertTrue($shastaChannels[0]['enabled'] ?? false);
        $this->assertContains('disabled-provider', array_column($options['channels'], 'code'));

        $disabledChannels = array_values(array_filter(
            $shastaChannels,
            static fn (array $channel): bool => $channel['code'] === 'disabled-provider'
        ));

        $this->assertFalse($disabledChannels[0]['enabled'] ?? true);
    }

    public function test_create_does_not_check_enabled_route_conflict(): void
    {
        $operator = Mockery::mock(UserOperationLogRepository::class);
        $operator->shouldReceive('addRecord')->once();
        $operator->shouldReceive('getBatchOperateInfo')->once()->andReturn([]);
        $adminUserRepository = Mockery::mock(AdminUserRepository::class);
        $adminUserRepository->shouldReceive('getColumnByIds')->once()->andReturn([]);

        Auth::shouldReceive('user')->andReturn((object) [
            'id' => 10,
            'login_name' => 'admin',
        ]);

        FluxWallet::shouldReceive('listNetworkRoutes')->never();
        FluxWallet::shouldReceive('listSupportedAssets')
            ->once()
            ->andReturn([
                [
                    'provider_id' => 'weoney',
                    'chain_id' => 'eip155:11155111',
                    'chain_names' => ['en_US' => 'Ethereum Sepolia Testnet'],
                    'icon_url' => 'https://example.com/eip155_11155111.png',
                    'token_symbol' => 'USDT',
                    'deposit_supported' => true,
                    'withdraw_supported' => true,
                    'enabled' => true,
                ],
            ]);
        FluxWallet::shouldReceive('createNetworkRoutes')
            ->once()
            ->with([
                [
                    'config_type' => 'deposit',
                    'token_symbol' => 'USDT',
                    'chain_id' => 'eip155:11155111',
                    'provider_id' => 'cobo',
                ],
            ], 'admin:10')
            ->andReturn([
                [
                    'id' => '101',
                    'config_type' => 'deposit',
                    'token_symbol' => 'USDT',
                    'chain_id' => 'eip155:11155111',
                    'provider_id' => 'cobo',
                    'status' => 'not_enabled',
                    'version' => 1,
                    'updated_by' => 'admin:10',
                ],
            ]);

        $service = new FundingNetworkConfigService(
            $operator,
            $adminUserRepository,
        );

        $result = $service->create([
            [
                'config_type' => 'deposit',
                'currency' => 'USDT',
                'network' => 'eip155:11155111',
                'channel' => 'cobo',
            ],
        ]);

        $this->assertSame('not_enabled', $result[0]['status'] ?? null);
        $this->assertSame('cobo', $result[0]['channel'] ?? null);
    }

    public function test_network_display_text_matches_asset_and_appends_token_standard(): void
    {
        app()->setLocale('zh_CN');

        FluxWallet::shouldReceive('listSupportedAssets')
            ->once()
            ->andReturn([
                [
                    'chain_id' => 'eip155:11155111',
                    'chain_names' => ['zh_CN' => '以太坊 Sepolia 测试网'],
                    'token_symbol' => 'USDT',
                    'token_standard' => 'ERC20',
                ],
            ]);

        $service = new FundingNetworkConfigService(
            Mockery::mock(UserOperationLogRepository::class),
            Mockery::mock(AdminUserRepository::class),
        );

        $this->assertSame(
            '以太坊 Sepolia 测试网(ERC20)',
            $service->networkDisplayText('eip155:11155111', 'USDT')
        );
    }

    public function test_create_translates_wallet_conflict_to_duplicate_config_message(): void
    {
        app()->setLocale('zh_CN');

        Auth::shouldReceive('user')->andReturn((object) [
            'id' => 10,
            'login_name' => 'admin',
        ]);
        $this->mockSupportedAssets();
        FluxWallet::shouldReceive('createNetworkRoutes')
            ->once()
            ->with([
                [
                    'config_type' => 'withdrawal',
                    'token_symbol' => 'USDT',
                    'chain_id' => 'tron:0xcd8690dc',
                    'provider_id' => 'cregis',
                ],
            ], 'admin:10')
            ->andThrow(new FluxWalletException('network route conflict'));
        FluxWallet::shouldReceive('listNetworkRoutes')
            ->once()
            ->with('withdrawal', 'USDT', 'tron:0xcd8690dc', '', '', 100, 0)
            ->andReturn([
                $this->networkRoute(55, 'withdrawal', 'USDT', 'tron:0xcd8690dc', 'cregis', 'disabled'),
            ]);

        $service = $this->makeService();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('出金（USDT）：波场 Shasta 测试网 网络 cregis 通道配置已存在，不可重复创建');

        $service->create([
            ['config_type' => 'withdrawal', 'currency' => 'USDT', 'network' => 'tron:0xcd8690dc', 'channel' => 'cregis'],
        ]);
    }

    public function test_create_translates_wallet_conflict_to_route_conflict_message(): void
    {
        app()->setLocale('zh_CN');

        Auth::shouldReceive('user')->andReturn((object) [
            'id' => 10,
            'login_name' => 'admin',
        ]);
        FluxWallet::shouldReceive('createNetworkRoutes')
            ->once()
            ->with([
                [
                    'config_type' => 'withdrawal',
                    'token_symbol' => 'USDT',
                    'chain_id' => 'tron:0xcd8690dc',
                    'provider_id' => 'cregis',
                ],
            ], 'admin:10')
            ->andThrow(new FluxWalletException('network route conflict'));
        FluxWallet::shouldReceive('listSupportedAssets')
            ->once()
            ->andReturn([
                [
                    'chain_id' => 'tron:0xcd8690dc',
                    'chain_names' => ['zh_CN' => '波场 Shasta 测试网'],
                    'token_symbol' => 'USDT',
                ],
            ]);
        FluxWallet::shouldReceive('listNetworkRoutes')
            ->once()
            ->with('withdrawal', 'USDT', 'tron:0xcd8690dc', '', '', 100, 0)
            ->andReturn([
                $this->networkRoute(56, 'withdrawal', 'USDT', 'tron:0xcd8690dc', 'cobo', 'enabled'),
            ]);

        $service = $this->makeService();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('同类型、币种下，波场 Shasta 测试网 网络已配置，更换通道请前往已有列表修改');

        $service->create([
            ['config_type' => 'withdrawal', 'currency' => 'USDT', 'network' => 'tron:0xcd8690dc', 'channel' => 'cregis'],
        ]);
    }

    public function test_create_falls_back_to_route_conflict_message_with_network_text(): void
    {
        app()->setLocale('zh_CN');

        Auth::shouldReceive('user')->andReturn((object) [
            'id' => 10,
            'login_name' => 'admin',
        ]);
        FluxWallet::shouldReceive('listSupportedAssets')
            ->once()
            ->andReturn([
                [
                    'chain_id' => 'tron:0xcd8690dc',
                    'chain_names' => ['zh_CN' => '波场 Shasta 测试网'],
                    'token_symbol' => 'USDT',
                ],
            ]);
        FluxWallet::shouldReceive('listNetworkRoutes')
            ->once()
            ->with('withdrawal', 'USDT', 'tron:0xcd8690dc', '', '', 100, 0)
            ->andReturn([]);
        FluxWallet::shouldReceive('createNetworkRoutes')
            ->once()
            ->andThrow(new FluxWalletException('network route conflict'));

        $service = $this->makeService();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('同类型、币种下，波场 Shasta 测试网 网络已配置，更换通道请前往已有列表修改');

        $service->create([
            ['config_type' => 'withdrawal', 'currency' => 'USDT', 'network' => 'tron:0xcd8690dc', 'channel' => 'cregis'],
        ]);
    }

    public function test_create_rejects_duplicate_configs_within_same_batch(): void
    {
        app()->setLocale('zh_CN');

        $this->mockSupportedAssets();
        FluxWallet::shouldReceive('createNetworkRoutes')->never();
        FluxWallet::shouldReceive('listNetworkRoutes')->never();

        $service = $this->makeService();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('出金（USDT）：波场 Shasta 测试网 网络 cregis 通道配置已存在，不可重复创建');

        $service->create([
            ['config_type' => 'withdrawal', 'currency' => 'USDT', 'network' => 'tron:0xcd8690dc', 'channel' => 'cregis'],
            ['config_type' => 'withdrawal', 'currency' => 'USDT', 'network' => 'tron:0xcd8690dc', 'channel' => 'cregis'],
        ]);
    }

    private function makeService(): FundingNetworkConfigService
    {
        return new FundingNetworkConfigService(
            Mockery::mock(UserOperationLogRepository::class),
            Mockery::mock(AdminUserRepository::class),
        );
    }

    private function mockSupportedAssets(): void
    {
        FluxWallet::shouldReceive('listSupportedAssets')
            ->once()
            ->andReturn([
                [
                    'chain_id' => 'tron:0xcd8690dc',
                    'chain_names' => ['zh_CN' => '波场 Shasta 测试网'],
                    'token_symbol' => 'USDT',
                ],
            ]);
    }

    private function networkRoute(int $id, string $configType, string $tokenSymbol, string $chainId, string $providerId, string $status): array
    {
        return [
            'id' => (string) $id,
            'config_type' => $configType,
            'token_symbol' => $tokenSymbol,
            'chain_id' => $chainId,
            'provider_id' => $providerId,
            'status' => $status,
            'version' => 1,
            'updated_by' => 'admin:1',
        ];
    }

    private function findConfigType(array $configTypes, string $code): array
    {
        foreach ($configTypes as $configType) {
            if (($configType['code'] ?? '') === $code) {
                return $configType;
            }
        }

        $this->fail(sprintf('Config type [%s] was not found.', $code));
    }
}
