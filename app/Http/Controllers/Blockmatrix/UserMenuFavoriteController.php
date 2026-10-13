<?php

namespace App\Http\Controllers\Blockmatrix;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Menu\FavoriteAddRequest;
use App\Services\Blockmatrix\UserMenuFavoriteService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[Group('UserMenuFavorite', '后台用户菜单收藏')]
class UserMenuFavoriteController extends BaseController
{
    public function __construct(
        private UserMenuFavoriteService $userMenuFavoriteService
    ) {
    }

    /**
     * 获取我的收藏菜单列表
     *
     * 仅返回当前登录用户有权限且状态正常的收藏菜单，按收藏时间倒序
     *
     * Response.data:array<object> 收藏菜单列表[{id,name,parent_id,pos,menu_key}]
     *
     * @return JsonResponse
     */
    public function list()
    {
        return $this->succeed(
            $this->userMenuFavoriteService->list(Auth::id(), Auth::user())
        );
    }

    /**
     * 收藏菜单
     *
     * @param FavoriteAddRequest $request request
     *
     * @return JsonResponse
     * @throws \App\Exceptions\BusinessException 菜单不存在、无权限或超出收藏上限
     */
    #[BodyParameter(name: 'menu_key', description: '菜单标识', type: 'string')]
    public function add(FavoriteAddRequest $request)
    {
        $this->userMenuFavoriteService->add(
            Auth::id(),
            (string)$request->input('menu_key'),
            Auth::user()
        );

        return $this->succeed();
    }

    /**
     * 取消收藏菜单
     *
     * @param Request $request request
     *
     * @return JsonResponse
     */
    #[QueryParameter(name: 'menu_key', description: '菜单标识', type: 'string')]
    public function remove(Request $request)
    {
        $menuKey = (string)$request->input('menu_key', '');
        if ($menuKey === '') {
            return $this->fail(trans('public.message.param_non_existent', ['param' => 'menu_key']));
        }

        $this->userMenuFavoriteService->remove(Auth::id(), $menuKey);

        return $this->succeed();
    }
}
