<?php

namespace Tests\Feature;

use App\Http\Requests\IbAccount\IbAccountSelfCommissionUpdateRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Redirector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IbAccountSelfCommissionUpdateRequestTest extends TestCase
{
    #[DataProvider('provideEnabledValues')]
    public function test_accepts_self_commission_enabled_values(int $isEnabled): void
    {
        $request = $this->makeRequest([
            'is_self_commission_enabled' => $isEnabled,
        ]);

        $request->validateResolved();

        $this->assertSame($isEnabled, (int) $request->input('is_self_commission_enabled'));
    }

    public function test_rejects_invalid_self_commission_enabled_value(): void
    {
        $request = $this->makeRequest([
            'is_self_commission_enabled' => 2,
        ]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    public function test_requires_self_commission_enabled_value(): void
    {
        $request = $this->makeRequest([]);

        $this->expectException(HttpResponseException::class);

        $request->validateResolved();
    }

    /**
     * @return array<int, array{int}>
     */
    public static function provideEnabledValues(): array
    {
        return [
            [0],
            [1],
        ];
    }

    private function makeRequest(array $payload): IbAccountSelfCommissionUpdateRequest
    {
        $request = IbAccountSelfCommissionUpdateRequest::create(
            '/api/ib_accounts/000001/self_commission',
            'PUT',
            $payload
        );
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        return $request;
    }
}
