<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Article\ArticleListRequest;
use App\Http\Requests\Article\ArticleStatusRequest;
use App\Http\Requests\Article\ArticleStoreRequest;
use App\Http\Requests\Article\ArticleUpdateRequest;
use App\Models\Api\Article;
use App\Services\Api\ArticleService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

#[Group('Article', '文章/新闻管理')]
class ArticleController extends BaseController
{
    public function __construct(private ArticleService $articleService) {}

    #[QueryParameter(name: 'keyword', description: '标题搜索关键词，最大100字符；按 title JSON 内所有语言模糊匹配，无需传 %', type: 'string', required: false, example: '平台公告')]
    #[QueryParameter(name: 'category', description: '分类编码筛选；支持数组、逗号分隔字符串或 JSON 数组字符串，命中任一分类即返回', type: 'array<int, integer>|string', required: false, example: [1, 10])]
    #[QueryParameter(name: 'status', description: '状态筛选：0=草稿，1=已发布，2=已下线；列表固定不返回已删除', type: 'integer', required: false, example: 1)]
    #[QueryParameter(name: 'is_top', description: '是否置顶：0=否，1=是', type: 'integer', required: false, example: 1)]
    #[QueryParameter(name: 'published_at_from', description: '发布开始时间，格式 Y-m-d H:i:s', type: 'string', required: false, example: '2026-01-01 00:00:00')]
    #[QueryParameter(name: 'published_at_to', description: '发布结束时间，格式 Y-m-d H:i:s', type: 'string', required: false, example: '2026-12-31 23:59:59')]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, example: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, example: 0)]
    #[ScrambleResponse(
        description: '文章列表，固定按 id 倒序且不返回已删除文章。locale_configs 用于前端编辑；thumbnail 的对象 key 会拼接 S3 域名，content 原样返回。',
        type: 'array{data:array<int,array{id:int,category:array<int,int>,category_text:string,title:string,is_top:int,thumbnail:string,locale_configs:array<int,array{lang:string,title:string,content:string,thumbnail:string}>,status:int,status_name:string,published_at:string,published_by:int,published_by_name:string,created_at:string,updated_at:string}>,total_count:int}'
    )]
    /**
     * 文章列表
     *
     * Query:
     * - keyword:string 标题搜索关键词，选填，最大100字符；对 title JSON 中各语言标题做模糊匹配，前端无需传 %
     * - category:array<int,int> 分类筛选，命中任一分类即返回
     * - status:int 状态筛选；列表固定不返回已删除
     * - is_top:int 是否置顶筛选
     * - published_at_from:string 发布开始时间
     * - published_at_to:string 发布结束时间
     * - limit:int 每页条数，默认20
     * - offset:int 分页偏移量，默认0
     */
    public function index(ArticleListRequest $request): JsonResponse
    {
        return $this->succeed($this->articleService->getList(
            [
                'keyword' => trim((string) $request->input('keyword', '')),
                'categories' => array_map('intval', $request->validated('category', [])),
                'status' => $request->validated('status'),
                'is_top' => $request->validated('is_top'),
                'published_at_from' => trim((string) $request->input('published_at_from', '')),
                'published_at_to' => trim((string) $request->input('published_at_to', '')),
            ],
            max(1, (int) $request->input('limit', 20)),
            max(0, (int) $request->input('offset', 0)),
        ));
    }

    #[ScrambleResponse(
        description: '文章筛选项。分类来自 scaffold 中间件的 TypeArticleCategory，状态包含已删除选项供筛选组件使用，但列表始终排除已删除。',
        type: 'array{category_options:array<int,array{value:int,description:string}>,status_options:array<int,array{code:int,label:string}>}'
    )]
    /**
     * 文章选项
     */
    public function options(): JsonResponse
    {
        return $this->succeed($this->articleService->options());
    }

    #[BodyParameter(name: 'category', description: '文章分类编码数组，例如 [1,10]；编码来自 options.category_options.value', type: 'array<int, integer>', required: true, example: [1, 10])]
    #[BodyParameter(name: 'is_top', description: '是否置顶：0=否，1=是', type: 'integer', required: true, example: 0)]
    #[BodyParameter(name: 'thumbnail', description: '缩略图 S3 对象 key，最大500字符；不能传 http/https 或协议相对完整域名地址。不传、空字符串或 null 时使用数据库字段默认值，返回时由后端拼接域名', type: 'string', required: false, example: 'article/2026/cover.png')]
    #[BodyParameter(name: 'locale_configs', description: '多语言配置，推荐结构（与广告一致）：[{lang,title,content}]。lang 必须包含 en_US。也可兼容传 title/content 两个多语言 JSON 对象。', type: 'array<int, array{lang:string,title:string,content:string}>', required: false, example: [
        ['lang' => 'en_US', 'title' => 'Platform announcement', 'content' => '<p>Content</p>'],
        ['lang' => 'zh_CN', 'title' => '平台公告', 'content' => '<p>公告内容</p>'],
    ])]
    #[BodyParameter(name: 'locale_configs.*.lang', description: '语言：en_US,ja_JP,ru_RU,vi_VN,zh_CN,zh_TW', type: 'string', required: true, example: 'en_US')]
    #[BodyParameter(name: 'locale_configs.*.title', description: '当前语言文章标题，最大255字符', type: 'string', required: true, example: 'Platform announcement')]
    #[BodyParameter(name: 'locale_configs.*.content', description: '当前语言正文 HTML；后端不做图片地址过滤、替换或拼接，按传入内容原样保存和返回', type: 'string', required: true, example: '<p><img src="https://example.com/body.png"></p>')]
    #[BodyParameter(name: 'title', description: '兼容字段：标题多语言 JSON，例如 {"en_US":"Platform announcement"}；locale_configs 存在时优先使用 locale_configs', type: 'object', required: false)]
    #[BodyParameter(name: 'content', description: '兼容字段：正文多语言 JSON；content 内容原样保存和返回', type: 'object', required: false)]
    #[ScrambleResponse(description: '新增文章。后端固定创建草稿状态，并把 published_by 更新为当前后台登录用户。', type: 'bool')]
    /**
     * 新增文章
     */
    public function store(ArticleStoreRequest $request): JsonResponse
    {
        return $this->succeed(
            $this->articleService->create($request->validated()),
            trans('public.message.add_success'),
        );
    }

    #[PathParameter('article', description: '文章ID', type: 'integer')]
    #[BodyParameter(name: 'category', description: '文章分类编码数组', type: 'array<int, integer>', required: true, example: [1, 10])]
    #[BodyParameter(name: 'is_top', description: '是否置顶：0=否，1=是', type: 'integer', required: true, example: 1)]
    #[BodyParameter(name: 'thumbnail', description: '缩略图 S3 对象 key；不能传完整域名地址；不传、空字符串或 null 时使用数据库字段默认值', type: 'string', required: false, example: 'article/2026/cover-v2.png')]
    #[BodyParameter(name: 'locale_configs', description: '多语言配置，结构同新增接口', type: 'array<int, array{lang:string,title:string,content:string}>', required: false)]
    #[BodyParameter(name: 'title', description: '兼容字段：标题多语言 JSON', type: 'object', required: false)]
    #[BodyParameter(name: 'content', description: '兼容字段：正文多语言 JSON；content 内容原样保存和返回', type: 'object', required: false)]
    #[ScrambleResponse(description: '修改文章。除已删除外的状态都允许修改；每次修改都会更新 published_by 为当前后台登录用户，但不会修改状态和发布时间。', type: 'bool')]
    /**
     * 修改文章
     */
    public function update(ArticleUpdateRequest $request, int $article): JsonResponse
    {
        $result = $this->articleService->update($article, $request->validated());
        if ($result === null) {
            return $this->fail(trans('public.message.non_existent'));
        }

        return $this->succeed($result, trans('public.message.upd_success'));
    }

    #[PathParameter('article', description: '文章ID', type: 'integer')]
    #[BodyParameter(name: 'status', description: '目标状态：1=发布，2=下线，3=删除。发布仅草稿/已下线可用；下线仅已发布可用；任意未删除状态均可删除。', type: 'integer', required: true, example: 1)]
    #[ScrambleResponse(description: '统一变更文章状态。发布会更新 published_at 和 published_by；下线/删除只更新状态。', type: 'array{id:int,status:int,status_name:string,published_at:string,published_by:int,published_by_name:string}')]
    /**
     * 发布/下线/删除文章
     */
    public function updateStatus(ArticleStatusRequest $request, int $article): JsonResponse
    {
        $status = (int) $request->validated('status');
        $result = $this->articleService->updateStatus($article, $status);
        if ($result === null) {
            return $this->fail(trans('public.message.non_existent'));
        }

        $message = match ($status) {
            Article::STATUS_PUBLISHED => trans('article.message.publish_success'),
            Article::STATUS_OFFLINE => trans('article.message.offline_success'),
            Article::STATUS_DELETED => trans('public.message.del_success'),
            default => trans('public.message.upd_success'),
        };

        return $this->succeed($result, $message);
    }
}
