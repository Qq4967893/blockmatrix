<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Scaffold\Framework\Models\Api\CommunityActivityApplication as BaseCommunityActivityApplication;

class CommunityActivityApplication extends BaseCommunityActivityApplication
{
    protected $casts = [
        'id' => 'integer',
        'community_activity_id' => 'integer',
        'user_code' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(CommunityActivity::class, 'community_activity_id');
    }
}
