<?php

namespace Tests\Feature;

use App\Http\Requests\Badge\BadgeStoreRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Redirector;
use Tests\TestCase;

class BadgeRequestTest extends TestCase
{
    public function test_close_trade_accepts_required_param_and_relation(): void
    {
        $request = $this->makeRequest([
            'event_key' => 'close_trade',
            'event_params' => [
                'trade_account_type' => 1,
                'lots' => 2,
            ],
            'relation' => 'and',
        ]);

        $request->validateResolved();

        $this->assertSame('and', $request->validated('relation'));
    }

    public function test_close_trade_requires_relation(): void
    {
        $request = $this->makeRequest([
            'event_key' => 'close_trade',
            'event_params' => ['trade_account_type' => 1],
        ]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    public function test_close_trade_requires_trade_account_type(): void
    {
        $request = $this->makeRequest([
            'event_key' => 'close_trade',
            'event_params' => ['lots' => 2],
            'relation' => 'or',
        ]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    public function test_close_trade_rejects_unknown_trade_account_type(): void
    {
        $request = $this->makeRequest([
            'event_key' => 'close_trade',
            'event_params' => ['trade_account_type' => 999],
            'relation' => 'or',
        ]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    public function test_deposited_requires_amount(): void
    {
        $request = $this->makeRequest([
            'event_key' => 'deposited',
            'event_params' => [],
        ]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    private function makeRequest(array $overrides): BadgeStoreRequest
    {
        $payload = array_replace_recursive([
            'icon' => 'badges/default.png',
            'short_name' => 'badge',
            'event_key' => 'registered',
            'event_params' => [],
            'locale_configs' => [
                [
                    'lang' => 'en_US',
                    'name' => 'Badge',
                    'description' => 'Badge description',
                ],
            ],
        ], $overrides);

        $request = BadgeStoreRequest::create('/api/badges', 'POST', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        return $request;
    }
}
