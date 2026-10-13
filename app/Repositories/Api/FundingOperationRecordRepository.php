<?php

namespace App\Repositories\Api;

use App\Models\Api\FundingOperation;
use App\Models\Api\FundingOperationRecord;
use Scaffold\Framework\Repositories\Repository;

class FundingOperationRecordRepository extends Repository
{
    public function __construct(FundingOperationRecord $model)
    {
        parent::__construct($model);
    }

    public function getAll($fundingLedgerIds)
    {
        return $this->getQuery()->from(FundingOperationRecord::TABLE . ' as fb')
            ->leftJoin(FundingOperation::TABLE . ' as f', 'f.id', '=', 'fb.job_id')
            ->leftJoin('admin_user as a', 'a.id', '=', 'f.created_by')
            ->whereIn('fb.funding_ledger_id', $fundingLedgerIds)
            ->where('fb.status', FundingOperationRecord::STATUS_SUCCESS)
            ->selectRaw('fb.funding_ledger_id, fb.amount, fb.reason, a.login_name')
            ->get()->toArray();
    }
}
