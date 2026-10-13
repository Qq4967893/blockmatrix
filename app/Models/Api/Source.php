<?php

namespace App\Models\Api;

use App\Casts\LocalizedImageCast;
use Scaffold\Framework\Models\Api\TsSource;

class Source extends TsSource
{
    /** 交易品种上架状态 */
    public const STATUS_LISTED = 1;

    /** 交易品种下架状态 */
    public const STATUS_DELISTED = 0;

    protected $casts = [
        'name'   => 'array',
        'config' => 'array',
        'icon'   => LocalizedImageCast::class,
    ];
}
