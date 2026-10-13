<?php

namespace App\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Api\FundingNetworkConfig;
use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use Illuminate\Support\Facades\Auth;
use Scaffold\FluxWallet\Exceptions\FluxWalletException;
use Scaffold\FluxWallet\Facades\FluxWallet;
use Wallet\V1\ListNetworkRoutesRequest\SortField;

class FundingNetworkConfigService
{
    /**
     * @var array<string, string>|null
     */
    private ?array $networkTextMap = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $supportedAssets = null;

    public function __construct(
        private UserOperationLogRepository $userOperationLogRepo,
        private AdminUserRepository $adminUserRepo,
    ) {
    }

    /**
     * @return array<int, array{provider_id:string,chain_id:string,chain_names:array<string,string>,icon_url:string,token_symbol:string,token_standard:string,deposit_supported:bool,withdraw_supported:bool,enabled:bool}>
     */
    public function listSupportedAssets(): array
    {
        return $this->supportedAssets ??= FluxWallet::listSupportedAssets();
    }

    public function options(): array
    {
        $assets = $this->listSupportedAssets();

        return [
            'config_types' => $this->configTypeOptions($assets),
            'networks' => $this->networkOptions($assets),
            'statuses' => FundingNetworkConfig::statusOptions(),
            'channels' => $this->simpleOptions($assets, 'provider_id'),
            'currencies' => $this->simpleOptions($assets, 'token_symbol'),
        ];
    }

    public function list(array $filters, int $limit): array
    {
        $pageAfterId = (int) ($filters['page_after_id'] ?? $filters['offset'] ?? 0);
        $routes = FluxWallet::listNetworkRoutes(
            $filters['config_type'] ?? '',
            '',
            $filters['network'] ?? '',
            $filters['channel'] ?? '',
            $filters['status'] ?? '',
            $limit + 1,
            $pageAfterId,
            SortField::SORT_FIELD_CONFIG_TYPE,
        );

        $hasMore = count($routes) > $limit;
        $data = array_slice($routes, 0, $limit);
        $operatorInfo = $this->getRouteOperatorInfo($data);
        $data = array_map(fn (array $item): array => $this->decorateNetworkRoute($item, $operatorInfo), $data);
        $nextPageAfterId = $hasMore && $data !== [] ? (int) $data[array_key_last($data)]['id'] : 0;

        return [
            'data' => $data,
            'total_count' => count($data),
            'has_more' => $hasMore,
            'next_page_after_id' => $nextPageAfterId,
        ];
    }

    public function create(array $configs): array
    {
        $routes = array_map(static fn (array $config): array => [
            'config_type' => (string) $config['config_type'],
            'token_symbol' => (string) $config['currency'],
            'chain_id' => (string) $config['network'],
            'provider_id' => (string) $config['channel'],
        ], $configs);

        // 同一批次内完全重复的配置直接拒绝，不请求钱包服务
        $this->ensureNoDuplicateConfigInBatch($routes);

        try {
            $createdRoutes = $this->mutateNetworkRoutes(
                fn (): array => FluxWallet::createNetworkRoutes($routes, $this->resolveGrpcOperator())
            );
        } catch (FluxWalletException $exception) {
            // 新增不做钱包侧预检；钱包服务判定 network route conflict 时转换为可读提示
            if (! str_contains($exception->getMessage(), 'network route conflict')) {
                throw $exception;
            }

            $this->throwCreateConflict($routes);
        }

        if ($createdRoutes === []) {
            throw new \Exception(trans('public.message.add_error'));
        }

        $this->logCreatedRoutes($createdRoutes);
        $operatorInfo = $this->getRouteOperatorInfo($createdRoutes);

        return array_map(fn (array $route): array => $this->decorateNetworkRoute($route, $operatorInfo), $createdRoutes);
    }

