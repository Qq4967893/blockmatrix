<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;

class FundingOperation extends Model
{
    public const TABLE = 'funding_operation';

    protected $connection = DB_BM;

    protected $table = self::TABLE;

    public $timestamps = false;

    public const STATUS_PENDING = 0;
    public const STATUS_PROCESSING = 1;
    public const STATUS_PARTIAL_SUCCESS = 2;
    public const STATUS_SUCCESS = 3;
    public const STATUS_FAIL = 4;
    public const STATUS_CANCELLED = 5;

    /**
     * 列表筛选可用的状态选项（不含 STATUS_PENDING）。
     *
     * @return int[]
     */
    public static function listFilterStatusOptions(): array
    {
        return [
            self::STATUS_PROCESSING,
            self::STATUS_PARTIAL_SUCCESS,
            self::STATUS_SUCCESS,
            self::STATUS_FAIL,
            self::STATUS_CANCELLED,
        ];
    }

    public static function statusTextMap(): array
    {
        return [
            self::STATUS_PENDING => '待执行',
            self::STATUS_PROCESSING => '执行中',
            self::STATUS_PARTIAL_SUCCESS => '部分成功',
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAIL => '失败',
            self::STATUS_CANCELLED => '已取消',
        ];
    }
}
