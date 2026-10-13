<?php

namespace Tests\Feature;

use App\Http\Requests\Article\ArticleListRequest;
use App\Http\Requests\Article\ArticleStatusRequest;
use App\Http\Requests\Article\ArticleStoreRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Redirector;
use Tests\TestCase;

class ArticleRequestTest extends TestCase
{
    public function test_article_list_normalizes_category_filter(): void
    {
        $request = ArticleListRequest::create(
            '/api/articles',
            'GET',
            ['category' => '[1,10]'],
        );
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        $request->validateResolved();

        $this->assertSame([1, 10], $request->validated('category'));
    }

    public function test_article_list_rejects_deleted_status_filter(): void
    {
        $request = ArticleListRequest::create(
            '/api/articles',
            'GET',
            ['status' => 3],
        );
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        try {
            $request->validateResolved();
            $this->fail('Expected deleted status filter validation to fail.');
        } catch (HttpResponseException $exception) {
            $payload = $exception->getResponse()->getData(true);

            $this->assertSame(trans('article.validation.list_status_in'), $payload['message']);
        }
    }

    public function test_article_status_accepts_transition_targets(): void
    {
        foreach ([1, 2, 3] as $status) {
            $request = ArticleStatusRequest::create(
                '/api/articles/9/status',
                'PUT',
                ['status' => $status],
            );
            $request->setContainer($this->app);
            $request->setRedirector($this->app->make(Redirector::class));

            $request->validateResolved();

            $this->assertSame($status, $request->validated('status'));
        }
    }

    public function test_article_status_rejects_draft_target(): void
    {
        $request = ArticleStatusRequest::create(
            '/api/articles/9/status',
            'PUT',
            ['status' => 0],
        );
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        try {
            $request->validateResolved();
            $this->fail('Expected draft target status validation to fail.');
        } catch (HttpResponseException $exception) {
            $payload = $exception->getResponse()->getData(true);

            $this->assertSame(trans('article.validation.status_in'), $payload['message']);
        }
    }

    public function test_article_accepts_ad_style_locale_configs(): void
    {
        $request = $this->makeRequest([]);
        $request->validateResolved();

        $this->assertSame('en_US', $request->validated('locale_configs.0.lang'));
    }

    public function test_article_thumbnail_rejects_complete_domain_url(): void
    {
        foreach ([
            'https://cdn.example.com/article/cover.png',
            'http://cdn.example.com/article/cover.png',
            '//cdn.example.com/article/cover.png',
            'ftp://cdn.example.com/article/cover.png',
        ] as $thumbnail) {
            $request = $this->makeRequest(['thumbnail' => $thumbnail]);

            try {
                $request->validateResolved();
                $this->fail('Expected thumbnail URL validation to fail.');
            } catch (HttpResponseException $exception) {
                $payload = $exception->getResponse()->getData(true);

                $this->assertSame(trans('article.validation.thumbnail_not_url'), $payload['message']);
            }
        }
    }

    public function test_article_thumbnail_accepts_s3_object_key(): void
    {
        $request = $this->makeRequest(['thumbnail' => 'article/20260908/57609426639.jpeg']);
        $request->validateResolved();

        $this->assertSame(
            'article/20260908/57609426639.jpeg',
            $request->validated('thumbnail'),
        );
    }

    public function test_article_requires_default_language(): void
    {
        $request = $this->makeRequest([
            'locale_configs' => [
                ['lang' => 'zh_CN', 'title' => '公告', 'content' => '<p>内容</p>'],
            ],
        ]);

        try {
            $request->validateResolved();
            $this->fail('Expected default language validation to fail.');
        } catch (HttpResponseException $exception) {
            $payload = $exception->getResponse()->getData(true);

            $this->assertSame(trans('public.message.default_lang'), $payload['message']);
        }
    }

    public function test_article_accepts_json_string_multilingual_fields(): void
    {
        $request = $this->makeRequest([
            'remove_locale_configs' => true,
            'title' => '{"en_US":"Announcement"}',
            'content' => '{"en_US":"<p>Content</p>"}',
        ]);
        $request->validateResolved();

        $this->assertSame('Announcement', $request->validated('title.en_US'));
    }

    private function makeRequest(array $overrides): ArticleStoreRequest
    {
        $payload = array_replace_recursive([
            'category' => [1],
            'is_top' => 0,
            'locale_configs' => [
                ['lang' => 'en_US', 'title' => 'Announcement', 'content' => '<p>Content</p>'],
            ],
        ], $overrides);
        unset($payload['remove_locale_configs']);
        if (! empty($overrides['remove_locale_configs'])) {
            unset($payload['locale_configs']);
        }

        $request = ArticleStoreRequest::create('/api/articles', 'POST', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        return $request;
    }
}
