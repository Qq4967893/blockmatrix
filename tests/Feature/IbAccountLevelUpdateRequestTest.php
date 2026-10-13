<?php

namespace Tests\Feature;

use App\Http\Requests\IbAccount\IbAccountLevelUpdateRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Redirector;
use Tests\TestCase;

class IbAccountLevelUpdateRequestTest extends TestCase
{
    public function test_accepts_single_ib_id_and_normalizes_blank_protection_times(): void
    {
        $request = $this->makeRequest([
            'ib_id' => '000001',
            'level_id' => 2,
            'level_protection_started_at' => '',
            'level_protection_ended_at' => '',
        ]);

        $request->validateResolved();

        $this->assertNull($request->input('level_protection_started_at'));
        $this->assertNull($request->input('level_protection_ended_at'));
    }

    public function test_accepts_missing_protection_times_and_keeps_them_null(): void
    {
        $request = $this->makeRequest([
            'ib_id' => '000001',
            'level_id' => 2,
        ]);

        $request->validateResolved();

        $this->assertNull($request->input('level_protection_started_at'));
        $this->assertNull($request->input('level_protection_ended_at'));
    }

    public function test_accepts_batch_ib_ids(): void
    {
        $request = $this->makeRequest([
            'ib_ids' => ['000001', 2],
            'level_id' => 2,
        ]);

        $request->validateResolved();

        $this->assertSame(['000001', 2], $request->validated('ib_ids'));
    }

    public function test_requires_single_or_batch_ib_ids(): void
    {
        $request = $this->makeRequest([
            'level_id' => 2,
        ]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    public function test_rejects_single_and_batch_ib_ids_together(): void
    {
        $request = $this->makeRequest([
            'ib_id' => '000001',
            'ib_ids' => ['000002'],
            'level_id' => 2,
        ]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    private function makeRequest(array $payload): IbAccountLevelUpdateRequest
    {
        $request = IbAccountLevelUpdateRequest::create('/api/ib_accounts/level', 'PUT', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        return $request;
    }
}