    public function destroy(int $id): void
    {
        $route = FluxWallet::getNetworkRoute($id);
        if ($route === []) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        if (! in_array($route['status'] ?? '', [FundingNetworkConfig::STATUS_DISABLED, FundingNetworkConfig::STATUS_NOT_ENABLED], true)) {
            throw new BusinessException(trans('funding_network_config.message.close_before_delete'));
        }

        $this->mutateNetworkRoutes(
            fn (): array => FluxWallet::archiveNetworkRoute($id, $this->resolveGrpcOperator())
        );
        $this->logOperate($id, $route, UserOperationLog::ACTION_TYPE_DELETE);
    }

    public function updateStatus(int $id, string $status): array
    {
        $route = FluxWallet::getNetworkRoute($id);
        if ($route === []) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        $currentStatus = (string) ($route['status'] ?? '');
        if ($currentStatus === $status) {
            throw new BusinessException(trans(
                $status === FundingNetworkConfig::STATUS_ENABLED
                    ? 'funding_network_config.message.already_enabled'
                    : 'funding_network_config.message.already_disabled'
            ));
        }

        $operator = $this->resolveGrpcOperator();
        $updatedRoutes = match ($status) {
            FundingNetworkConfig::STATUS_ENABLED => $this->enableRoute($route, $id, $operator),
            FundingNetworkConfig::STATUS_DISABLED => $this->mutateNetworkRoutes(
                fn (): array => FluxWallet::disableNetworkRoutes([$id], $operator)
            ),
            default => throw new BusinessException(trans('public.message.state_error')),
        };

        $updatedRoute = $this->resolveUpdatedRoute($updatedRoutes, $id);

        $this->logOperate($id, [
            'before' => $route,
            'after' => $updatedRoute,
        ], UserOperationLog::ACTION_TYPE_UPDATE);

        return $this->decorateNetworkRoute($updatedRoute, $this->getRouteOperatorInfo([$updatedRoute]));
    }

    public function switchProvider(int $id, string $providerId): array
    {
        $route = FluxWallet::getNetworkRoute($id);
        if ($route === []) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        if (($route['status'] ?? '') === FundingNetworkConfig::STATUS_ENABLED) {
            $this->ensureNoEnabledConflict($route, $id, 'funding_network_config.message.config_conflict');
        }

        $updatedRoutes = $this->mutateNetworkRoutes(
            fn (): array => FluxWallet::switchNetworkRoute($id, $providerId, $this->resolveGrpcOperator())
        );
        $updatedRoute = $this->resolveUpdatedRoute($updatedRoutes, $id, $providerId);
        $this->logSwitchOperate($route, $updatedRoutes !== [] ? $updatedRoutes : [$updatedRoute]);

        return $this->decorateNetworkRoute($updatedRoute, $this->getRouteOperatorInfo([$updatedRoute]));
    }

    private function logCreatedRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            $id = (int) ($route['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $this->logOperate($id, $route, UserOperationLog::ACTION_TYPE_ADD);
        }
    }

    private function resolveUpdatedRoute(array $updatedRoutes, int $id, ?string $providerId = null): array
    {
        if ($providerId !== null) {
            $updatedRoute = $this->findRouteByProviderId($updatedRoutes, $providerId);
            if ($updatedRoute !== []) {
                return $updatedRoute;
            }
        }

        $updatedRoute = $this->findRouteById($updatedRoutes, $id);
        if ($updatedRoute !== []) {
            return $updatedRoute;
        }

        $updatedRoute = FluxWallet::getNetworkRoute($id);
        if ($updatedRoute !== []) {
            return $updatedRoute;
        }

        throw new \Exception(trans('public.message.upd_error'));
    }

    private function logSwitchOperate(array $currentRoute, array $updatedRoutes): void
    {
        foreach ($updatedRoutes as $updatedRoute) {
            $id = (int) ($updatedRoute['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $this->logOperate($id, [
                'current_route_before' => $currentRoute,
                'after' => $updatedRoute,
            ], UserOperationLog::ACTION_TYPE_UPDATE);
        }
    }

