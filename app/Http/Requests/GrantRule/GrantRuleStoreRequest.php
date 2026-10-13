<?php

namespace App\Http\Requests\GrantRule;

use App\Http\Requests\ApiRequest;
use App\Models\Api\GrantRule;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Enums\TypeGrantEvent;

class GrantRuleStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'target_type' => ['required', 'integer', Rule::in(GrantRule::SUPPORTED_TARGET_TYPES)],
            'target' => ['required', 'integer', 'min:1'],
            'grant_method' => ['required', 'integer', Rule::in([GrantRule::GRANT_METHOD_MANUAL, GrantRule::GRANT_METHOD_AUTO])],
            'total_count' => [
                Rule::requiredIf(fn (): bool => ! $this->isManualDelivery()),
                'nullable',
                'integer',
                'min:0',
            ],
            'start_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'end_at' => ['required', 'date_format:Y-m-d H:i:s', 'after_or_equal:start_at'],
            'user_codes' => [
                Rule::requiredIf(fn (): bool => (int) $this->input('grant_method') === GrantRule::GRANT_METHOD_MANUAL),
                'array',
                'min:1',
                'max:'.GrantRule::MAX_MANUAL_USER_COUNT,
            ],
            'user_codes.*' => ['integer', 'min:1'],
            'event_key' => [
                Rule::requiredIf(fn (): bool => (int) $this->input('grant_method') === GrantRule::GRANT_METHOD_AUTO),
                'nullable',
                'string',
                Rule::in($this->autoEventKeys()),
            ],
            'event_params' => [
                Rule::requiredIf(fn (): bool => $this->autoEventRequiresParams()),
                'nullable',
                'array',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => trans('grant_rule.validation.name_required'),
            'name.string' => trans('grant_rule.validation.name_string'),
            'name.max' => trans('grant_rule.validation.name_max'),

            'target_type.required' => trans('grant_rule.validation.target_type_required'),
            'target_type.integer' => trans('grant_rule.validation.target_type_integer'),
            'target_type.in' => trans('grant_rule.validation.target_type_in'),

            'target.required' => trans('grant_rule.validation.target_required'),
            'target.integer' => trans('grant_rule.validation.target_integer'),
            'target.min' => trans('grant_rule.validation.target_min'),

            'grant_method.required' => trans('grant_rule.validation.grant_method_required'),
            'grant_method.integer' => trans('grant_rule.validation.grant_method_integer'),
            'grant_method.in' => trans('grant_rule.validation.grant_method_in'),

            'total_count.required' => trans('grant_rule.validation.total_count_required'),
            'total_count.integer' => trans('grant_rule.validation.total_count_integer'),
            'total_count.min' => trans('grant_rule.validation.total_count_min'),

            'start_at.required' => trans('grant_rule.validation.start_at_required'),
            'start_at.date_format' => trans('grant_rule.validation.start_at_date_format'),

            'end_at.required' => trans('grant_rule.validation.end_at_required'),
            'end_at.date_format' => trans('grant_rule.validation.end_at_date_format'),
            'end_at.after_or_equal' => trans('grant_rule.validation.end_at_after_start'),

            'user_codes.required' => trans('grant_rule.validation.user_codes_required'),
            'user_codes.array' => trans('grant_rule.validation.user_codes_array'),
            'user_codes.min' => trans('grant_rule.validation.user_codes_min'),
            'user_codes.max' => trans('grant_rule.validation.user_codes_max', [
                'count' => GrantRule::MAX_MANUAL_USER_COUNT,
            ]),
            'user_codes.*.integer' => trans('grant_rule.validation.user_codes_item_integer'),
            'user_codes.*.min' => trans('grant_rule.validation.user_codes_item_min'),

            'event_key.required' => trans('grant_rule.validation.event_key_required'),
            'event_key.string' => trans('grant_rule.validation.event_key_string'),
            'event_key.in' => trans('grant_rule.validation.event_key_in'),

            'event_params.required' => trans('grant_rule.validation.event_params_required'),
            'event_params.array' => trans('grant_rule.validation.event_params_array'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $grantMethod = (int) $this->input('grant_method');
            $eventParams = $this->input('event_params', []);

            if (! is_array($eventParams)) {
                return;
            }

            if ($grantMethod === GrantRule::GRANT_METHOD_AUTO) {
                $eventKey = trim((string) $this->input('event_key', ''));
                $this->validateAutoEventParams($validator, $eventKey, $eventParams);
            }
        });
    }

    /**
     * 人工投递时发放总量可省略，服务端按投递用户数补齐。
     */
    private function isManualDelivery(): bool
    {
        return (int) $this->input('grant_method') === GrantRule::GRANT_METHOD_MANUAL;
    }

    /**
     * 后台批量粘贴的用户ID以逗号分隔，这里统一转为数组，供校验规则与服务层使用。
     */
    protected function prepareForValidation(): void
    {
        $userCodes = $this->input('user_codes');

        if ($userCodes === null) {
            return;
        }

        if (is_string($userCodes)) {
            $userCodes = preg_split('/[,，;；\s]+/u', trim($userCodes)) ?: [];
        }

        if (is_array($userCodes)) {
            $userCodes = array_map(
                static fn (mixed $userCode): mixed => is_numeric($userCode) ? (int) $userCode : $userCode,
                $userCodes
            );
        }

        $this->merge(['user_codes' => $userCodes]);
    }

    /**
     * @return array<int, string>
     */
    private function autoEventKeys(): array
    {
        return array_values(array_map(
            static fn (array $option): string => (string) ($option['key'] ?? ''),
            GrantRule::eventOptions()
        ));
    }

    private function autoEventRequiresParams(): bool
    {
        if ((int) $this->input('grant_method') !== GrantRule::GRANT_METHOD_AUTO) {
            return false;
        }

        $eventKey = trim((string) $this->input('event_key', ''));
        $eventOption = collect(GrantRule::eventOptions())->firstWhere('key', $eventKey);
        if ($eventKey === TypeGrantEvent::InviteeRegistered->value) {
            $eventParams = $this->input('event_params', []);
            if (! is_array($eventParams)) {
                return false;
            }

            $normalized = $this->normalizeEventParams($eventParams);

            return ! $this->isBlankValue($normalized['inviter_user_code'] ?? null);
        }

        return is_array($eventOption) && $this->eventParamKeys($eventOption) !== [];
    }

    private function validateAutoEventParams($validator, string $eventKey, array $eventParams): void
    {
        if ($eventKey === '') {
            return;
        }

        $eventOption = collect(GrantRule::eventOptions())->firstWhere('key', $eventKey);
        if (! is_array($eventOption)) {
            return;
        }

        $normalized = $this->normalizeEventParams($eventParams);
        $allowedParamKeys = $this->eventParamKeys($eventOption);
        $hasSelectedParam = false;
        foreach ($allowedParamKeys as $paramKey) {
            $value = $normalized[$paramKey] ?? null;
            if (! $this->isBlankValue($value)) {
                $hasSelectedParam = true;
                break;
            }
        }

        if ($allowedParamKeys !== []
            && ! $hasSelectedParam
            && $eventKey !== TypeGrantEvent::InviteeRegistered->value
        ) {
            $validator->errors()->add('event_params', trans('grant_rule.validation.event_params_required'));
        }

        $relations = GrantRule::eventRelations($eventKey);
        $relation = $normalized['relation'] ?? null;
        if ($relations !== []
            && ! $this->isBlankValue($relation)
            && ! in_array((string) $relation, $relations, true)
        ) {
            $validator->errors()->add('event_params.relation', trans('grant_rule.validation.relation_in'));
        }
    }

    /**
     * @param  array<string, mixed>  $eventOption
     * @return array<int, string>
     */
    private function eventParamKeys(array $eventOption): array
    {
        $keys = [];
        foreach ($eventOption['event_params'] ?? [] as $param) {
            if (! is_array($param)) {
                continue;
            }

            $paramKey = trim((string) ($param['param_key'] ?? ''));
            if ($paramKey !== '') {
                $keys[] = $paramKey;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $eventParams
     * @return array<string, mixed>
     */
    private function normalizeEventParams(array $eventParams): array
    {
        if (! array_is_list($eventParams)) {
            return $eventParams;
        }

        $normalized = [];
        foreach ($eventParams as $item) {
            if (! is_array($item)) {
                continue;
            }

            $paramKey = trim((string) ($item['param_key'] ?? ''));
            if ($paramKey === '') {
                continue;
            }

            $normalized[$paramKey] = $item['param_value'] ?? null;
        }

        return $normalized;
    }

    private function isBlankValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || trim((string) $value) === '';
    }
}
