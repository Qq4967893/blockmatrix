<?php

namespace App\Http\Requests\Menu;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use App\Models\Blockmatrix\Menu;
use function trans;

class MenuAddRequest extends ApiRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            /** 名称 */
            'name'       => ['required'],
            /** 上级菜单 */
            'parent_id'  => [Rule::exists(DB_BM.'.menu', 'id')->where('status', Menu::STATUS_NORMAL)],
            /** 菜单类型0:系统设置菜单,1:功能菜单 */
            'category'       => ['required', Rule::in([Menu::CATEGORY_SYS, Menu::CATEGORY_FUNC])],
            /** 菜单键值 */
            'menu_key'   => ['required', Rule::unique(DB_BM.'.menu')->where('status', Menu::STATUS_NORMAL)],
            /** API路径，多个路径使用逗号分隔 */
            'api_path'   => ['nullable', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'name.required'            => trans('manager.menu.not_empty'),
            'name.unique'              => trans('public.message.already_exist'),
            'parent_id.exists'         => trans('manager.message.superior_not_exist'),
            'category.required'            => trans('public.message.type_error'),
            'category.in'                  => trans('public.message.type_error'),
            'menu_key.required'        => trans('public.message.not_be_empty'),
            'menu_key.unique'          => trans('public.message.already_exist'),
        ];
    }
}