    private function enableRoute(array $route, int $id, string $operator): array
    {
        $this->ensureNoEnabledConflict($route, $id, 'funding_network_config.message.enable_conflict');

        return $this->mutateNetworkRoutes(
            fn (): array => FluxWallet::enableNetworkRoutes([$id], $operator)
        );
    }

    /**
     * Walletd 状态前置校验兜底：客户端先读后写存在竞态窗口，命中 INVALID_STATE 时转友好提示。
     *
     * @template T
     * @param callable(): T $mutation
     * @return T
     */
    private function mutateNetworkRoutes(callable $mutation): mixed
    {
        try {
            return $mutation();
        } catch (FluxWalletException $e) {
            if ($e->getErrorCode() === FundingNetworkConfig::WALLET_ERROR_INVALID_STATE) {
                throw new BusinessException(trans('funding_network_config.message.invalid_state'));
            }

            throw $e;
        }
    }

    private function ensureNoEnabledConflict(array $route, int $excludeId, string $messageKey): void
    {
        $conflict = $this->findEnabledConflict($route, $excludeId);
        if ($conflict === []) {
            return;
        }

        throw new BusinessException(trans($messageKey, [
            'network' => $this->networkText((string) ($route['chain_id'] ?? '')),
            'psp' => (string) ($conflict['provider_id'] ?? ''),
        ]));
    }

    private function findEnabledConflict(array $route, int $excludeId): array
    {
        $pageAfterId = 0;

        do {
            $routes = FluxWallet::listNetworkRoutes(
                (string) ($route['config_type'] ?? ''),
                (string) ($route['token_symbol'] ?? ''),
                (string) ($route['chain_id'] ?? ''),
                '',
                FundingNetworkConfig::STATUS_ENABLED,
                100,
                $pageAfterId,
            );

            foreach ($routes as $enabledRoute) {
                if ((int) ($enabledRoute['id'] ?? 0) !== $excludeId) {
                    return $enabledRoute;
                }
            }

            $pageAfterId = $routes === [] ? 0 : (int) ($routes[array_key_last($routes)]['id'] ?? 0);
        } while (count($routes) === 100 && $pageAfterId > 0);

        return [];
    }

    /**
     * 同一批次内完全相同（类型、币种、网络、通道都一致）的配置直接拒绝。
     *
     * @param array<int, array{config_type:string,token_symbol:string,chain_id:string,provider_id:string}> $routes
     */
    private function ensureNoDuplicateConfigInBatch(array $routes): void
    {
        $seenKeys = [];
        foreach ($routes as $route) {
            $key = sprintf(
                '%s|%s|%s|%s',
                $route['config_type'] ?? '',
                $route['token_symbol'] ?? '',
                $route['chain_id'] ?? '',
                $route['provider_id'] ?? ''
            );

            if (isset($seenKeys[$key])) {
                $this->throwRouteConflict($route, (string) ($route['provider_id'] ?? ''));
            }

            $seenKeys[$key] = true;
        }
    }

    /**
     * 钱包服务判定 network route conflict 后的兜底解释：查询已存在配置以区分提示文案。
     *
     * @param array<int, array{config_type:string,token_symbol:string,chain_id:string,provider_id:string}> $routes
     */
    private function throwCreateConflict(array $routes): never
    {
        foreach ($routes as $route) {
            $existingProviderId = $this->findExistingRouteProviderId($route);
            if ($existingProviderId !== '') {
                $this->throwRouteConflict($route, $existingProviderId);
            }
        }

        // 冲突配置可能已被并发删除等场景查不到，统一给出通用冲突提示
        throw new BusinessException(trans('funding_network_config.message.route_conflict', [
            'network' => $this->routesNetworkText($routes),
        ]));
    }

