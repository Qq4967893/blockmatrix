<?php

namespace App\Repositories\Api;

use App\Models\Api\IbStatSnapshot;
use Scaffold\Framework\Repositories\Repository;

class IbStatSnapshotRepository extends Repository
{
    public function __construct(IbStatSnapshot $model)
    {
        parent::__construct($model);
    }

    public function getInfo($userCode)
    {
        return $this->getQuery()->where('user_code', $userCode)->first();
    }
}
