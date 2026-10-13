<?php

namespace App\Http\Requests\FundingLedger;

use App\Http\Requests\ApiRequest;
use App\Models\Api\FundingLedger;
use App\Models\Api\Money;
use App\Models\Api\User;
use Illuminate\Validation\Rule;

/**
 * 资金管理新增请求校验。
 */
class FundingLedgerAddRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 约束资金管理新增接口的输入格式与可选值范围。
     */
    public function rules(): array
    {
        return [
            'user_codes' => ['required', 'string', 'regex:/^\d+(;\d+)*$/'],
            'fund_type' => ['required', 'integer', Rule::in(FundingLedger::managementTypeOptions())],
            'direction' => ['required', 'integer', Rule::in(FundingLedger::directionOptions())],
            'amount' => ['required', 'numeric', 'gt:0', 'lt:' . Money::MAX_MANUAL_AMOUNT],
            'reason' => ['nullable', 'string', 'max:255'],
            'activity_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * 返回统一的请求参数校验错误文案。
     */
    public function messages(): array
    {
        return [
            'user_codes.required' => trans('public.message.data_error'),
            'user_codes.string' => trans('public.message.data_error'),
            'user_codes.regex' => trans('public.message.data_error'),
            'fund_type.required' => trans('public.message.data_error'),
            'fund_type.integer' => trans('public.message.data_error'),
            'fund_type.in' => trans('public.message.data_error'),
            'direction.required' => trans('public.message.data_error'),
            'direction.integer' => trans('public.message.data_error'),
            'direction.in' => trans('public.message.data_error'),
            'amount.required' => trans('public.message.data_error'),
            'amount.numeric' => trans('public.message.data_error'),
            'amount.gt' => trans('public.message.amount_error'),
            'amount.lt' => trans('public.message.amount_lt_max', ['max' => Money::MAX_MANUAL_AMOUNT]),
            'reason.string' => trans('public.message.data_error'),
            'reason.max' => trans('public.message.data_error'),
            'activity_name.string' => trans('public.message.data_error'),
            'activity_name.max' => trans('public.message.data_error'),
        ];
    }

    /**
     * 将 user_codes 字符串解析为 user_code 数组。
     *
     * @return int[]
     */
    public function userCodes(): array
    {
        $userCodes = explode(';', (string) $this->input('user_codes', ''));

        return array_map('intval', array_filter($userCodes, static fn (string $userCode) => $userCode !== ''));
    }

    /**
     * 校验分号分隔的用户是否全部存在于 api.user 表。
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $userCodes = array_values(array_unique($this->userCodes()));
            if ($userCodes === []) {
                return;
            }

            if (count($userCodes) > FundingLedger::MAX_BATCH_USER_COUNT) {
                $validator->errors()->add('user_codes', trans('public.message.data_error'));

                return;
            }

            $existingUserCodes = User::query()
                ->whereIn('user_code', $userCodes)
                ->pluck('user_code')
                ->map(static fn ($userCode) => (int) $userCode)
                ->all();

            $missingUserCodes = array_diff($userCodes, $existingUserCodes);
            if ($missingUserCodes !== []) {
                $validator->errors()->add(
                    'user_codes',
                    trans('user.message.not_exist') . ':' . implode(';', $missingUserCodes)
                );
            }
        });
    }

    /**
     * 规范化 user_codes 输入，兼容中文分号与分号两侧空格。
     */
    protected function prepareForValidation(): void
    {
        $userCode = (string) $this->input('user_codes', '');
        if ($userCode === '') {
            return;
        }

        $userCode = str_replace('；', ';', $userCode);
        $userCode = preg_replace('/\s*;\s*/', ';', trim($userCode)) ?? '';

        $this->merge([
            'user_codes' => trim($userCode, ';'),
        ]);
    }
}
