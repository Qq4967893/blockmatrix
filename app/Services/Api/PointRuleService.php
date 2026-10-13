<?php

namespace App\Services\Api;

use App\Models\Api\PointRule;
use App\Models\Point\PointRuleConstant;
use App\Repositories\Api\IbLevelRepository;
use App\Repositories\Api\PointRuleRepository;
use App\Utils\Email;
use Carbon\Carbon;
use Scaffold\Framework\Models\Api\PointRule as FrameworkPointRule;

class PointRuleService
{
    private const EVENT_PARAM_RENDER_MODE_SELECT = 1;
    private const EVENT_PARAM_RENDER_MODE_MULTI = 2;
    private const EVENT_PARAM_RELATION_OPTIONS = ['or', 'and'];
    private const EVENT_KEYS_SUPPORT_PARAM_RELATION = ['open_trade', 'close_trade'];
    private ?array $eventOptionsCache = null;

    public function __construct(
        private PointRuleRepository $repo,
        private IbLevelRepository $ibLevelRepo,
    ) {
    }

    /**
     * 获取积分规则列表
     *
     * 查询参数：
     * - keyword：内部名称/同名称模糊搜索
     * - status：状态（-1 待启用，0 禁用，1 启用）
     *
     * 返回字段：
     * - data[].id：规则ID
     * - data[].name_internal：内部展示名称
     * - data[].locale_configs：多语言配置[{lang,name,description}]
     * - data[].event_key：触发事件标识
     * - data[].event_key_label：事件说明（多语言）
     * - data[].event_params：事件附加配置
     * - data[].event_params_text：事件参数文本数组
     * - data[].relation：事件条件关系（open_trade/close_trade 支持，or/and）
     * - data[].cycle_limit：周期内触发次数上限
     * - data[].cycle_period：周期类型
     * - data[].cycle_period_day：周期内可触发日期
     * - data[].cycle_period_label：周期说明
     * - data[].start_at/data[].end_at：总任务生效时间起止，格式 Y-m-d H:i:s
     * - data[].points：可获取积分
     * - data[].points_type：积分类型
     * - data[].points_type_text：积分类型说明
     * - data[].status：状态值
     * - data[].status_label：状态说明
     * - data[].task_type：任务类型
     * - data[].task_type_label：任务类型说明
     * - data[].created_at/updated_at：变动时间，格式 Y-m-d H:i:s
     * - total_count：记录总数
     *
     * @param string   $keyword 关键词（内部/同名）
     * @param int|null $status  状态：-1待启用 0禁用 1启用 null全部
     * @param int      $limit   每页条数
     * @param int      $offset  偏移量
     *
     * @return array{data: array<int, array{id:int,name_internal:string,locale_configs:array,event_key:string,event_key_label:string,event_params:array,event_params_text:array<int,string>,relation:?string,cycle_limit:int,cycle_period:int,cycle_period_day:?int,cycle_period_label:string,start_at:?string,end_at:?string,points:int,points_type:int,points_type_text:string,status:int,status_label:string,task_type:int,task_type_label:string,created_at:?string,updated_at:?string}>, total_count:int}
     */
    public function getList($keyword, $status, $limit, $offset)
    {
        [$data, $count] = $this->repo->getList($keyword, $status, $limit, $offset);

        $data = array_map(fn ($rule) => $this->formatRule($rule), $data);

        return [
            'data'        => $data,
            'total_count' => $count,
        ];
    }

