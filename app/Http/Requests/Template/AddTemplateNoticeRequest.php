<?php

namespace App\Http\Requests\Template;

use App\Http\Requests\ApiRequest;
use App\Models\Api\TemplateNotice;
use Illuminate\Validation\Rule;

class AddTemplateNoticeRequest extends ApiRequest
{
    public function rules()
    {
        return [
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
            'type.required' => trans('template.message.type_required'),
            'type.in' => trans('template.message.type_in'),
            'event_key.required' => trans('template.message.event_key_required'),
            'title.required' => trans('template.message.title_required'),
            'lang.required' => trans('template.message.lang_required'),
            'content.required' => trans('template.message.content_required'),
        ];
    }
}
