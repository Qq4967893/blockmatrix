<?php

namespace App\Repositories\Api;

use App\Models\Api\PointRule;
use Illuminate\Database\Eloquent\Builder;
use Scaffold\Framework\Repositories\Repository;

class PointRuleRepository extends Repository
{
    public function __construct(PointRule $model)
    {
        parent::__construct($model);
    }

    /**
     * 创建积分规则
     *
     * @param array $data 数据
     *
     * @return PointRule
     */
    public function create(array $data)
    {
        return $this->getQuery()->create($data);
    }

    /**
     * 根据ID获取积分规则
     *
     * @param int $id 规则ID
     *
     * @return PointRule|null
     */
    public function findById($id)
    {
        return $this->getQuery()->find($id);
    }

    /**
     * 更新积分规则
     *
     * @param int            $id   规则ID
     * @param array          $data 数据
     * @param PointRule|null $rule 规则实例
     *
     * @return PointRule|null
     */
    public function updateById($id, array $data, ?PointRule $rule = null)
    {
        $rule = $rule ?: $this->findById($id);
        if (! $rule) {
            return null;
        }

        $rule->fill($data);
        $rule->save();

        return $rule;
    }

    /**
     * 获取积分规则列表
     *
     * @param string   $keyword 关键词（内部/同名，模糊匹配）
     * @param int|null $status  状态：-1待启用 0禁用 1启用 null全部
     * @param int      $limit   每页条数
     * @param int      $offset  偏移量
     *
     * @return array
     */
    public function getList($keyword, $status, $limit, $offset)
    {
        $query = $this->getQuery();

        if ($keyword !== '') {
            $like = $this->like($keyword);
            $this->appendKeywordFilter($query, $like);
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $count = $query->count();
        $data  = $query->orderByDesc('id')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();

        return [$data, $count];
    }

    private function appendKeywordFilter(Builder $query, string $like): void
    {
        $query->where(function (Builder $subQuery) use ($like) {
            $subQuery->where('name_internal', 'like', $like);

            $langs = array_keys(config('lang.languages', []));
            if (empty($langs)) {
                $defaultLang = config('app.locale', 'en_US');
                $subQuery->orWhere("name_external->$defaultLang", 'like', $like);
                return;
            }

            foreach ($langs as $lang) {
                $subQuery->orWhere("name_external->$lang", 'like', $like);
            }
        });
    }

    /**
     * 检查已启动的 trigger 事件规则中是否存在相同的唯一标识
     *
     * @param  string   $identifier  唯一标识值
     * @param  int|null $excludeRuleId 排除的规则ID（更新时排除自身）
     * @return bool
     */
    public function existsActiveTriggerIdentifier(string $identifier, ?int $excludeRuleId = null): bool
    {
        $query = $this->getQuery()
            ->where('event_key', 'trigger')
            ->where('status', PointRule::STATUS_ENABLED);

        if ($excludeRuleId !== null) {
            $query->where('id', '!=', $excludeRuleId);
        }

        $rules = $query->get(['event_params']);

        foreach ($rules as $rule) {
            $params = is_array($rule->event_params)
                ? $rule->event_params
                : json_decode((string) $rule->event_params, true) ?? [];

            $existingIdentifier = $params['identifier'] ?? null;
            if ($existingIdentifier !== null && (string) $existingIdentifier === $identifier) {
                return true;
            }
        }

        return false;
    }

    private function like(string $value): string
    {
        $escaped = addcslashes($value, '%_');

        return sprintf('%%%s%%', $escaped);
    }
}