    /**
     * 新增积分规则
     *
     * event_params 按事件定义补齐全部参数键（trade_account_type 除外），前端未传或缺省的默认存 0
     *
     * @param array $payload 入参
     *
     * @return array{id:int,name_internal:string,locale_configs:array,event_key:string,event_key_label:string,event_params:array,event_params_text:array<int,string>,relation:?string,cycle_limit:int,cycle_period:int,cycle_period_label:string,points:int,points_type:int,points_type_text:string,status:int,status_label:string,task_type:int,task_type_label:string,created_at:?string,updated_at:?string}
     */
    public function create(array $payload)
    {
        $payload                 = $this->mergeRelationIntoEventParams($payload);
        $payload['event_params'] = $this->fillMissingEventParams(
            $this->normalizeEventParamsForStorage($payload['event_params'] ?? []),
            (string) ($payload['event_key'] ?? '')
        );
        $this->assertTriggerIdentifierUnique($payload);
        $payload['points']       = (int) ($payload['points'] ?? 0);
        $payload['points_type']  = $this->normalizePointsType(
            $payload['points_type'] ?? PointRule::POINTS_TYPE_XP,
            PointRule::POINTS_TYPE_XP
        );
        $payload['status']       = PointRule::STATUS_PENDING;
        $payload                = $this->normalizeRuleTimingFields($payload, true);
        $localized               = $this->normalizeLocaleConfigs($payload['locale_configs'] ?? []);
        $payload['name_external']= $localized['names'];
        $payload['description']  = $localized['descriptions'];
        $payload['pop_up']       = empty($localized['pop_up']) ? null : $localized['pop_up'];
        $payload['link']         = $payload['link'] ?? null;
        unset($payload['jump_type']);
        unset($payload['locale_configs']);
        $payload['task_type']    = (int) ($payload['task_type'] ?? $payload['task'] ?? 0);
        unset($payload['task']);
        $rule = $this->repo->create($payload);

        return $this->formatRule($rule);
    }

    /**
     * 修改积分规则
     *
     * @param int   $id      规则ID
     * @param array $payload 入参
     *
     * @return array|null
     */
    public function update($id, array $payload)
    {
        $rule = $this->repo->findById($id);
        if (! $rule) {
            return null;
        }

        $payload                 = $this->mergeRelationIntoEventParams($payload);
        $payload['event_params'] = $this->fillMissingEventParams(
            $this->normalizeEventParamsForStorage($payload['event_params'] ?? []),
            (string) ($payload['event_key'] ?? '')
        );
        $this->assertTriggerIdentifierUnique($payload, $id);
        $payload['points']       = (int) ($payload['points'] ?? 0);
        $existingPointsType      = (int) ($rule->points_type ?: PointRule::POINTS_TYPE_XP);
        $payload['points_type']  = $this->normalizePointsType(
            $payload['points_type'] ?? $existingPointsType,
            $existingPointsType
        );
        $payload                = $this->normalizeRuleTimingFields($payload);
        $localized               = $this->normalizeLocaleConfigs($payload['locale_configs'] ?? []);
        $payload['name_external']= $localized['names'];
        $payload['description']  = $localized['descriptions'];
        $payload['pop_up']       = empty($localized['pop_up']) ? null : $localized['pop_up'];
        $payload['link']         = $payload['link'] ?? null;
        unset($payload['jump_type']);
        unset($payload['locale_configs']);
        $payload['task_type']    = (int) ($payload['task_type'] ?? $payload['task'] ?? 0);
        unset($payload['task']);

        if (! array_key_exists('status', $payload)) {
            $payload['status'] = $rule->status;
        }

        $updated = $this->repo->updateById($id, $payload, $rule);

        return $updated ? $this->formatRule($updated) : null;
    }

    /**
     * 修改积分规则状态
     *
     * @param int $id     规则ID
     * @param int $status 状态值（-1待启用/0禁用/1启用），停用后不可再修改为其他状态
     *
     * @return array|null
     */
    public function changeStatus($id, $status)
    {
        $rule = $this->repo->findById($id);
        if (! $rule) {
            return null;
        }

        if ($rule->status == PointRule::STATUS_DISABLED && $status !== PointRule::STATUS_DISABLED) {
            throw new \Exception(trans('point_rule.message.cannot_change_disabled'));
        }

        if ($rule->status == $status) {
            return $this->formatRule($rule);
        }

        // 启动 trigger 事件规则时校验 identifier 唯一性
        if ($status === PointRule::STATUS_ENABLED) {
            $this->assertTriggerIdentifierUnique([
                'event_key'    => $rule->event_key,
                'event_params' => $rule->event_params,
            ], $id);
        }

        $updated = $this->repo->updateById($id, ['status' => $status], $rule);

        return $updated ? $this->formatRule($updated) : null;
    }

