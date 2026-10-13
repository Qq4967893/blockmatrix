<?php

namespace App\Models\Api;

use App\Casts\LocalizedImageCast;
use Scaffold\Framework\Models\Model;

class PositionRecord extends Model
{
    public const TARGET_TYPE_INTERNAL = 1;

    public const TARGET_TYPE_EXTERNAL = 2;

    public const STATUS_DISABLED = 0;

    public const STATUS_ENABLED = 1;

    public const STATUS_PENDING = 2;

    public const STATUSES = [
        self::STATUS_DISABLED,
        self::STATUS_ENABLED,
        self::STATUS_PENDING,
    ];

    public const SCHEDULED_STATUSES = [
        self::STATUS_ENABLED,
        self::STATUS_PENDING,
    ];

    /**
     * @return array<int, array{code:int,label:string}>
     */
    public static function statusOptions(): array
    {
        return array_map(
            static fn (int $status): array => [
                'code' => $status,
                'label' => trans('ad.status.' . $status),
            ],
            self::STATUSES,
        );
    }

    protected $connection = DB_API;
    protected $table = 'position_record';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'image' => LocalizedImageCast::class,
    ];
}
