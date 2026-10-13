<?php

namespace App\Http\Requests\AppRelease;

class AppReleaseUpdateRequest extends AppReleaseStoreRequest
{
    /**
     * 修改版本时，platform/version/build_number 视为唯一主键组成，不允许变更。
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['platform'] = ['prohibited'];
        $rules['version'] = ['prohibited'];
        $rules['build_number'] = ['prohibited'];

        return $rules;
    }

    public function messages(): array
    {
        $messages = parent::messages();
        $messages['platform.prohibited'] = trans('public.message.data_error');
        $messages['version.prohibited'] = trans('public.message.data_error');
        $messages['build_number.prohibited'] = trans('public.message.data_error');

        return $messages;
    }

    /**
     * 支持从路由读取版本ID。
     */
    protected function prepareForValidation(): void
    {
        $id = $this->route('id');
        if ($id !== null && ! $this->has('id')) {
            $this->merge(['id' => (int) $id]);
        }
    }
}