    private function findExistingRouteProviderId(array $route): string
    {
        $existingRoutes = FluxWallet::listNetworkRoutes(
            (string) ($route['config_type'] ?? ''),
            (string) ($route['token_symbol'] ?? ''),
            (string) ($route['chain_id'] ?? ''),
            '',
            '',
            100,
            0,
        );

        foreach ($existingRoutes as $existingRoute) {
            $providerId = (string) ($existingRoute['provider_id'] ?? '');
            if ($providerId !== '') {
                return $providerId;
            }
        }

        return '';
    }

    private function throwRouteConflict(array $route, string $existingProviderId): never
    {
        if ((string) ($route['provider_id'] ?? '') === $existingProviderId) {
            $configType = (string) ($route['config_type'] ?? '');
            throw new BusinessException(trans('funding_network_config.message.duplicate_config', [
                'config_type' => FundingNetworkConfig::configTypeTextMap()[$configType] ?? $configType,
                'currency' => (string) ($route['token_symbol'] ?? ''),
                'network' => $this->networkText((string) ($route['chain_id'] ?? '')),
                'channel' => $existingProviderId,
            ]));
        }

        throw new BusinessException(trans('funding_network_config.message.route_conflict', [
            'network' => $this->networkText((string) ($route['chain_id'] ?? '')),
        ]));
    }

    /**
     * 批量配置涉及的网络展示文案，多个网络时去重拼接。
     *
     * @param array<int, array{config_type:string,token_symbol:string,chain_id:string,provider_id:string}> $routes
     */
    private function routesNetworkText(array $routes): string
    {
        $networks = [];
        foreach ($routes as $route) {
            $text = $this->networkText((string) ($route['chain_id'] ?? ''));
            if ($text !== '' && ! in_array($text, $networks, true)) {
                $networks[] = $text;
            }
        }

        $locale = (string) app()->getLocale();
        $separator = str_starts_with($locale, 'zh') || str_starts_with($locale, 'ja') ? '、' : ', ';

        return implode($separator, $networks);
    }

    private function findRouteById(array $routes, int $id): array
    {
        foreach ($routes as $route) {
            if ((int) ($route['id'] ?? 0) === $id) {
                return $route;
            }
        }

        return [];
    }

    private function findRouteByProviderId(array $routes, string $providerId): array
    {
        foreach ($routes as $route) {
            if ((string) ($route['provider_id'] ?? '') === $providerId) {
                return $route;
            }
        }

        return [];
    }

    private function decorateNetworkRoute(array $item, array $operatorInfo): array
    {
        $id = (int) ($item['id'] ?? 0);
        $log = $operatorInfo[$id] ?? [];
        $status = (string) ($item['status'] ?? '');
        $configType = (string) ($item['config_type'] ?? '');
        $network = (string) ($item['chain_id'] ?? '');
        $channel = (string) ($item['provider_id'] ?? '');

        return [
            'id' => $id,
            'currency' => (string) ($item['token_symbol'] ?? ''),
            'config_type' => $configType,
            'config_type_text' => FundingNetworkConfig::configTypeTextMap()[$configType] ?? $configType,
            'network' => $network,
            'network_text' => $this->networkText($network),
            'channel' => $channel,
            'channel_text' => $channel,
            'status' => $status,
            'status_text' => FundingNetworkConfig::statusTextMap()[$status] ?? $status,
            'updated_at' => (string) ($log['created_at'] ?? ''),
            'operator_name' => (string) ($log['operator_name'] ?? ''),
            'can_delete' => in_array($status, [FundingNetworkConfig::STATUS_NOT_ENABLED, FundingNetworkConfig::STATUS_DISABLED], true),
            'can_enable' => $status !== FundingNetworkConfig::STATUS_ENABLED,
            'can_disable' => $status === FundingNetworkConfig::STATUS_ENABLED,
            'can_modify' => true,
        ];
    }

