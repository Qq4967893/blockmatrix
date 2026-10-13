<?php

namespace App\Repositories\Api;

use App\Models\Api\MessageTransaction;
use Scaffold\Framework\Repositories\Repository;

class MessageTransactionRepository extends Repository
{
    public function __construct(MessageTransaction $model)
    {
        parent::__construct($model);
    }
}
