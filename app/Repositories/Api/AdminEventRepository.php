<?php

namespace App\Repositories\Api;

use App\Models\Api\AdminEvent;
use Scaffold\Framework\Repositories\Repository;

class AdminEventRepository extends Repository
{
    public function __construct(AdminEvent $model)
    {
        parent::__construct($model);
    }

    /**
     * 使用 Eloquent create 触发 ProduceModelEvent。
     */
    public function create(array $data)
    {
        return parent::create($data);
    }

    public function existsByEventKeyAndUserCode(string $eventKey, int $userCode): bool
    {
        return $this->getQuery()
            ->where('event_key', $eventKey)
            ->where('event_params->user_code', $userCode)
            ->exists();
    }
}
