<?php

namespace Tests\Unit\Repositories\Api;

use App\Models\Api\Article;
use App\Repositories\Api\ArticleRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArticleRepositoryTest extends TestCase
{
    private array $originalApiConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalApiConnection = config('database.connections.'.DB_API);
        config([
            'database.connections.'.DB_API => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge(DB_API);

        Schema::connection(DB_API)->create('article', function (Blueprint $table): void {
            $table->id();
            $table->json('category');
            $table->json('title');
            $table->json('content')->nullable();
            $table->string('thumbnail')->default('');
            $table->unsignedTinyInteger('is_top')->default(0);
            $table->unsignedTinyInteger('status')->default(Article::STATUS_DRAFT);
            $table->dateTime('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge(DB_API);
        config(['database.connections.'.DB_API => $this->originalApiConnection]);

        parent::tearDown();
    }

    public function test_get_list_filters_by_multilingual_title_keyword(): void
    {
        DB::connection(DB_API)->table('article')->insert([
            $this->article(1, ['en_US' => 'Platform Announcement', 'zh_CN' => '平台公告']),
            $this->article(2, ['en_US' => 'Market Update', 'zh_CN' => '市场更新']),
            $this->article(3, ['en_US' => 'Deleted Announcement', 'zh_CN' => '已删除公告'], Article::STATUS_DELETED),
        ]);

        $repository = new ArticleRepository(new Article);

        [$records, $count] = $repository->getList(['keyword' => '平台'], 20, 0);

        $this->assertSame([1], array_map(static fn (Article $article): int => (int) $article->id, $records));
        $this->assertSame(1, $count);
    }

    public function test_get_list_orders_pinned_articles_first(): void
    {
        $articles = [
            $this->article(1, ['en_US' => 'Oldest']),
            $this->article(2, ['en_US' => 'Pinned']),
            $this->article(3, ['en_US' => 'Newest']),
        ];
        $articles[1]['is_top'] = 1;

        DB::connection(DB_API)->table('article')->insert($articles);

        $repository = new ArticleRepository(new Article);

        [$records] = $repository->getList([], 20, 0);

        $this->assertSame(
            [2, 3, 1],
            array_map(static fn (Article $article): int => (int) $article->id, $records)
        );
    }

    private function article(int $id, array $title, int $status = Article::STATUS_PUBLISHED): array
    {
        return [
            'id' => $id,
            'category' => json_encode([1]),
            'title' => json_encode($title),
            'content' => json_encode(['en_US' => '<p>Content</p>']),
            'thumbnail' => 'article/cover.png',
            'is_top' => 0,
            'status' => $status,
            'published_at' => '2026-09-11 10:00:00',
            'published_by' => 1,
            'created_at' => '2026-09-11 09:00:00',
            'updated_at' => '2026-09-11 09:00:00',
        ];
    }
}
