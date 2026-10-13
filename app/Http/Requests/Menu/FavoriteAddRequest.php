<?php

namespace App\Http\Requests\Menu;

use App\Http\Requests\ApiRequest;
use function trans;

class FavoriteAddRequest extends ApiRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            /** 菜单标识 */
            'menu_key' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages()
    {
        return [
            'menu_key.required' => trans('public.message.not_be_empty'),
        ];
    }
}