    /**
     * 获取积分规则事件选项
     *
     * 返回字段：
     * - key：事件标识（用于 event_key 保存）
     * - name：事件说明（多语言）
     * - event_params：事件配置项模板，单项包含 is_required / is_select_required 标识
     * - relation：支持的参数关系选项
     *
     * @return array<int, array{key:string,name:string,event_params:array}>
     */
    public function getEventOptions()
    {
        if ($this->eventOptionsCache !== null) {
            return $this->eventOptionsCache;
        }

        $options = PointRuleConstant::eventOptions();

        foreach ($options as &$option) {
            $option['relation'] = $this->supportsParamRelation($option['key'] ?? '')
                ? self::EVENT_PARAM_RELATION_OPTIONS
                : [];
            $option['render_mode'] = self::EVENT_PARAM_RENDER_MODE_SELECT;
            if (($option['key'] ?? '') !== PointRule::EVENT_KEY_IB_LEVEL_UPGRADE) {
                continue;
            }

            $option['event_params'] = $this->buildIbLevelUpgradeEventParams();
            $option['render_mode'] = self::EVENT_PARAM_RENDER_MODE_MULTI;
            break;
        }
        unset($option);

        $this->eventOptionsCache = $options;

        return $this->eventOptionsCache;
    }

    /**
     * 积分规则枚举选项
     *
     * @return array{cycle_limits: array<int, array{code:int,label:string}>, cycle_periods: array<int, array{code:int,label:string}>, task_types: array<int, array{code:int,label:string}>, points_types: array<int, array{code:int,label:string}>}
     */
    public function getRuleOptions(): array
    {
        return [
            'cycle_limits' => $this->buildCycleLimitOptions(),
            'cycle_periods' => $this->buildCyclePeriodOptions(),
            'task_types'    => $this->buildTaskTypeOptions(),
            'points_types'  => $this->buildPointsTypeOptions(),
        ];
    }

    private function supportsParamRelation(string $eventKey): bool
    {
        return in_array($eventKey, self::EVENT_KEYS_SUPPORT_PARAM_RELATION, true);
    }

    private function mergeRelationIntoEventParams(array $payload): array
    {
        $eventKey = (string) ($payload['event_key'] ?? '');
        $relation = $payload['relation'] ?? null;

        if (! $this->supportsParamRelation($eventKey) || $relation === null || $relation === '') {
            unset($payload['relation']);

            return $payload;
        }

        $eventParams = $payload['event_params'] ?? [];
        if (! is_array($eventParams)) {
            $eventParams = [];
        }

        if (array_is_list($eventParams)) {
            $eventParams[] = ['relation' => $relation];
        } else {
            $eventParams['relation'] = $relation;
        }
        $payload['event_params'] = $eventParams;
        unset($payload['relation']);

        return $payload;
    }

    private function extractRelationFromEventParams(string $eventKey, array $eventParams): ?string
    {
        if (! $this->supportsParamRelation($eventKey)) {
            return null;
        }

        $relation = $eventParams['relation'] ?? null;
        if ($relation === null) {
            foreach ($eventParams as $item) {
                if (! is_array($item) || ! array_key_exists('relation', $item)) {
                    continue;
                }

                $relation = $item['relation'];
                break;
            }
        }

        if (! is_string($relation) || ! in_array($relation, self::EVENT_PARAM_RELATION_OPTIONS, true)) {
            return null;
        }

        return $relation;
    }

    private function stripRelationFromEventParams(array $eventParams): array
    {
        unset($eventParams['relation']);

        foreach ($eventParams as $index => $item) {
            if (! is_array($item) || ! array_key_exists('relation', $item)) {
                continue;
            }

            unset($eventParams[$index]['relation']);
            if ($eventParams[$index] === []) {
                unset($eventParams[$index]);
            }
        }

        return array_values($eventParams) === $eventParams ? array_values($eventParams) : $eventParams;
    }

    private function buildCycleLimitOptions(): array
    {
        $options = [
            1  => trans('point_rule.cycle_limit.unique'),
            0  => trans('point_rule.cycle_limit.loop'),
            -1 => trans('point_rule.cycle_limit.limited'),
        ];

        return array_map(
            fn ($code, $label) => ['code' => (int) $code, 'label' => $label],
            array_keys($options),
            $options
        );
    }

    private function buildCyclePeriodOptions(): array
    {
        $codes = [0, FrameworkPointRule::CYCLE_DAILY, FrameworkPointRule::CYCLE_WEEKLY, FrameworkPointRule::CYCLE_MONTHLY];

        return array_map(
            fn (int $code) => ['code' => $code, 'label' => PointRule::cyclePeriodLabel($code)],
            $codes
        );
    }

