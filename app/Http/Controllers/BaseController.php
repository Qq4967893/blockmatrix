<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Context;
use Scaffold\Framework\Traits\Response;

class BaseController extends Controller
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;
    use Response;

    protected function ensureExportWithinLimit(int $totalCount, int $limit = 3000): ?JsonResponse
    {
        if ($totalCount > $limit) {
            return $this->fail(trans('public.message.export_limit_exceeded', ['limit' => $limit]));
        }

        return null;
    }

    /**
     * 失败响应（带数据）
     */
    public function failWithData($data = [], string $message = "", int $code = 0, int $status = 200, array $headers = []): JsonResponse
    {
        $ret = [
            "is_succ"    => false,
            "message"    => $message,
            "code"       => $code,
            'request_id' => Context::get('request-id', ''),
        ];
        if (! empty($data)) {
            $ret['data'] = $data;
        }
        return response()->json($ret, $status, $headers);
    }
}
