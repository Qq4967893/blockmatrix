<?php

namespace App\Http\Requests\Badge;

class BadgeUpdateRequest extends BadgeStoreRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['icon'] = ['sometimes', 'string'];

        return $rules;
    }
}
