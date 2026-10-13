<?php

namespace App\Http\Requests\Template;

use App\Models\Api\TemplateLayout;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateLayoutRequest extends ApiRequest
{
    public function rules()
    {
        return [
            'id' => ['required', 'integer', Rule::exists(TemplateLayout::class)],
            'name' => ['required', 'string'],
            'content' => ['required', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'id.required' => trans('template.message.layout_id_required'),
            'id.exists' => trans('public.message.non_existent'),
            'name.required' => trans('template.message.layout_name_required'),
            'content.required' => trans('template.message.content_required'),
        ];
    }
}