    private function buildTaskTypeOptions(): array
    {
        $options = [
            PointRule::TASK_TYPE_NONE     => trans('point_rule.task_type.0'),
            PointRule::TASK_TYPE_NEW_USER => trans('point_rule.task_type.1'),
            PointRule::TASK_TYPE_TRADE    => trans('point_rule.task_type.2'),
            PointRule::TASK_TYPE_SOCIAL   => trans('point_rule.task_type.3'),
        ];

        return array_map(
            fn ($code, $label) => ['code' => (int) $code, 'label' => $label],
            array_keys($options),
            $options
        );
    }

    private function buildPointsTypeOptions(): array
    {
        $options = [
            PointRule::POINTS_TYPE_XP      => trans('point_rule.points_type.1'),
            PointRule::POINTS_TYPE_DROPLET => trans('point_rule.points_type.2'),
        ];

        return array_map(
            fn ($code, $label) => ['code' => (int) $code, 'label' => $label],
            array_keys($options),
            $options
        );
    }

    private function normalizePointsType(mixed $pointsType, int $default): int
    {
        if ($pointsType === null || $pointsType === '') {
            return $default;
        }

        return (int) $pointsType;
    }

    /**
     * 构建单条规则展示信息
     *
     * @param array $rule 规则
     *
     * @return array
     */
    private function formatRule(PointRule|array $rule)
    {
        if ($rule instanceof PointRule) {
            $rule = $rule->toArray();
        }

        $nameTranslations        = $this->decodeLocaleField($rule['name_external'] ?? null);
        $descriptionTranslations = $this->decodeLocaleField($rule['description'] ?? null);
        $eventParams             = $this->decodeJsonField($rule['event_params'] ?? []);
        $eventKey                = $rule['event_key'] ?? '';
        $relation                = $this->extractRelationFromEventParams($eventKey, $eventParams);
        $eventParams             = $this->stripRelationFromEventParams($eventParams);
        $eventParamTexts         = $this->buildEventParamTexts($eventKey, $eventParams);

        $link     = isset($rule['link']) ? (string) $rule['link'] : null;
        $popUp    = $this->decodeJsonField($rule['pop_up'] ?? []);

        $formatted = [
            'id'                => (int) ($rule['id'] ?? 0),
            'name_internal'     => $rule['name_internal'] ?? '',
            'locale_configs'    => $this->buildLocaleConfigs($nameTranslations, $descriptionTranslations, $popUp),
            'jump_type'         => $link === null ? -1 : (preg_match('/^https?:\/\//i', $link) ? 1 : 0),
            'link'              => $link,
            'event_key'         => $eventKey,
            'relation'          => $relation,
            'event_params'      => $eventParams,
            'event_params_text' => $eventParamTexts,
            'cycle_limit'       => (int) ($rule['cycle_limit'] ?? 0),
            'cycle_period'      => (int) ($rule['cycle_period'] ?? 0),
            'cycle_period_day'  => isset($rule['cycle_period_day']) ? (int) $rule['cycle_period_day'] : null,
            'start_at'          => $rule['start_at'] ?? null,
            'end_at'            => $rule['end_at'] ?? null,
            'points'            => (int) ($rule['points'] ?? 0),
            'points_type'       => (int) ($rule['points_type'] ?? PointRule::POINTS_TYPE_XP),
            'status'            => (int) ($rule['status'] ?? PointRule::STATUS_PENDING),
            'task_type'         => (int) ($rule['task_type'] ?? $rule['task'] ?? 0),
            'created_at'        => $rule['created_at'] ?? null,
            'updated_at'        => $rule['updated_at'] ?? null,
        ];

        $formatted['event_text']        = $this->buildEventText($formatted['event_params_text'], $relation);
        $formatted['cycle_limit_label']  = PointRule::cycleLimitLabel($formatted['cycle_limit']);
        $formatted['cycle_period_label'] = PointRule::cyclePeriodLabel($formatted['cycle_period']);
        $formatted['status_label']       = PointRule::statusLabel($formatted['status']);
        $formatted['event_key_label']    = PointRule::eventKeyLabel($formatted['event_key']);
        $formatted['task_type_label']    = PointRule::taskLabel($formatted['task_type']);
        $formatted['points_type_text']   = PointRule::pointsTypeLabel($formatted['points_type']);
        $formatted['start_at']           = $this->formatDateTime($formatted['start_at']);
        $formatted['end_at']             = $this->formatDateTime($formatted['end_at']);
        $formatted['created_at']         = $this->formatDateTime($formatted['created_at']);
        $formatted['updated_at']         = $this->formatDateTime($formatted['updated_at']);

        return $formatted;
    }

