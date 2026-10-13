<?php

namespace App\Http\Requests\IbCommissionRule\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesIbCommissionRulePayload
{
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateLocaleConfigs($validator);
            $this->validateDetails($validator);
        });
    }

    private function validateLocaleConfigs(Validator $validator): void
    {
        $langs = [];

        foreach ((array) $this->input('locale_configs', []) as $config) {
            $lang = (string) ($config['lang'] ?? '');
            if ($lang === '') {
                continue;
            }

            if (isset($langs[$lang])) {
                $validator->errors()->add('locale_configs', trans('public.message.repeat_lang'));

                return;
            }

            $langs[$lang] = true;
        }

        if (! isset($langs['en_US'])) {
            $validator->errors()->add('locale_configs', trans('public.message.default_lang'));
        }
    }

    private function validateDetails(Validator $validator): void
    {
        $sources = [];

        foreach ((array) $this->input('details', []) as $detail) {
            $source = strtoupper(trim((string) ($detail['source'] ?? '')));
            $commissionAmount = $detail['commission_amount'] ?? null;
            $commissionFeeRate = $detail['commission_fee_rate'] ?? null;
            if ($source === '') {
                continue;
            }

            if (isset($sources[$source])) {
                $validator->errors()->add('details', trans('public.message.already_exist'));

                return;
            }

            $hasCommissionAmount = $commissionAmount !== null && $commissionAmount !== '';
            $hasCommissionFeeRate = $commissionFeeRate !== null && $commissionFeeRate !== '';

            // commission_amount / commission_fee_rate 二选一：必须且只能传一个。
            if ($hasCommissionAmount === $hasCommissionFeeRate) {
                $validator->errors()->add('details', trans('public.message.data_error'));

                return;
            }

            $sources[$source] = true;
        }
    }

    /**
     * @return array<int, string>
     */
    protected function langKeys(): array
    {
        return array_keys(config('lang.languages', []));
    }
}
