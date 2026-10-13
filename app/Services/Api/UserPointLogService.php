<?php

namespace App\Services\Api;

use App\Repositories\Api\UserPointLogRepository;
use Carbon\Carbon;

class UserPointLogService
{
    public function __construct(private UserPointLogRepository $repo)
    {
    }

    /**
     * 获取积分获取明细
     *
     * @param int|null    $userCode 用户编码
     * @param string|null $startAt  开始时间
     * @param string|null $endAt    结束时间
     * @param int         $limit    每页条数
     * @param int         $offset   偏移量
     *
     * @return array{data: array<int, array{id:int,user_code:int,source:string,event_key:?string,remark:?string,points:int,points_display:string,created_at:string}>, total_count:int}
     */
    public function getList($userCode, $startAt, $endAt, $limit, $offset)
    {
        [$startAt, $endAt] = $this->normalizeDateRange($startAt, $endAt);

        [$data, $count] = $this->repo->getList($userCode, $startAt, $endAt, $limit, $offset);

        $data = array_map(function ($item) {
            $source = $item['remark'] ?? '';
            $points = (int) $item['points'];

            return [
                'id'              => (int) $item['id'],
                'user_code'       => (int) $item['user_code'],
                'source'          => $source,
                'event_key'       => $item['event_key'] ?? null,
                'remark'          => $item['remark'] ?? null,
                'points'          => $points,
                'points_display'  => sprintf('%+d PTS', $points),
                'created_at'      => $this->formatDateTime($item['created_at']),
            ];
        }, $data);

        return [
            'data'        => $data,
            'total_count' => $count,
        ];
    }

    private function normalizeDateRange($startAt, $endAt): array
    {
        $start = $this->parseDate($startAt, true);
        $end   = $this->parseDate($endAt, false);

        if ($start && $end && $start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    private function parseDate($value, $isStart)
    {
        if (empty($value)) {
            return null;
        }

        try {
            $dt = Carbon::parse($value);

            return $isStart ? $dt->startOfDay()->toDateTimeString() : $dt->endOfDay()->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatDateTime($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}