    /**
     * 格式化日期时间
     *
     * @param string|\DateTimeInterface|null $value 日期
     *
     * @return string|null
     */
    private function formatDateTime($value)
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function normalizeRuleTimingFields(array $payload, bool $fillMissing = false): array
    {
        if ($fillMissing || array_key_exists('cycle_period_day', $payload)) {
            $cyclePeriodDay = (int) ($payload['cycle_period_day'] ?? 0);
            $payload['cycle_period_day'] = $cyclePeriodDay === 0 ? null : $cyclePeriodDay;
        }

        if (
            $fillMissing
            || array_key_exists('start_at', $payload)
            || array_key_exists('end_at', $payload)
        ) {
            $startAt = $payload['start_at'] ?? null;
            $endAt   = $payload['end_at'] ?? null;
            if (empty($startAt) || empty($endAt)) {
                $payload['start_at'] = null;
                $payload['end_at']   = null;
            }
        }

        return $payload;
    }

    private function normalizeLocaleConfigs(array $localeConfigs): array
    {
        $names        = [];
        $descriptions = [];
        $langs        = [];
        $popUp        = [];

        foreach ($localeConfigs as $config) {
            if (! is_array($config)) {
                continue;
            }

            $lang = $config['lang'] ?? null;
            if ($lang === null || $lang === '') {
                continue;
            }
            if (in_array($lang, $langs, true)) {
                throw new \Exception(trans('public.message.repeat_lang'));
            }
            $langs[] = $lang;

            $name = isset($config['name']) ? trim((string) $config['name']) : '';
            if ($name !== '') {
                $names[$lang] = $name;
            }

            $description = isset($config['description']) ? trim((string) $config['description']) : '';
            if ($description !== '') {
                $descriptions[$lang] = $description;
            }

            // 提取弹窗配置（按语言组合为 {"en_US":{"image":"...","text":"..."}}）
            $popUpImage = $config['pop_up_image'] ?? null;
            $popUpText  = $config['pop_up_text'] ?? null;
            if ($popUpImage !== null && $popUpImage !== '') {
                $popUpImage = (string) $popUpImage;
                // HTTP 地址则提取 path 部分，非 HTTP 地址直接存储
                if (preg_match('/^https?:\/\//i', $popUpImage)) {
                    $parsed = parse_url($popUpImage);
                    $popUpImage = isset($parsed['path']) ? ltrim($parsed['path'], '/') : '';
                }
            }
            if (($popUpImage !== null && $popUpImage !== '') || ($popUpText !== null && $popUpText !== '')) {
                $popUp[$lang] = [
                    'image' => (string) ($popUpImage ?? ''),
                    'text'  => (string) ($popUpText ?? ''),
                ];
            }
        }

        if (empty($names)) {
            throw new \Exception(trans('public.message.Incomplete'));
        }

        return [
            'names'        => $names,
            'descriptions' => $descriptions,
            'pop_up'       => $popUp,
        ];
    }

    private function decodeLocaleField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $fallback = config('app.fallback_locale', 'en_US');

