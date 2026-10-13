<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use App\Models\Blockmatrix\Role;

class RoleUpdateRequest extends ApiRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            /** 角色名 */
            'name'      => ['required', 'max:50',  Rule::unique(DB_BM.'.role', 'name')->where('status',
                Role::STATUS_NORMAL)->ignore
            ($this->route('role_id'))],
            /** 上级id */
            'parent_id' => [Rule::exists(DB_BM.'.role', 'id')],
            /** 菜单id,例如'1,3,4...' */
            'menu_ids'  => ['required'],
        ];
    }

    public function messages()
    {
        return [
            'name.required'      => trans('manager.role.name_not_empty'),
            'name.max'           => trans('manager.role.name_too_lang'),
            'name.unique'        => trans('manager.role.name_exist'),
            'menu_ids.required'  => trans('manager.menu.not_empty'),
        ];
    }
}
