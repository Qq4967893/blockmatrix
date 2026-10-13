<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;

class FundingOperationRecord extends Model
{
    public const TABLE = 'funding_operation_record';

    protected $connection = DB_BM;

    protected $table = self::TABLE;

    public $timestamps = false;

    public const STATUS_PENDING = 0;
    public const STATUS_PROCESSING = 1;
    public const STATUS_SUCCESS = 2;
    public const STATUS_FAIL = 3;
    public const STATUS_INVALID = 4;

    /**
     * 所有状态选项。
     *
     * @return int[]
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_SUCCESS,
            self::STATUS_FAIL,
            self::STATUS_INVALID,
        ];
    }

    public static function statusTextMap(): array
    {
        return [
            self::STATUS_PENDING => '待执行',
            self::STATUS_PROCESSING => '执行中',
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAIL => '执行失败',
            self::STATUS_INVALID => '导入无效',
        ];
    }
}
