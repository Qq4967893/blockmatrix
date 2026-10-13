<?php

namespace App\Utils;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class BaseRedis
{

    /**
     * redis cache连接
     *
     * @return Connection
     */
    public static function getBlockmatrixRedis(): Connection
    {
        return Redis::connection();
    }

    /**
     * api cache redis连接
     *
     * @return Connection
     */
    public static function getApiCacheRedis(): Connection
    {
        return Redis::connection('api_cache');
    }
}
