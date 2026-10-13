<?php

namespace App\Repositories\Api;
use Scaffold\Framework\Repositories\Repository;
use App\Models\Api\TemplateNotice;

class TemplateNoticeRepository extends Repository
{
    public function __construct(
        TemplateNotice $model
    ) {
        parent::__construct($model);
    }

    /**
     * 消息模版列表
     *
     * @param int $type 类型
     * @param string $eventKey 触发事件关键字
     * @param string $lang 语言关键字
     * @param string $title 标题
     * @param string $sort
     * @param string $order
     * @param int $limit
     * @param int $offset
     *
     * @return array
     */
    public function list($type, $eventKey, $lang, $title, $sort, $order, $limit, $offset)
    {
        $query = $this->getQuery()->from('template_notice as tn')
            ->leftJoin('template_layout as tl', 'tl.id', '=', 'tn.layout_id');
        if (! empty($type)) {
            $query->where('tn.type', $type);
        }
        if (! empty($eventKey)) {
            $query->where('tn.event_key', $eventKey);
        }
        if (! empty($lang)) {
            $query->where('tn.lang', $lang);
        }
        if (! empty($title)) {
            $query->where('tn.title', 'like', '%' . $title . '%');
        }
        $count = $query->count();
        $data  = $query->select('tn.*', 'tl.name as layout_name')
            ->orderBy($sort, $order)->offset($offset)->limit($limit)->get()->toArray();
        return [$data, $count];
    }

    /**
     * 获取一条模版信息
     *
     * @param int $id 模版ID
     * @param int $layout_id layout模版ID
     * @param int $type 类型
     * @param string $eventKey 触发事件关键字
     * @param string $lang 语言关键字
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getFirst($id, $layout_id, $type, $eventKey, $lang)
    {
        $query = $this->getQuery();
        if (! empty($id)) {
            $query->where('id', $id);
        }
        if (! empty($layout_id)) {
            $query->where('layout_id', $layout_id);
        }
        if (! empty($type)) {
            $query->where('type', $type);
        }
        if (! empty($eventKey)) {
            $query->where('event_key', $eventKey);
        }
        if (! empty($lang)) {
            $query->where('lang', $lang);
        }
        return $query->first();
    }
}
