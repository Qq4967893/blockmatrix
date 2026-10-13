<?php

namespace App\Services\Api;

use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\CurrencyRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;

class CurrencyService
{
    public function __construct(
        private CurrencyRepository         $repo,
        private UserOperationLogRepository $userOperationLogRepo,
        private AdminUserRepository        $adminUserRepo
    ) {
    }

    /**
     * @return array{data: array<int, array{id:int,currency:string,rate:float,spread_deposit:float,spread_withdraw:float,created_at:?string,updated_at:?string,created_by:string,created_time:?string}>, total_count:int}
     */
    public function list(array $filters, int $limit, int $offset): array
    {
        $filters['currency'] = $this->normalizeCurrency($filters['currency'] ?? null);
        $list = $this->repo->paginate($filters, $limit, $offset);
        $rows = $this->attachOperateInfo($list['data']);

        return [
            'data'        => $rows,
            'total_count' => $list['total_count'],
        ];
    }

    public function create(array $payload): int
    {
        $payload['currency'] = $this->normalizeCurrency($payload['currency'] ?? '');
        $this->assertCurrencyUnique($payload['currency']);

        $id = $this->repo->create([
            'currency'        => $payload['currency'],
            'spread_deposit'  => $payload['spread_deposit'],
            'spread_withdraw' => $payload['spread_withdraw'],
        ]);

        $this->logOperate((int) $id, $payload, UserOperationLog::ACTION_TYPE_ADD);

        return (int) $id;
    }

    public function update(array $payload): bool
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new \Exception(trans('public.message.data_error'));
        }

        $existing = $this->repo->find($id);
        if (! $existing) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        $updated = $this->repo->update([
            'spread_deposit'  => $payload['spread_deposit'],
            'spread_withdraw' => $payload['spread_withdraw'],
        ], $id);

        if ($updated) {
            $this->logOperate($id, $payload, UserOperationLog::ACTION_TYPE_UPDATE);
        }

        return (bool) $updated;
    }

    private function normalizeCurrency(?string $currency): ?string
    {
        if ($currency === null || $currency === '') {
            return null;
        }

        return strtoupper($currency);
    }

    private function assertCurrencyUnique(string $currency, int $exceptId = 0): void
    {
        $row = $this->repo->findByCurrency($currency);
        if ($row && (int) ($row['id'] ?? 0) !== $exceptId) {
            throw new \Exception(trans('public.message.currency_exists'));
        }
    }

    /**
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    private function attachOperateInfo(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $ids = array_column($rows, 'id');
        $operateInfos = $this->userOperationLogRepo->getBatchOperateInfo(
            UserOperationLog::TARGET_TYPE_CURRENCY_SPREAD,
            $ids,
            [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]
        );

        $adminIds  = array_values(array_unique(array_filter(array_column($operateInfos, 'operator_id'))));
        $adminMaps = $this->adminUserRepo->getColumnByIds($adminIds, 'login_name');

        foreach ($rows as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $info = $operateInfos[$id] ?? null;
            $row['created_by']   = $info ? ($adminMaps[$info['operator_id']] ?? '') : '';
            $row['created_time'] = $info['created_at'] ?? ($row['created_at'] ?? null);
        }
        unset($row);

        return $rows;
    }

    private function logOperate(int $id, array $payload, int $status): void
    {
        $content = json_encode([
            'currency'        => $payload['currency'] ?? ($existing['currency'] ?? ''),
            'spread_deposit'  => $payload['spread_deposit'] ?? 0,
            'spread_withdraw' => $payload['spread_withdraw'] ?? 0,
        ], JSON_UNESCAPED_UNICODE);

        $this->userOperationLogRepo->addRecord(
            $id,
            UserOperationLog::TARGET_TYPE_CURRENCY_SPREAD,
            $content,
            $status
        );
    }
}
