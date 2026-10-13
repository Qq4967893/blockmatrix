<?php

namespace App\Http\Requests\SystemMessage;

use App\Http\Requests\ApiRequest;
use App\Http\Requests\ValidateTrait;
use App\Models\Api\SystemMessage;
use Illuminate\Validation\Rule;

class SystemMessageRequest extends ApiRequest
{
    use ValidateTrait;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            /** 消息类型 0:全部 1:指定 */
            'type'      => ['required', Rule::in([SystemMessage::TYPE_ALL, SystemMessage::TYPE_ASSIGN])],
            /** 标题 */
            'title'     =>
                [
                    'required',
                    'json',
                    function ($attribute, $value, $fail) {
                        $resp = $this->validateMultilingual($value);
                        if (!empty($resp)) {
                            $fail($resp);
                        }
                    }
                ],
            /** 内容 */
            'content'   =>
                [
                    'required',
                    'json',
                    function ($attribute, $value, $fail) {
                        $resp = $this->validateMultilingual($value);
                        if (!empty($resp)) {
                            $fail($resp);
                        }
                    }
                ],
            /** 大区id */
            'area_id'   => [Rule::exists('api.area', 'id')],
            /** 用户账号 */
            'user_code' => ['required_if:type,' . SystemMessage::TYPE_ASSIGN],
        ];
    }

    public function messages()
    {
        return [
            'type.required'         => trans('user.sys_message.type_empty'),
            'type.in'               => trans('user.sys_message.type_err'),
            'title.required'        => trans('user.sys_message.message_empty'),
            'title.json'            => trans('user.sys_message.format_err'),
            'content.required'      => trans('user.sys_message.message_empty'),
            'content.json'          => trans('user.sys_message.format_err'),
            'area_id.required'      => trans('user.sys_message.area_required'),
            'user_code.required_if' => trans('user.sys_message.user_not_empty'),
        ];
    }
}