        return [$fallback => (string) $value];
    }

    private function resolveLocaleValue(array $translations): string
    {
        if (empty($translations)) {
            return '';
        }

        $currentLocale = app()->getLocale() ?: config('app.locale');
        if ($currentLocale && isset($translations[$currentLocale]) && $translations[$currentLocale] !== '') {
            return (string) $translations[$currentLocale];
        }

        $fallback = config('app.fallback_locale', 'en_US');
        if (isset($translations[$fallback]) && $translations[$fallback] !== '') {
            return (string) $translations[$fallback];
        }

        $first = reset($translations);

        return is_string($first) ? $first : '';
    }

    private function buildLocaleConfigs(array $names, array $descriptions, array $popUp = []): array
    {
        $languages = array_unique(array_merge(array_keys($names), array_keys($descriptions)));
        if (empty($languages)) {
            return [];
        }

        $ordered = $this->orderLanguages($languages);
        $result  = [];
        foreach ($ordered as $lang) {
            $result[] = [
                'lang'         => $lang,
                'name'         => $names[$lang] ?? '',
                'description'  => $descriptions[$lang] ?? '',
                'pop_up_image' => ! empty($popUp[$lang]['image'] ?? null)
                    ? Email::s3ImageAddress() . '/' . $popUp[$lang]['image']
                    : '',
                'pop_up_text'  => $popUp[$lang]['text'] ?? '',
            ];
        }

        return $result;
    }

    private function orderLanguages(array $languages): array
    {
        $configured = array_keys(config('lang.languages', []));
        $ordered    = [];

        foreach ($configured as $lang) {
            if (in_array($lang, $languages, true)) {
                $ordered[] = $lang;
            }
        }

        foreach ($languages as $lang) {
            if (! in_array($lang, $ordered, true)) {
                $ordered[] = $lang;
            }
        }

        return $ordered;
    }

    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * 入库时补齐事件定义的全部参数键（trade_account_type 除外），
     * 前端未传或缺省的默认存 0。
     *
     * @param  array<string, mixed> $eventParams
     * @return array<string, mixed>
     */
    private function fillMissingEventParams(array $eventParams, string $eventKey): array
    {
        foreach (PointRuleConstant::eventParamDefaults($eventKey) as $paramKey => $default) {
            if (! array_key_exists($paramKey, $eventParams) || $this->isBlankValue($eventParams[$paramKey])) {
                $eventParams[$paramKey] = $default;
            }
        }

        return $eventParams;
    }

    private function isBlankValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || trim((string) $value) === '';
    }

    /**
     * 将前端事件参数归一化为入库所需的 key-value 结构。
     *
     * 支持以下输入：
     * - {"1":100,"2":200}
     * - [{"param_key":"1","param_value":"100"},{"param_key":"2","param_value":"200"}]
     * - [{"1":"100"},{"2":"200"}]
     * - {"count":"2","position_size":"3333","relation":"or"}
     */
    private function normalizeEventParamsForStorage(array $params): array
    {
        if ($params === []) {
            return [];
        }

        $normalized = [];
        $items = array_is_list($params) ? $params : [$params];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_key_exists('param_key', $item)) {
                $key = trim((string) ($item['param_key'] ?? ''));
                if ($key !== '') {
                    $normalized[$key] = $this->normalizeStoredEventParamValue($item['param_value'] ?? null);
                }
                continue;
            }

            foreach ($item as $key => $value) {
                $key = trim((string) $key);
                if ($key === '' || in_array($key, ['type', 'param_name', 'description', 'text'], true)) {
                    continue;
                }

                $normalized[$key] = $this->normalizeStoredEventParamValue($value);
            }
        }

        return $normalized;
    }

    private function normalizeStoredEventParamValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return $value;
        }

        return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
    }

    /**
     * @return array<int, string>
     */
    private function buildEventParamTexts(string $eventKey, array $eventParams): array
    {
        if ($eventKey === '' || empty($eventParams)) {
            return [];
        }

        $definitions = $this->resolveEventParamDefinitions($eventKey);
        if ($definitions === []) {
            return [];
        }

        $texts = [];
        foreach ($eventParams as $key => $value) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }

            $definition = $definitions[$key] ?? null;
            if (! is_array($definition)) {
                continue;
            }

            $text = $this->formatEventParamText($definition, $value);
            if ($text !== null) {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * 将事件参数拼成可读文案
     */
    private function buildEventText(array $eventParamTexts, ?string $relation = null): string
    {
        if (empty($eventParamTexts)) {
            return '';
        }

        $eventParamTexts = array_values(array_filter($eventParamTexts, static fn ($text) => is_string($text) && $text !== ''));
        if ($eventParamTexts === []) {
            return '';
        }

        if ($relation !== null && $relation !== '') {
            return implode(" {$relation} ", $eventParamTexts);
        }

        return implode('，', $eventParamTexts);
    }

    /**
     * @return array<string, array{param_key:string,param_name:string,type:int,options?:array<int, array{value:int,label:string}>}>
     */
    private function resolveEventParamDefinitions(string $eventKey): array
    {
        $options = $this->getEventOptions();
        foreach ($options as $option) {
            if (($option['key'] ?? '') !== $eventKey) {
                continue;
            }

            $definitions = [];
            foreach ($option['event_params'] ?? [] as $param) {
                if (! is_array($param)) {
                    continue;
                }

                $paramKey = (string) ($param['param_key'] ?? '');
                if ($paramKey === '') {
                    continue;
                }

                $definitions[$paramKey] = [
                    'param_key'  => $paramKey,
                    'param_name' => (string) ($param['param_name'] ?? ''),
                    'type'       => (int) ($param['type'] ?? 0),
                    'options'    => is_array($param['options'] ?? null) ? $param['options'] : [],
                ];
            }

            return $definitions;
        }

        return [];
    }

    private function formatEventParamText(array $definition, mixed $value): ?string
    {
        $label = trim((string) ($definition['param_name'] ?? ''));
        $displayValue = $this->resolveEventParamDisplayValue($definition, $value);

        if ($label === '' || $displayValue === null || $displayValue === '') {
            return null;
        }

        $label = $this->stripLeadingComparator($label);
        // 选择类型参数与 trigger.identifier 使用冒号拼接，避免受当前语言文案影响。
        if ((int) ($definition['type'] ?? 0) === PointRuleConstant::PARAM_TYPE_SELECT || ($definition['param_key'] ?? '') === 'identifier') {
            return sprintf('%s:%s', $label, $displayValue);
        }

        return sprintf('%s >= %s', $label, $displayValue);
    }

    private function resolveEventParamDisplayValue(array $definition, mixed $value): mixed
    {
        $options = is_array($definition['options'] ?? null) ? $definition['options'] : [];
        if ($options !== []) {
            foreach ($options as $option) {
                if (! is_array($option) || ! array_key_exists('value', $option)) {
                    continue;
                }

                if ((string) $option['value'] === (string) $value) {
                    return $option['label'] ?? $value;
                }
            }
        }

        return is_array($value) ? ($value['value'] ?? $value['amount'] ?? $value['days'] ?? null) : $value;
    }

    private function stripLeadingComparator(string $label): string
    {
        $label = preg_replace('/^(?:[≥>]=?|=)\s*/u', '', $label);

        return trim((string) $label);
    }

    /**
     * @return array<int, array{type:string,param_key:string,param_name:string}>
     */
    private function buildIbLevelUpgradeEventParams(): array
    {
        $levels = $this->ibLevelRepo->getEnabledLevelParams();
        if (empty($levels)) {
            return [];
        }

        $params = [];
        foreach ($levels as $level) {
            $levelPos = (int) ($level['level_pos'] ?? 0);
            if ($levelPos <= 0) {
                continue;
            }

            $params[] = [
                'type' => (string) PointRuleConstant::PARAM_TYPE_TEXT,
                'param_key' => (string) $levelPos,
                'param_name' => $this->resolveLocaleValue($this->decodeLocaleField($level['name'] ?? null)),
            ];
        }

        if (empty($params)) {
            return [];
        }

        return array_values($params);
    }

    /**
     * 从 payload 的 event_params 中提取 trigger 事件的 identifier 值
     *
     * @param array $payload 请求数据（已 normalizeEventParamsForStorage 处理）
     * @return string|null
     */
    private function extractTriggerIdentifier(array $payload): ?string
    {
        $eventKey = (string) ($payload['event_key'] ?? '');
        if ($eventKey !== 'trigger') {
            return null;
        }

        $eventParams = $payload['event_params'] ?? [];
        if (! is_array($eventParams)) {
            return null;
        }

        $identifier = $eventParams['identifier'] ?? null;
        if ($identifier === null || $identifier === '') {
            return null;
        }

        return (string) $identifier;
    }

    /**
     * 校验 trigger 事件的 identifier 在已启动规则中的唯一性
     *
     * @param array   $payload       请求数据（已 normalizeEventParamsForStorage 处理）
     * @param int|null $excludeRuleId 排除的规则ID（更新时排除自身）
     * @throws \Illuminate\Validation\ValidationException
     */
    private function assertTriggerIdentifierUnique(array $payload, ?int $excludeRuleId = null): void
    {
        $identifier = $this->extractTriggerIdentifier($payload);
        if ($identifier === null) {
            return;
        }

        if ($this->repo->existsActiveTriggerIdentifier($identifier, $excludeRuleId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'event_params' => [trans('point_rule.message.duplicate_trigger_identifier')],
            ]);
        }
    }
}