    private function getRouteOperatorInfo(array $routes): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (array $route): int => (int) ($route['id'] ?? 0),
            $routes
        )));

        if ($ids === []) {
            return [];
        }

        $operateInfos = $this->userOperationLogRepo->getBatchOperateInfo(
            UserOperationLog::TARGET_TYPE_FUNDING_NETWORK_CONFIG,
            $ids,
            0
        );

        $adminIds = array_values(array_unique(array_filter(array_map(
            static fn (array $info): int => (int) ($info['operator_id'] ?? 0),
            $operateInfos
        ))));
        $adminNames = $this->adminUserRepo->getColumnByIds($adminIds, 'login_name');
        $result = [];

        foreach ($operateInfos as $targetId => $info) {
            $operatorId = (int) ($info['operator_id'] ?? 0);
            $result[(int) $targetId] = [
                'operator_name' => (string) ($adminNames[$operatorId] ?? ''),
                'created_at' => (string) ($info['created_at'] ?? ''),
            ];
        }

        return $result;
    }

    private function networkText(string $chainId): string
    {
        if ($chainId === '') {
            return '';
        }

        return $this->networkTextMap()[$chainId] ?? $chainId;
    }

    public function networkDisplayText(string $chainId, string $tokenSymbol): string
    {
        if ($chainId === '') {
            return '';
        }

        $asset = $this->findSupportedAsset($chainId, $tokenSymbol);
        $networkName = $asset !== [] ? $this->assetNetworkText($asset) : $this->networkText($chainId);
        $tokenStandard = $asset !== [] ? $this->assetTokenStandard($asset) : '';

        return $tokenStandard === '' ? $networkName : $networkName . '(' . $tokenStandard . ')';
    }

    /**
     * @return array<string, mixed>
     */
    private function findSupportedAsset(string $chainId, string $tokenSymbol): array
    {
        foreach ($this->listSupportedAssets() as $asset) {
            if (
                strcasecmp((string) ($asset['chain_id'] ?? ''), $chainId) === 0
                && strcasecmp((string) ($asset['token_symbol'] ?? ''), $tokenSymbol) === 0
            ) {
                return $asset;
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function networkTextMap(): array
    {
        if ($this->networkTextMap !== null) {
            return $this->networkTextMap;
        }

        $this->networkTextMap = [];
        foreach ($this->listSupportedAssets() as $asset) {
            $chainId = (string) ($asset['chain_id'] ?? '');
            if ($chainId === '' || isset($this->networkTextMap[$chainId])) {
                continue;
            }

            $this->networkTextMap[$chainId] = $this->assetNetworkText($asset);
        }

        return $this->networkTextMap;
    }

    /**
     * @param array<int, array<string, mixed>> $assets
     * @return array<int, array{code:string,name:string,settings:array{relations:array<int, array{currency:string,network:string,network_text:string,network_icon_url:string,channels:array<int, array{code:string,name:string,enabled:bool}>}>}}>
     */
    private function configTypeOptions(array $assets): array
    {
        return array_map(function (array $option) use ($assets): array {
            $configTypeAssets = $this->assetsForConfigType($assets, (string) $option['code']);

            return $option + [
                'settings' => [
                    'relations' => $this->assetRelations($configTypeAssets),
                ],
            ];
        }, FundingNetworkConfig::configTypeOptions());
    }

    /**
     * @param array<int, array<string, mixed>> $assets
     * @return array<int, array<string, mixed>>
     */
    private function assetsForConfigType(array $assets, string $configType): array
    {
        $supportedKey = match ($configType) {
            FundingNetworkConfig::CONFIG_TYPE_DEPOSIT => 'deposit_supported',
            FundingNetworkConfig::CONFIG_TYPE_WITHDRAWAL => 'withdraw_supported',
            default => null,
        };

        if ($supportedKey === null) {
            return [];
        }

        return array_values(array_filter(
            $assets,
            static fn (array $asset): bool => (bool) ($asset[$supportedKey] ?? false)
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $assets
     * @return array<int, array{currency:string,network:string,network_text:string,network_icon_url:string,channels:array<int, array{code:string,name:string,enabled:bool}>}>
     */
    private function assetRelations(array $assets): array
    {
        $relations = [];
        foreach ($assets as $asset) {
            $currency = (string) ($asset['token_symbol'] ?? '');
            $network = (string) ($asset['chain_id'] ?? '');
            $channel = (string) ($asset['provider_id'] ?? '');
            if ($currency === '' || $network === '' || $channel === '') {
                continue;
            }

            $key = $currency . '|' . $network;
            if (! isset($relations[$key])) {
                $relations[$key] = [
                    'currency' => $currency,
                    'network' => $network,
                    'network_text' => $this->assetNetworkText($asset),
                    'network_icon_url' => (string) ($asset['icon_url'] ?? ''),
                    'channels' => [],
                ];
            }

            if (isset($relations[$key]['channels'][$channel])) {
                continue;
            }

            $relations[$key]['channels'][$channel] = [
                'code' => $channel,
                'name' => $channel,
                'enabled' => (bool) ($asset['enabled'] ?? false),
            ];
        }

        return array_values(array_map(static function (array $relation): array {
            $relation['channels'] = array_values($relation['channels']);

            return $relation;
        }, $relations));
    }

    /**
     * @param array<int, array<string, mixed>> $assets
     * @return array<int, array{code:string,name:string}>
     */
    private function networkOptions(array $assets): array
    {
        $options = [];
        foreach ($assets as $asset) {
            $chainId = (string) ($asset['chain_id'] ?? '');
            if ($chainId === '' || isset($options[$chainId])) {
                continue;
            }

            $options[$chainId] = [
                'code' => $chainId,
                'name' => $this->assetNetworkText($asset),
            ];
        }

        return array_values($options);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array{code:string,name:string}>
     */
    private function simpleOptions(array $items, string $codeKey): array
    {
        $options = [];
        foreach ($items as $item) {
            $code = (string) ($item[$codeKey] ?? '');
            if ($code === '' || isset($options[$code])) {
                continue;
            }

            $options[$code] = [
                'code' => $code,
                'name' => $code,
            ];
        }

        return array_values($options);
    }

    private function assetNetworkText(array $asset): string
    {
        $chainId = (string) ($asset['chain_id'] ?? '');
        $name = $this->resolveLocalizedName($asset['chain_names'] ?? null);
        if ($name === '') {
            $name = (string) ($asset['chain_name'] ?? '');
        }

        return $name !== '' ? $name : $chainId;
    }

    private function assetTokenStandard(array $asset): string
    {
        return strtoupper(trim((string) ($asset['token_standard'] ?? '')));
    }

    /**
     * @param array<string, string>|string|null $value
     */
    private function resolveLocalizedName(array|string|null $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (! is_array($decoded)) {
                return $value;
            }

            $value = $decoded;
        }

        if (! is_array($value)) {
            return '';
        }

        $locale = (string) (app()->getLocale() ?: config('app.locale', 'en_US'));
        $fallback = (string) config('app.fallback_locale', 'en_US');

        foreach (array_unique([$locale, $fallback, 'en_US', 'zh_CN']) as $lang) {
            $name = trim((string) ($value[$lang] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return (string) (reset($value) ?: '');
    }

    private function resolveGrpcOperator(): string
    {
        $user = Auth::user();
        $id = is_array($user)
            ? (int) ($user['id'] ?? 0)
            : (is_object($user) ? (int) ($user->id ?? 0) : 0);
        $name = is_array($user)
            ? (string) ($user['login_name'] ?? '')
            : (is_object($user) ? (string) ($user->login_name ?? '') : '');

        return $name . ':' . $id;
    }

    private function logOperate(int $id, array $content, int $actionType): void
    {
        $this->userOperationLogRepo->addRecord(
            $id,
            UserOperationLog::TARGET_TYPE_FUNDING_NETWORK_CONFIG,
            json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $actionType
        );
    }

}
