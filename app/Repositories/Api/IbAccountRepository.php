<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Models\Api\IbAccount;
use Scaffold\Framework\Repositories\Repository;

class IbAccountRepository extends Repository
{
    public function __construct(IbAccount $model)
    {
        parent::__construct($model);
    }

    /**
     * 根据用户ID获取当前 IB 账户信息
     *
     * 查询字段:
     * - ia.*: ib_account 原始字段
     * - level_name: 当前代理等级名称
     * - parent_user_code: 上级代理的 user_code
     *
     * @param int|string $user_code 用户ID
     *
     * @return object|null
     */
    public function getInfo($user_code)
    {
        return $this->getQuery()->from('ib_account as ia')
            ->leftJoin('ib_level as il', 'ia.level_id', '=', 'il.id')
            ->leftJoin('ib_account as parent_ia', 'parent_ia.id', '=', 'ia.parent_id')
            ->where('ia.user_code', $user_code)
            ->select('ia.*', 'il.name as level_name', 'parent_ia.user_code as parent_user_code')
            ->first();
    }

    public function findByUserCode(int $userCode): ?IbAccount
    {
        return $this->getQuery()->where('user_code', $userCode)->first();
    }

    // 获取指定代理层级路径下的所有代理链
    public function getIbPaths($ibPath)
    {
        return $this->getQuery()->where('ib_path', 'like', '%'.$ibPath.'%')->get()->toArray();
    }

    public function findById(int $id): ?IbAccount
    {
        return $this->getQuery()->find($id);
    }

    public function findByIdForUpdate(int $id): ?IbAccount
    {
        return $this->getQuery()->lockForUpdate()->find($id);
    }

    public function existsByLevelId(int $levelId): bool
    {
        return $this->getQuery()
            ->where('level_id', $levelId)
            ->exists();
    }

    public function createAccount(array $payload): IbAccount
    {
        /** @var IbAccount $account */
        $account = $this->create($payload);

        return $account;
    }

    public function updateAccount(int $id, array $payload, ?IbAccount $account = null): ?IbAccount
    {
        $account ??= $this->findById($id);
        if (! $account) {
            return null;
        }

        $account->fill($payload);
        $account->save();

        return $account->fresh();
    }
}
