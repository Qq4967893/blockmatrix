<?php

namespace App\Http\Requests\PointRule;

use App\Http\Requests\ApiRequest;
use App\Models\Api\PointRule;
use App\Models\Point\PointRuleConstant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Models\Api\PointRule as FrameworkPointRule;
use Scaffold\Framework\Enums\TypeTradeAccount;

class PointRuleStoreRequest extends ApiRequest
{
    private const TRADE_ACCOUNT_TYPE_REQUIRED_EVENT_KEYS = [
        'open_trade',
        'close_trade',
        'first_open_trade',
    ];
    private const RELATION_SUPPORTED_EVENT_KEYS = [
        'open_trade',
        'close_trade',
    ];
    private const RELATION_OPTIONS = ['or', 'and'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 积分规则创建参数
     *
     * body:
     * - name_internal:string 内部显示名称
     * - locale_configs:array 多语言配置[{lang,name,description?,pop_up_image?,pop_up_text?}]
     * - jump_type:int|null 跳转类型（0=内部模块ID，1=外部链接），选填
     * - link:string|null 跳转地址（内部模块ID或http链接），选填
     * - event_key:string 触发事件编码
     * - event_params:array|null 事件配置(json对象)；trigger 事件时 identifier 必填
     * - relation:string|null 条件关系(open_trade/close_trade 必填，or/and)
     * - cycle_limit:int 周期内限制次数
     * - cycle_period:int 周期类型(0不限/1天/2周/3月)
     * - cycle_period_day:int|null 周期内可触发日期，0入库为null
     * - start_at:string|null 有效期开始时间
     * - end_at:string|null 有效期结束时间
     * - points:int 奖励积分
     * - points_type:int|null 积分类型(1=XP/2=Drop，默认1)
     * - task_type:int|null 任务类型
     * - status:int|null 状态(-1待启用/0禁用/1启用，默认-1)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $langKeys    = array_keys(config('lang.languages', []));
        $eventKeys    = PointRuleConstant::eventKeys();
        $cyclePeriods = [
            0,
            FrameworkPointRule::CYCLE_DAILY,
            FrameworkPointRule::CYCLE_WEEKLY,
            FrameworkPointRule::CYCLE_MONTHLY,
        ];
        $statuses     = [
            PointRule::STATUS_PENDING,
            PointRule::STATUS_DISABLED,
            PointRule::STATUS_ENABLED,
        ];

        return [
            /** 内部名称 */
            'name_internal' => [
                'required',
                'string',
                'max:100',
                Rule::unique('api.point_rule', 'name_internal'),
            ],
            /** 多语言配置 */
            'locale_configs'              => ['required', 'array', 'min:1'],
            'locale_configs.*.lang'       => ['required', 'string', Rule::in($langKeys)],
            'locale_configs.*.name'       => ['required', 'string'],
            'locale_configs.*.description'=> ['nullable', 'string'],
            'locale_configs.*.pop_up_image'    => ['nullable', 'string'],
            'locale_configs.*.pop_up_text'     => ['nullable', 'string'],
            /** 跳转类型 */
            'jump_type'     => ['nullable', 'integer', Rule::in([PointRuleConstant::JUMP_TYPE_INTERNAL, PointRuleConstant::JUMP_TYPE_EXTERNAL])],
            /** 跳转链接 */
            'link'          => ['nullable', 'string'],
            /** 事件编码 */
            'event_key'     => ['required', 'string', Rule::in($eventKeys)],
            /** 事件配置项(键值对) */
            'event_params'  => ['nullable', 'array'],
            /** 条件关系 */
            'relation'      => [
                Rule::requiredIf(fn () => in_array((string) $this->input('event_key'), self::RELATION_SUPPORTED_EVENT_KEYS, true)),
                'nullable',
                'string',
                Rule::in(self::RELATION_OPTIONS),
            ],
            /** 循环次数：0=无限次，>0 表示可触发次数上限 */
            'cycle_limit'   => ['required', 'integer', 'min:0'],
            /** 循环周期：0=无限，1=每日，2=每周，3=每 */
            'cycle_period'  => ['required', 'integer', Rule::in($cyclePeriods)],
            /** 周期内可触发日期：0 入库为 null */
            'cycle_period_day' => ['nullable', 'integer', 'min:0'],
            /** 生效开始时间 */
            'start_at'      => ['nullable', 'date'],
            /** 生效结束时间 */
            'end_at'        => ['nullable', 'date'],
            /** 奖励积分（ib_level_upgrade 事件可不填） */
            'points'        => [
                Rule::requiredIf(fn () => (string) $this->input('event_key') !== PointRule::EVENT_KEY_IB_LEVEL_UPGRADE),
                'nullable',
                'integer',
                'min:0',
            ],
            /** 任务类型 */
            'task_type'     => ['sometimes', 'integer', Rule::in([
                PointRule::TASK_TYPE_NEW_USER,
                PointRule::TASK_TYPE_TRADE,
                PointRule::TASK_TYPE_SOCIAL,
            ])],
            /** 积分类型 */
            'points_type'   => ['nullable', 'integer', Rule::in([
                PointRule::POINTS_TYPE_XP,
                PointRule::POINTS_TYPE_DROPLET,
            ])],
            /** 状态 */
            'status'        => ['nullable', 'integer', Rule::in($statuses)],
        ];
    }

