<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Model;

class AppRelease extends Model
{
    private const PLATFORM_OPTIONS = [
        ['code' => 'miniapp', 'label' => 'miniapp'],
        ['code' => 'testflight', 'label' => 'TestFlight'],
        ['code' => 'ios', 'label' => 'iOS'],
        ['code' => 'android', 'label' => 'Android'],
        ['code' => 'webtrade', 'label' => 'WebTrade'],
    ];

    protected $connection = DB_API;
    protected $table = 'app_release';
    protected $guarded = [];

    protected $casts = [
        'id' => 'integer',
        'build_number' => 'integer',
        'is_forced_update' => 'integer',
        'title' => 'array',
        'description' => 'array',
        'release_time' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * @return array<int, string>
     */
    public static function supportedPlatforms(): array
    {
        return array_column(self::PLATFORM_OPTIONS, 'code');
    }

    /**
     * @return array<int, array{code:string,label:string}>
     */
    public static function platformOptions(): array
    {
        return self::PLATFORM_OPTIONS;
    }
}
