<?php

namespace Tests\Feature;

use App\Http\Requests\AdminEvent\AdminEventStoreRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Redirector;
use Tests\TestCase;

class AdminEventRequestTest extends TestCase
{
    public function test_accepts_user_code_and_amount(): void
    {
        $request = $this->makeRequest([
            'user_code' => 587239582,
            'amount' => 5000,
        ]);

        $request->validateResolved();

        $this->assertSame(587239582, $request->validated('user_code'));
        $this->assertSame(5000, $request->validated('amount'));
    }

    public function test_rejects_missing_user_code(): void
    {
        $request = $this->makeRequest([]);

        $request->replace([]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    public function test_rejects_missing_amount(): void
    {
        $request = $this->makeRequest([
            'user_code' => 587239582,
        ]);

        $request->replace(['user_code' => 587239582]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    public function test_rejects_negative_amount(): void
    {
        $request = $this->makeRequest([
            'user_code' => 587239582,
            'amount' => -1,
        ]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    public function test_accepts_zero_amount(): void
    {
        $request = $this->makeRequest([
            'user_code' => 587239582,
            'amount' => 0,
        ]);

        $request->validateResolved();

        $this->assertSame(0, $request->validated('amount'));
    }

    private function makeRequest(array $overrides): AdminEventStoreRequest
    {
        $payload = array_replace_recursive([
            'user_code' => 587239582,
            'amount' => 5000,
        ], $overrides);

        $request = AdminEventStoreRequest::create('/api/open_bonus_fund', 'POST', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        return $request;
    }
}
