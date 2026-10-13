<?php

namespace App\Repositories\Et6;

use App\Models\TradingSystem\Copy;
use Scaffold\Framework\Models\TradingSystem\CopyHistory;
use Scaffold\Framework\Repositories\Repository;

class CopyHistoryRepository extends Repository
{
    public function __construct(CopyHistory $model)
    {
        parent::__construct($model);
    }

    /**
     * 分页获取历史跟单记录
     *
     * @param array $login_masters 主账号列表
     * @param array $login_followers 跟单账号列表
     * @param int|string|null $status 跟单状态
     * @param int $offset 偏移量
     * @param int $limit 每页条数
     * @param string $sort 排序字段
     * @param string $order 排序方向
     *
     * @return array
     */
    public function getList($login_masters, $login_followers, $status, $offset, $limit, $sort = 'id', $order = 'desc')
    {
        $query = $this->getQuery();
        if (count($login_masters) > 0) {
            $query->whereIn('login_master', $login_masters);
        }

        if (count($login_followers) > 0) {
            $query->whereIn('login_follower', $login_followers);
        }

        if (in_array($status, [Copy::STATUS_FOLLOWING, Copy::STATUS_ENDED])) {
            $query->where('status', $status);
        }

        $count = $query->count();
        $data = $query->offset($offset)->limit($limit)->orderBy($sort, $order)->get()->toArray();
        return [$data, $count];
    }

    /**
     * 获取全部历史跟单记录
     *
     * @param array $login_masters 主账号列表
     * @param array $login_followers 跟单账号列表
     * @param int|string|null $status 跟单状态
     *
     * @return array
     */
    public function getAll($login_masters, $login_followers, $status)
    {
        $query = $this->getQuery();
        if (count($login_masters) > 0) {
            $query->whereIn('login_master', $login_masters);
        }

        if (count($login_followers) > 0) {
            $query->whereIn('login_follower', $login_followers);
        }

        if ($status == Copy::STATUS_FOLLOWING) {
            $query->where('status', $status);
        }

        return $query->get()->toArray();
    }
}
