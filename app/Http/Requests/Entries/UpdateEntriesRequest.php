<?php

namespace App\Http\Requests\Entries;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEntriesRequest extends ApiRequest
{
    public function rules()
    {
        return [
            /* 描述 */
            'description' => ['nullable', 'string', 'max:255'],
            /* 名称 */
            'name' => ['required', 'string', 'max:100'],
            /* 广告位标识 */
            'identifier' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[a-z][a-z0-9_-]*$/'],
            /* 广告位id */
            'entry_id' => ['required', 'numeric'],
            /* 状态 */
            'status' => ['nullable', 'integer', Rule::in([0, 1])],
            /* 示例：{
                "entry_id": 1,
                "description": "test6",
                "name": "test5",
                "identifier": "test5",
                "status": 1
            } */
        ];
    }

    public function messages()
    {
        return [
            'description.string' => trans('ad.position.validation.description_string'),
            'description.max' => trans('ad.position.validation.description_max'),
            'name.required' => trans('ad.position.validation.entry_name_required'),
            'name.string' => trans('ad.position.validation.entry_name_string'),
            'name.max' => trans('ad.position.validation.entry_name_max'),
            'identifier.required' => trans('ad.position.validation.identifier_required'),
            'identifier.string' => trans('ad.position.validation.identifier_string'),
            'identifier.min' => trans('ad.position.validation.identifier_min'),
            'identifier.max' => trans('ad.position.validation.identifier_max'),
            'identifier.regex' => trans('ad.position.validation.identifier_regex'),
            'entry_id.required' => trans('ad.position.validation.entry_id_required'),
            'entry_id.numeric' => trans('ad.position.validation.entry_id_numeric'),
            'status.integer' => trans('ad.position.validation.entry_status_integer'),
            'status.in' => trans('ad.position.validation.entry_status_in'),
        ];
    }

    public function withValidator(Validator $validator) {}

    protected function prepareForValidation() {}
}
