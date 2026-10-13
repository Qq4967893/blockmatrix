<?php

namespace App\Services\Api;

use App\Repositories\Api\PositionTabRepository;
use Illuminate\Support\Facades\Auth;

class PositionTabService
{
    public function __construct(
       private PositionTabRepository $repo
    ) {
    }

    /**
     * 获取条目内标签列表
     *
     * @param int    $entryId 条目id
     * @param string $sort    排序字段
     * @param string $order   排序方式(desc:降序, asc:升序)
     *
     * @return array
     */
    public function tabList($entryId, $sort, $order)
    {
        return $this->repo->tList($entryId, $sort, $order);
    }

    /**
     * 添加标签
     *
     * @param string $desc    描述
     * @param int    $entryId 条目id
     *
     * @return bool
     */
    public function addTab($desc, $entryId)
    {
        return $this->repo->insert(['desc' => $desc, 'editor_id' => Auth::id(), 'entry_id' => $entryId]);
    }

    /**
     * 修改标签
     *
     * @param string $desc  描述
     * @param int    $tabId 标签id
     *
     * @return bool
     */
    public function updateTab($desc, $tabId)
    {
        return $this->repo->update(['desc' => $desc], $tabId);
    }

    /**
     * 删除标签
     *
     * @param int $tabId 标签id
     *
     * @return int
     */
    public function deleteTab($tabId)
    {
        return $this->repo->delete($tabId);
    }
}
