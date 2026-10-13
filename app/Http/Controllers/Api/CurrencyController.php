<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Currency\CurrencyListRequest;
use App\Http\Requests\Currency\CurrencyStoreRequest;
use App\Http\Requests\Currency\CurrencyUpdateRequest;
use App\Services\Api\CurrencyService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;

#[Group('Currency', '币种点差配置')]
class CurrencyController extends BaseController
{
    public function __construct(private CurrencyService $service)
    {
    }

    #[QueryParameter(name: 'currency', description: '币种（大写）', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: '币种点差列表',
        type: 'array{data: array<int, array{id:int,currency:string,rate:float,spread_deposit:float,spread_withdraw:float,created_at:?string,updated_at:?string,created_by:string,created_time:?string}>, total_count:int}'
    )]
    /**
     * 获取币种点差列表
     *
     * @param CurrencyListRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(CurrencyListRequest $request)
    {
        $limit  = (int) $request->input('limit', 20);
        $offset = (int) $request->input('offset', 0);
        $filters = [
            'currency' => $request->input('currency'),
        ];

        $data = $this->service->list($filters, $limit, $offset);

        return $this->succeed($data);
    }

    #[Response(description: '新增币种点差', type: 'array{id:int}')]
    /**
     * 新增币种点差
     *
     * @param CurrencyStoreRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CurrencyStoreRequest $request)
    {
        $id = $this->service->create($request->only([
            'currency',
            'spread_deposit',
            'spread_withdraw',
        ]));

        return $this->succeed(['id' => $id]);
    }

    #[Response(description: '更新币种点差', type: 'array{updated:bool}')]
    /**
     * 修改币种点差
     *
     * @param CurrencyUpdateRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CurrencyUpdateRequest $request)
    {
        $updated = $this->service->update($request->only([
            'id',
            'currency',
            'spread_deposit',
            'spread_withdraw',
        ]));

        return $this->succeed(['updated' => $updated]);
    }
}
