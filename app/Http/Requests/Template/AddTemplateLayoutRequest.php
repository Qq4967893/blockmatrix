<?php

namespace App\Http\Requests\Template;

use App\Http\Requests\ApiRequest;

class AddTemplateLayoutRequest extends ApiRequest
{
    public function rules()
    {
        return [
            'name' => ['required', 'string'],
            'content' => ['required', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => trans('template.message.layout_name_required'),
            'content.required' => trans('template.message.content_required'),
        ];
    }
}
