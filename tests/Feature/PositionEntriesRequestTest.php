<?php

namespace Tests\Feature;

use App\Http\Requests\Entries\PositionEntriesRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Redirector;
use Tests\TestCase;

class PositionEntriesRequestTest extends TestCase
{
    public function test_ad_record_requires_identifier_instead_of_entry_id(): void
    {
        $request = $this->makeRequest([
            'identifier' => null,
            'entry_id' => 'home_banner',
        ]);

        try {
            $request->validateResolved();
            $this->fail('Expected validation to fail when identifier is missing.');
        } catch (HttpResponseException $exception) {
            $payload = $exception->getResponse()->getData(true);

            $this->assertSame(trans('ad.position.validation.identifier_required'), $payload['message']);
        }
    }

    public function test_ad_record_identifier_must_use_supported_format(): void
    {
        $request = $this->makeRequest([
            'identifier' => 'HomeBanner',
        ]);

        try {
            $request->validateResolved();
            $this->fail('Expected validation to fail for invalid identifier format.');
        } catch (HttpResponseException $exception) {
            $payload = $exception->getResponse()->getData(true);

            $this->assertSame(trans('ad.position.validation.identifier_regex'), $payload['message']);
        }
    }

    public function test_ad_record_accepts_identifier_payload(): void
    {
        $request = $this->makeRequest([
            'identifier' => 'home_banner',
        ]);

        $request->validateResolved();

        $this->assertSame('home_banner', $request->input('identifier'));
    }

    public function test_ad_record_accepts_pending_status(): void
    {
        $request = $this->makeRequest([
            'status' => 2,
        ]);

        $request->validateResolved();

        $this->assertSame(2, $request->input('status'));
    }

    private function makeRequest(array $overrides): PositionEntriesRequest
    {
        $payload = array_replace_recursive([
            'identifier' => 'home_banner',
            'platform' => ['ios'],
            'name' => 'Home banner',
            'description' => 'Home banner description',
            'locale_configs' => [
                [
                    'platform' => 'ios',
                    'lang' => 'en_US',
                    'img_url' => 'https://example.com/banner.png',
                    'link' => 'https://example.com',
                ],
            ],
            'start_time' => '2026-07-24 00:00:00',
            'end_time' => '2026-07-25 00:00:00',
            'status' => 1,
        ], $overrides);

        $request = PositionEntriesRequest::create('/api/position_record/add', 'POST', $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        return $request;
    }
}
