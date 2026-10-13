<?php

namespace App\Repositories\Api;

use App\Models\Api\FundingOperation;
use Scaffold\Framework\Repositories\Repository;

class FundingOperationRepository extends Repository
{
    public function __construct(FundingOperation $model)
    {
        parent::__construct($model);
    }
}