    public function messages(): array
    {
        return [
            'name_internal.required' => trans('public.message.in_complete'),
            'name_internal.string'   => trans('public.message.type_error'),
            'name_internal.max'      => trans('public.message.wrong_threshold'),
            'name_internal.unique'   => trans('public.message.already_exist'),

            'locale_configs.required'        => trans('public.message.in_complete'),
            'locale_configs.array'           => trans('public.message.data_error'),
            'locale_configs.min'             => trans('public.message.in_complete'),
            'locale_configs.*.lang.required' => trans('public.message.in_complete'),
            'locale_configs.*.lang.in'       => trans('public.message.lang_err'),
            'locale_configs.*.name.required' => trans('public.message.in_complete'),

            'jump_type.integer' => trans('public.message.type_error'),
            'jump_type.in'      => trans('public.message.wrong_options'),

            'event_key.required' => trans('public.message.in_complete'),
            'event_key.in'       => trans('public.message.wrong_options'),

            'event_params.array' => trans('public.message.type_error'),
            'relation.required'  => trans('public.message.in_complete'),
            'relation.string'    => trans('public.message.type_error'),
            'relation.in'        => trans('public.message.wrong_options'),

            'cycle_limit.required' => trans('public.message.in_complete'),
            'cycle_limit.integer'  => trans('public.message.type_error'),
            'cycle_limit.min'      => trans('public.message.wrong_threshold'),

            'cycle_period.required' => trans('public.message.in_complete'),
            'cycle_period.integer'  => trans('public.message.type_error'),
            'cycle_period.in'       => trans('public.message.wrong_options'),

            'cycle_period_day.integer' => trans('public.message.type_error'),
            'cycle_period_day.min'     => trans('public.message.wrong_threshold'),

            'start_at.date' => trans('public.message.wrong_format_date'),
            'end_at.date'   => trans('public.message.wrong_format_date'),

            'points.required' => trans('public.message.in_complete'),
            'points.integer'  => trans('public.message.type_error'),
            'points.min'      => trans('public.message.wrong_threshold'),

            'task_type.required' => trans('public.message.in_complete'),
            'task_type.integer'  => trans('public.message.type_error'),
            'task_type.in'       => trans('public.message.wrong_options'),

            'points_type.integer' => trans('public.message.type_error'),
            'points_type.in'      => trans('public.message.wrong_options'),

            'status.integer' => trans('public.message.type_error'),
            'status.in'      => trans('public.message.wrong_options'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $configs = $this->input('locale_configs', []);
            if (! is_array($configs)) {
                $this->validateCyclePeriodDay($validator);
                return;
            }

            $langs        = [];
            $hasAny       = false;
            $hasDefault   = false;
            $envLocale    = config('app.locale');
            $confLocale   = config('app.locale', 'en_US');
            // 积分规则默认语言强制使用英文，环境配置仅记录
            $defaultLang  = 'en_US';
            $defaultLower = strtolower($defaultLang);
            Log::info('point_rule.locale_check', [
                'action'         => 'store',
                'default_lang'   => $defaultLang,
                'env_locale'     => $envLocale,
                'config_locale'  => $confLocale,
                'locale_configs' => $configs,
            ]);
            foreach ($configs as $config) {
                if (! is_array($config)) {
                    continue;
                }
                $lang = trim((string) ($config['lang'] ?? ''));
                if ($lang !== '') {
                    if (strtolower($lang) === $defaultLower) {
                        $hasDefault = true;
                    }
                    if (in_array($lang, $langs, true)) {
                        $validator->errors()->add('locale_configs', trans('public.message.repeat_lang'));
                    }
                    $langs[] = $lang;
                }

                $name = isset($config['name']) ? trim((string) $config['name']) : '';
                if ($name !== '') {
                    $hasAny = true;
                }
            }

            if (! $hasAny) {
                $validator->errors()->add('locale_configs', trans('public.message.Incomplete'));
            }

            if (! empty($langs) && ! $hasDefault) {
                $validator->errors()->add('locale_configs', trans('public.message.default_lang'));
            }

            $this->validateCyclePeriodDay($validator);
            $this->validateTradeAccountType($validator);
            $this->validateJumpLink($validator);
            $this->validateTriggerIdentifier($validator);
        });
    }

