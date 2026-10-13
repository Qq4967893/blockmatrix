<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Entries\AddEntriesRequest;
use App\Http\Requests\Entries\UpdateEntriesRequest;
use App\Services\Api\PositionEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionEntryController extends BaseController
{
    private const STATUS_DISABLED = 0;

    private const STATUS_ENABLED = 1;

    public function __construct(
        private PositionEntryService $pEntryService,
    ) {}

    /**
     * 获取广告位列表
     *
     * @param  Request  $request  request
     * @return JsonResponse
     */
    public function entryList(Request $request)
    {
        /** 排序字段 */
        $sort = $request->input('sort', 'id');
        /** 排序方式 */
        $order = $request->input('order', 'desc');
        $status = $this->normalizeStatus($request->input('status'));

        $entryArr = $this->pEntryService->entryList(
            $sort,
            $order,
            $status
        );

        return $this->succeed($entryArr);
    }

    /**
     * 添加广告位
     *
     * @param  AddEntriesRequest  $request  request
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function addEntry(AddEntriesRequest $request)
    {
        $description = $request->input('description');
        $name = $request->input('name');
        $identifier = $request->input('identifier');
        $status = $this->normalizeStatus($request->input('status'), false);

        // 检测identifier的唯一性
        $identifierOnly = $this->pEntryService->identifierUnique($identifier);
        if ($identifierOnly) {
            return $this->fail(trans('public.position.identifierOnly'));
        }
        $bool = $this->pEntryService->addEntry($description, $name, $identifier, $status);
        if ($bool) {
            return $this->succeed([], trans('public.message.add_success'));
        } else {
            return $this->fail(trans('public.message.add_error'));
        }
    }

    /**
     * 修改广告位
     *
     * @param  UpdateEntriesRequest  $request  request
     * @return JsonResponse
     */
    public function updateEntry(UpdateEntriesRequest $request)
    {
        $entryId = $request->input('entry_id', '');
        $description = $request->input('description');
        $name = $request->input('name');
        $identifier = $request->input('identifier');
        $status = $request->has('status')
            ? $this->normalizeStatus($request->input('status'), false)
            : null;

        $identifierOnly = $this->pEntryService->identifierUnique($identifier, (int) $entryId);
        if ($identifierOnly) {
            return $this->fail(trans('public.position.identifierOnly'));
        }

        $bool = $this->pEntryService->updateEntry($entryId, $description, $name, $identifier, $status);
        if ($bool) {
            return $this->succeed([], trans('public.message.upd_success'));
        } else {
            return $this->fail(trans('public.message.upd_error'));
        }
    }

    /**
     * 删除广告位
     *
     * @param  Request  $request  request
     * @return JsonResponse
     */
    public function deleteEntry(Request $request)
    {
        /** 广告位id */
        $entryId = $request->input('entry_id', '');
        if (empty($entryId)) {
            return $this->fail(trans('public.message.id_empty'));
        }

        $this->pEntryService->deleteEntry($entryId);

        return $this->succeed();
    }

    private function normalizeStatus(mixed $status, bool $nullable = true): ?int
    {
        if (is_numeric($status)) {
            $status = (int) $status;

            return in_array($status, [self::STATUS_DISABLED, self::STATUS_ENABLED], true) ? $status : null;
        }

        if (is_string($status) && $status !== '') {
            $decoded = json_decode($status, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $status = is_array($decoded) ? ($decoded[0] ?? null) : $decoded;
            }

            if (is_numeric($status)) {
                $status = (int) $status;

                return in_array($status, [self::STATUS_DISABLED, self::STATUS_ENABLED], true) ? $status : null;
            }
        }

        if (is_array($status)) {
            $first = array_values(array_filter($status, function ($value) {
                return $value !== null && $value !== '';
            }));
            $candidate = $first[0] ?? null;
            if (is_numeric($candidate)) {
                $candidate = (int) $candidate;

                return in_array($candidate, [self::STATUS_DISABLED, self::STATUS_ENABLED], true) ? $candidate : null;
            }
        }

        return $nullable ? null : self::STATUS_ENABLED;
    }
}
