<?php

namespace App\Models\Api;

use Scaffold\Framework\Enums\TypeCommunityActivity;
use Scaffold\Framework\Enums\TypeCommunityActivityCity;
use Scaffold\Framework\Models\Api\CommunityActivity as BaseCommunityActivity;

class CommunityActivity extends BaseCommunityActivity
{
    public const int PUBLISH_TYPE_SCHEDULED = 0; // 发布类型:定时（纯逻辑推导，不落库）
    public const int PUBLISH_TYPE_MANUAL    = 1; // 发布类型:人工（纯逻辑推导，不落库）

    protected $casts = [
        'id' => 'integer',
        'type' => TypeCommunityActivity::class,
        'city' => TypeCommunityActivityCity::class,
        'status' => 'integer',
        'audience' => 'array',
        'name' => 'array',
        'summary' => 'array',
        'cover_image' => 'array',
        'bg_image' => 'array',
        'introduction' => 'array',
        'content' => 'array',
        'start_at' => 'datetime:Y-m-d H:i:s',
        'end_at' => 'datetime:Y-m-d H:i:s',
        'apply_start_at' => 'datetime:Y-m-d H:i:s',
        'apply_end_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