    private function validateCyclePeriodDay($validator): void
    {
        if ($validator->errors()->has('cycle_period') || $validator->errors()->has('cycle_period_day')) {
            return;
        }

        $cyclePeriodDay = $this->input('cycle_period_day');
        if ($cyclePeriodDay === null || $cyclePeriodDay === '') {
            return;
        }

        $cyclePeriod = (int) $this->input('cycle_period');
        $cyclePeriodDay = (int) $cyclePeriodDay;

        $isValid = match ($cyclePeriod) {
            0, 1, 3 => $cyclePeriodDay >= 0 && $cyclePeriodDay <= 31,
            2 => $cyclePeriodDay >= 0 && $cyclePeriodDay <= 7,
            default => false,
        };

        if (! $isValid) {
            $validator->errors()->add('cycle_period_day', trans('point_rule.message.invalid_cycle_period_day'));
        }
    }

    private function validateTradeAccountType($validator): void
    {
        if ($validator->errors()->has('event_key') || $validator->errors()->has('event_params')) {
            return;
        }

        $eventKey = (string) $this->input('event_key');
        if (! in_array($eventKey, self::TRADE_ACCOUNT_TYPE_REQUIRED_EVENT_KEYS, true)) {
            return;
        }

        $eventParams = $this->input('event_params', []);
        if (! is_array($eventParams)) {
            return;
        }

        $tradeAccountType = $this->extractTradeAccountType($eventParams);
        if ($tradeAccountType === null || $tradeAccountType === '') {
            $validator->errors()->add('event_params.trade_account_type', trans('public.message.in_complete'));
            return;
        }

        $allowedTypes = array_column(TypeTradeAccount::cases(), 'value');
        if (! is_numeric((string) $tradeAccountType) || ! in_array((int) $tradeAccountType, $allowedTypes, true)) {
            $validator->errors()->add('event_params.trade_account_type', trans('public.message.wrong_options'));
        }
    }

    private function extractTradeAccountType(array $eventParams): mixed
    {
        if (array_key_exists('trade_account_type', $eventParams)) {
            return $eventParams['trade_account_type'];
        }

        foreach ($eventParams as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['param_key'] ?? null) === 'trade_account_type') {
                return $item['param_value'] ?? null;
            }

            if (array_key_exists('trade_account_type', $item)) {
                return $item['trade_account_type'];
            }
        }

        return null;
    }

    /**
     * 校验 jump_type 与 link 的合法性
     */
    private function validateJumpLink($validator): void
    {
        $jumpType = $this->input('jump_type');
        $link = trim((string) ($this->input('link', '')));

        // jump_type 和 link 都不传则跳过校验
        if (($jumpType === null || $jumpType === '') && $link === '') {
            return;
        }

        $jumpType = is_numeric($jumpType) ? (int) $jumpType : null;
        if ($jumpType === null) {
            $validator->errors()->add('jump_type', trans('public.message.in_complete'));
            return;
        }

        if ($link === '') {
            $validator->errors()->add('link', trans('public.message.in_complete'));
            return;
        }

        if ($jumpType === PointRuleConstant::JUMP_TYPE_INTERNAL) {
            // 内部模块：link 必须在合法模块ID列表中
            if (! in_array((int) $link, PointRuleConstant::internalModuleIds(), true)) {
                $validator->errors()->add('link', trans('public.message.wrong_options'));
            }
        } elseif ($jumpType === PointRuleConstant::JUMP_TYPE_EXTERNAL) {
            // 外部链接：link 必须是 http/https 地址
            if (! preg_match('/^https?:\/\/.+/i', $link)) {
                $validator->errors()->add('link', trans('public.message.format_err'));
            }
        }
    }

    /**
     * 校验 trigger 事件的 identifier 必填
     */
    private function validateTriggerIdentifier($validator): void
    {
        $eventKey = (string) $this->input('event_key', '');
        if ($eventKey !== 'trigger') {
            return;
        }

        if ($validator->errors()->has('event_key') || $validator->errors()->has('event_params')) {
            return;
        }

        $identifier = $this->extractIdentifierFromEventParams($this->input('event_params', []));
        if ($identifier === null || $identifier === '') {
            $validator->errors()->add('event_params', trans('public.message.in_complete'));
        }
    }

    /**
     * 从 event_params 中提取 identifier 值（兼容对象和数组两种格式）
     *
     * @param array $eventParams
     * @return string|null
     */
    private function extractIdentifierFromEventParams(array $eventParams): ?string
    {
        // 格式：[{"param_key": "identifier", "param_value": "xxx"}]
        foreach ($eventParams as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['param_key'] ?? null) === 'identifier') {
                $value = $item['param_value'] ?? null;
                return $value !== null && $value !== '' ? (string) $value : null;
            }
        }

        return null;
    }
}
