<?php

namespace App\Http\Requests\Template;

use App\Models\Api\TemplateNotice;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateNoticeRequest extends ApiRequest
{
    public function rules()
    {
        return [
            'id' => ['required', 'int', Rule::exists(TemplateNotice::class)],
            'type' => ['required', 'int', Rule::in(TemplateNotice::typeOptions())],
            'event_key' => ['required', 'string'],
            'title' => ['required', 'string'],
            'lang' => ['required', 'string'],
            'content' => ['required', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'id.required' => trans('template.message.id_required'),
            'id.exists' => trans('public.message.non_existent'),
            'type.required' => trans('template.message.type_required'),
            'type.in' => trans('template.message.type_in'),
            'event_key.required' => trans('template.message.event_key_required'),
            'title.required' => trans('template.message.title_required'),
            'lang.required' => trans('template.message.lang_required'),
            'content.required' => trans('template.message.content_required'),
        ];
    }
}
