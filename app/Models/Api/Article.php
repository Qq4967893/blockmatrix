<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Api\Article as BaseArticle;

class Article extends BaseArticle
{
    public const int STATUS_DRAFT = 0;

    public const int STATUS_PUBLISHED = 1;

    public const int STATUS_OFFLINE = 2;

    public const int STATUS_DELETED = 3;

    public const array STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_OFFLINE,
        self::STATUS_DELETED,
    ];

    public const array LIST_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_OFFLINE,
    ];

    public const array STATUS_UPDATE_OPTIONS = [
        self::STATUS_PUBLISHED,
        self::STATUS_OFFLINE,
        self::STATUS_DELETED,
    ];

    public const array PUBLISHABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_OFFLINE,
    ];

    protected $casts = [
        'category' => 'array',
        'title' => 'array',
        'content' => 'array',
    ];

    /**
     * @return array<int, array{code:int,label:string}>
     */
    public static function statusOptions(): array
    {
        return array_map(
            static fn (int $status): array => [
                'code' => $status,
                'label' => trans('article.status.'.$status),
            ],
            self::STATUSES,
        );
    }
}
