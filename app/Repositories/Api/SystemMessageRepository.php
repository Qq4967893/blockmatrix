<?php

namespace App\Repositories\Api;


use Scaffold\Framework\Repositories\Repository;
use App\Models\Api\SystemMessage;

class SystemMessageRepository extends Repository
{
    public function __construct(SystemMessage $model)
    {
        parent::__construct($model);
    }

    /**
     * 消息列表
     *
     * @param int $limit  每页条数
     * @param int $offset 偏移量
     *
     * @return array
     */
    public function getList($limit, $offset)
    {
        $query = $this->getQuery();

        $count    = $query->count();
        $messages = $query->orderByDesc('id')->limit($limit)->offset($offset)->get()->toArray();

        return [$messages, $count];
    }

    /**
     * 添加消息
     *
     * @param array $data 数据
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function sendMsg($data)
    {
        return $this->getQuery()->create($data);
    }
}
