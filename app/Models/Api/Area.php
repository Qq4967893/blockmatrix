<?php

namespace App\Models\Api;

use Illuminate\Support\Facades\App;
use Scaffold\Framework\Models\Model;

class Area extends Model
{
    protected $connection = DB_API;
    protected $table      = 'area';
    public $timestamps = false;
    protected $guarded    = [];

    // 添加casts将name字段自动转换为数组
    protected $casts = [
        'name' => 'array'
    ];

    /**
     * 获取当前语言对应的名称
     *
     * @return string
     */
    public function getNameAttribute($value)
    {
        if (is_array($value)) {
            $currentLocale = App::getLocale();
            $fallback      = config('app.fallback_locale', 'en_US');

            if (isset($value[$currentLocale])) {
                return $value[$currentLocale];
            }

            if (isset($value[$fallback])) {
                return $value[$fallback];
            }

            return !empty($value) ? reset($value) : '';
        }

        return $value;
    }
}
