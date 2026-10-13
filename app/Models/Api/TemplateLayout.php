<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Model;

class TemplateLayout extends Model
{
    protected $connection = DB_API;
    protected $table      = 'template_layout';
    public $timestamps = false;
    protected $guarded    = [];

    public const STATUS_UNPUBLISHED = 0;
    public const STATUS_PUBLISHED = 1;

    public const CONTENT_PLACEHOLDER = '{{content}}';
}
