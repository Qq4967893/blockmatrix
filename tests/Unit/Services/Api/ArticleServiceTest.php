<?php

namespace Tests\Unit\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Api\Article;
use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\ArticleRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Services\Api\ArticleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Scaffold\Framework\Utils\S3;
use Tests\TestCase;

class ArticleServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_create_normalizes_multilingual_payload_and_records_operation(): void
    {
        $repo = Mockery::mock(ArticleRepository::class);
        $repo->shouldReceive('createArticle')
            ->once()
            ->withArgs(function (array $data): bool {
                return $data['category'] === [10, 1]
                    && $data['is_top'] === 1
                    && $data['title'] === ['zh_CN' => '公告', 'en_US' => 'Announcement']
                    && $data['content'] === [
                        'zh_CN' => '<p><img src="http://cdn.example.com/article/own-zh.png"><img src="https://images.example.net/external-zh.png"></p>',
                        'en_US' => '<p><img src="https://cdn.example.com/article/own-en.png"><img src="https://images.example.net/external-en.png"></p>',
                    ]
                    && $data['thumbnail'] === 'article/cover.png'
                    && $data['status'] === Article::STATUS_DRAFT
                    && $data['published_by'] === 0;
            })
            ->andReturn($this->makeArticle());

        $operationLogRepo = Mockery::mock(UserOperationLogRepository::class);
        $operationLogRepo->shouldReceive('addRecord')->once();

        $service = $this->makeService($repo, operationLogRepo: $operationLogRepo);
        $result = $service->create($this->payload());

        $this->assertTrue($result);
    }

    public function test_create_omits_thumbnail_when_missing_empty_or_null(): void
    {
        foreach (['missing' => null, 'null' => null, 'empty' => '', 'blank' => '   '] as $case => $thumbnail) {
            $payload = $this->payload();
            if ($case === 'missing') {
                unset($payload['thumbnail']);
            } else {
                $payload['thumbnail'] = $thumbnail;
            }

            $repo = Mockery::mock(ArticleRepository::class);
            $repo->shouldReceive('createArticle')
                ->once()
                ->withArgs(fn (array $data): bool => ! array_key_exists('thumbnail', $data))
                ->andReturn($this->makeArticle());

            $operationLogRepo = Mockery::mock(UserOperationLogRepository::class);
            $operationLogRepo->shouldReceive('addRecord')->once();

            $service = $this->makeService($repo, operationLogRepo: $operationLogRepo);
            $service->create($payload);
        }
    }

    public function test_update_keeps_existing_thumbnail_when_missing_empty_or_null(): void
    {
        foreach (['missing' => null, 'null' => null, 'empty' => '', 'blank' => '   '] as $case => $thumbnail) {
            $payload = $this->payload();
            if ($case === 'missing') {
                unset($payload['thumbnail']);
            } else {
                $payload['thumbnail'] = $thumbnail;
            }

            $repo = Mockery::mock(ArticleRepository::class);
            $this->mockApiTransaction();
            $repo->shouldReceive('findByIdForUpdate')->once()->andReturn($this->makeArticle());
            $repo->shouldReceive('updateById')
                ->once()
                ->withArgs(fn (int $id, array $data): bool => ! array_key_exists('thumbnail', $data))
                ->andReturn($this->makeArticle());

            $operationLogRepo = Mockery::mock(UserOperationLogRepository::class);
            $operationLogRepo->shouldReceive('addRecord')->once();

            $service = $this->makeService($repo, operationLogRepo: $operationLogRepo);
            $this->assertTrue($service->update(9, $payload));
        }
    }

    public function test_publish_updates_publish_fields_and_operation_log(): void
    {
        $article = $this->makeArticle(['status' => Article::STATUS_OFFLINE]);
        $repo = Mockery::mock(ArticleRepository::class);
        $this->mockApiTransaction();
        $repo->shouldReceive('findByIdForUpdate')->once()->with(9)->andReturn($article);
        $repo->shouldReceive('updateById')
            ->once()
            ->withArgs(function (int $id, array $data, Article $existing): bool {
                return $id === 9
                    && $data['status'] === Article::STATUS_PUBLISHED
                    && $data['published_at'] instanceof Carbon
                    && $data['published_by'] === 0;
            })
            ->andReturnUsing(function (int $id, array $data): Article {
                return $this->makeArticle($data);
            });

        $adminRepo = Mockery::mock(AdminUserRepository::class);
        $adminRepo->shouldReceive('getColumnByIds')->with([0], 'login_name')->andReturn([]);
        $operationLogRepo = Mockery::mock(UserOperationLogRepository::class);
        $operationLogRepo->shouldReceive('addRecord')->once();

        $service = $this->makeService($repo, $adminRepo, $operationLogRepo);
        $result = $service->updateStatus(9, Article::STATUS_PUBLISHED);

        $this->assertSame(Article::STATUS_PUBLISHED, $result['status']);
    }

    public function test_offline_rejects_draft_article(): void
    {
        $repo = Mockery::mock(ArticleRepository::class);
        $this->mockApiTransaction();
        $repo->shouldReceive('findByIdForUpdate')->once()->andReturn($this->makeArticle());

        $service = $this->makeService($repo);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(trans('article.message.only_published_can_offline'));

        $service->updateStatus(9, Article::STATUS_OFFLINE);
    }

    public function test_update_status_deletes_article_and_records_delete_action(): void
    {
        $repo = Mockery::mock(ArticleRepository::class);
        $this->mockApiTransaction();
        $repo->shouldReceive('findByIdForUpdate')
            ->once()
            ->andReturn($this->makeArticle(['status' => Article::STATUS_PUBLISHED]));
        $repo->shouldReceive('updateById')
            ->once()
            ->with(9, ['status' => Article::STATUS_DELETED], Mockery::on(fn ($article): bool => $article instanceof Article))
            ->andReturn($this->makeArticle(['status' => Article::STATUS_DELETED]));

        $adminRepo = Mockery::mock(AdminUserRepository::class);
        $adminRepo->shouldReceive('getColumnByIds')->with([0], 'login_name')->andReturn([]);
        $operationLogRepo = Mockery::mock(UserOperationLogRepository::class);
        $operationLogRepo->shouldReceive('addRecord')
            ->once()
            ->withArgs(function (int $id, int $targetType, string $content, int $actionType): bool {
                return $id === 9
                    && $targetType === UserOperationLog::TARGET_TYPE_ARTICLE
                    && str_contains($content, 'article_delete')
                    && $actionType === UserOperationLog::ACTION_TYPE_DELETE;
            });

        $service = $this->makeService($repo, $adminRepo, $operationLogRepo);
        $result = $service->updateStatus(9, Article::STATUS_DELETED);

        $this->assertSame(Article::STATUS_DELETED, $result['status']);
    }

    public function test_update_rejects_deleted_article(): void
    {
        $repo = Mockery::mock(ArticleRepository::class);
        $this->mockApiTransaction();
        $repo->shouldReceive('findByIdForUpdate')->once()->andReturn(
            $this->makeArticle(['status' => Article::STATUS_DELETED]),
        );

        $service = $this->makeService($repo);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(trans('article.message.deleted_not_operable'));

        $service->update(9, $this->payload());
    }

    public function test_list_appends_category_text_publisher_and_asset_domain(): void
    {
        $article = $this->makeArticle([
            'published_by' => 88,
            'content' => json_encode(['en_US' => '<p><img src="article/body.png"></p>']),
        ]);
        $repo = Mockery::mock(ArticleRepository::class);
        $repo->shouldReceive('getList')->once()->andReturn([[$article], 1]);

        $adminRepo = Mockery::mock(AdminUserRepository::class);
        $adminRepo->shouldReceive('getColumnByIds')
            ->once()
            ->with([88], 'login_name')
            ->andReturn([88 => 'admin']);

        $service = $this->makeService($repo, $adminRepo, s3: $this->makeS3());
        $result = $service->getList([], 20, 0);
        $row = $result['data'][0];

        $this->assertSame('Getting Started, Platform Announcements & System Status', $row['category_text']);
        $this->assertSame('Announcement', $row['title']);
        $this->assertSame('admin', $row['published_by_name']);
        $this->assertSame('https://cdn.example.com/article/cover.png', $row['thumbnail']);
        $this->assertSame(
            '<p><img src="article/body.png"></p>',
            $row['locale_configs'][0]['content'],
        );
    }

    public function test_list_returns_content_images_verbatim(): void
    {
        $article = $this->makeArticle([
            'content' => json_encode([
                'en_US' => '<p><img src="https://cdn.example.com/article/existing.png"><img src="https://images.example.net/external.png"></p>',
            ]),
        ]);
        $repo = Mockery::mock(ArticleRepository::class);
        $repo->shouldReceive('getList')->once()->andReturn([[$article], 1]);

        $service = $this->makeService($repo, s3: $this->makeS3());
        $result = $service->getList([], 20, 0);

        $this->assertSame(
            '<p><img src="https://cdn.example.com/article/existing.png"><img src="https://images.example.net/external.png"></p>',
            $result['data'][0]['locale_configs'][0]['content'],
        );
    }

    private function makeService(
        ?ArticleRepository $repo = null,
        ?AdminUserRepository $adminRepo = null,
        ?UserOperationLogRepository $operationLogRepo = null,
        ?S3 $s3 = null,
    ): ArticleService {
        return new ArticleService(
            $repo ?? Mockery::mock(ArticleRepository::class),
            $adminRepo ?? Mockery::mock(AdminUserRepository::class),
            $operationLogRepo ?? Mockery::mock(UserOperationLogRepository::class),
            $s3 ?? $this->makeS3(),
        );
    }

    private function mockApiTransaction(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static fn (callable $callback): mixed => $callback());
        DB::shouldReceive('connection')->with(DB_API)->once()->andReturn($connection);
    }

    private function makeArticle(array $overrides = []): Article
    {
        $article = new Article;
        $article->setRawAttributes(array_merge([
            'id' => 9,
            'category' => json_encode([1, 10]),
            'is_top' => 0,
            'status' => Article::STATUS_DRAFT,
            'title' => json_encode(['en_US' => 'Announcement', 'zh_CN' => '公告']),
            'content' => json_encode(['en_US' => '<p>Content</p>', 'zh_CN' => '<p>内容</p>']),
            'thumbnail' => 'article/cover.png',
            'published_at' => '2026-09-07 10:00:00',
            'published_by' => 0,
            'created_at' => '2026-09-07 09:00:00',
            'updated_at' => '2026-09-07 09:30:00',
        ], $overrides), true);

        return $article;
    }

    private function makeS3(): S3
    {
        $s3 = Mockery::mock(S3::class);
        $s3->shouldReceive('fileUrl')
            ->andReturnUsing(static fn (string $key): string => 'https://cdn.example.com/'.$key);

        return $s3;
    }

    private function payload(): array
    {
        return [
            'category' => [10, 1, 1],
            'is_top' => 1,
            'thumbnail' => 'article/cover.png',
            'locale_configs' => [
                ['lang' => 'zh_CN', 'title' => '公告', 'content' => '<p><img src="http://cdn.example.com/article/own-zh.png"><img src="https://images.example.net/external-zh.png"></p>'],
                ['lang' => 'en_US', 'title' => 'Announcement', 'content' => '<p><img src="https://cdn.example.com/article/own-en.png"><img src="https://images.example.net/external-en.png"></p>'],
            ],
        ];
    }
}
