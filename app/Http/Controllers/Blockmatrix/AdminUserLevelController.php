<?php

namespace App\Http\Controllers\Blockmatrix;

use App\Http\Controllers\BaseController;
use App\Services\Blockmatrix\UserLevelService;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Models\Blockmatrix\UserLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminUserLevelController extends BaseController
{
    public function __construct(
        private UserLevelService    $userLevelService,
        private AdminUserRepository $adminUserRepo
    ) {
    }

    /**
     * 获取所有用户层级
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function list()
    {
        $data = $this->userLevelService->getList();

        return $this->succeed($data);
    }

    /**
     * 添加层级
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(Request $request)
    {
        /* 名称 */
        $name = $request->input('name');
        /* 类型  1-内容层级 2-外部层级*/
        $type = $request->input('type');
        /* 父级id */
        $parentId  = $request->input('parent_id');

        if (empty($name)) {
            return $this->fail(trans('public.message.not_be_empty'));
        }
        if (! in_array($type, [UserLevel::TYPE_INSIDE, UserLevel::TYPE_OUTSIDE])) {
            return $this->fail(trans('public.message.type_error'));
        }
        $arr['name'] = $name;
        $arr['type'] = $type;
        $arr['parent_id']  = $parentId;

        $this->userLevelService->add($arr);
        $str = implode('-', $arr);
        Log::info(LOGIN_USER . "新增用户层级" . $str);

        return $this->succeed();
    }

    /**
     * 修改层级
     *
     * @param Request $request request
     * @param int     $levelId id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function modify(Request $request, $levelId)
    {
        /* 名称 */
        $name = $request->input('name', '');
        /* 父级id */
        $parentId  = $request->input('parent_id', null);

        if (empty($name) || $parentId == -1) {
            return $this->fail(trans('public.message.not_be_empty'));
        }

        $info = $this->userLevelService->getById($levelId);
        if (empty($info)) {
            return $this->fail(trans('public.message.non_existent'));
        }
        if ($parentId == $info['id']) {
            return $this->fail(trans('public.message.wrong_threshold'));
        }

        $arr['name'] = $name;
        if (! is_null($parentId)) {
            $arr['$parent_id'] = $parentId;
        }

        $this->userLevelService->updateLevel($levelId, $arr);
        $str = implode('-', $arr);
        Log::info(LOGIN_USER . "修改用户层级" . $str);

        return $this->succeed();
    }

    /**
     * 删除层级
     *
     * @param int $levelId 层级id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($levelId)
    {
        $info = $this->userLevelService->getById($levelId);
        if (empty($info)) {
            return $this->fail(trans('public.message.non_existent'));
        }

        $users = $this->adminUserRepo->getByLevelIds([$levelId]);
        if (! empty($users)) {
            return $this->fail(trans('public.message.correlation'));
        }
        Log::info(LOGIN_USER . "删除用户层级" . $levelId);
        $this->userLevelService->delete($levelId);

        return $this->succeed();
    }
}
