<?php

namespace App\Http\Requests\Menu;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use App\Models\Blockmatrix\Menu;
use function trans;

class MenuUpdateRequest extends ApiRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        /** 路由参数中的菜单id */
        $menuId = $this->route('menu_id');

        return [
            /** 菜单名 */
            'name'       => ['required'],
            /** 上级菜单 */
            'parent_id'  => [Rule::exists(DB_BM.'.menu','id')->where('status', Menu::STATUS_NORMAL)],
            /** 菜单类型 0-系统设置菜单 1-功能菜单 */
            'category'       => ['required', Rule::in([Menu::CATEGORY_SYS, Menu::CATEGORY_FUNC])],
            /** API路径，多个路径使用逗号分隔 */
            'api_path'       => ['nullable', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'name.required'            => trans('manager.menu.not_empty'),
            'name.unique'              => trans('public.message.already_exist'),
            'parent_id.exists'         => trans('manager.message.superior_not_exist'),
            'category.required'        => trans('public.message.type_error'),
            'category.in'              => trans('public.message.type_error'),
        ];
    }
}
