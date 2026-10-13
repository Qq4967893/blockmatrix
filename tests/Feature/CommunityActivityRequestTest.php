<?php

namespace Tests\Feature;

use App\Http\Requests\CommunityActivity\CommunityActivityStoreRequest;
use App\Http\Requests\CommunityActivity\CommunityActivityUpdateRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Redirector;
use Tests\TestCase;

class CommunityActivityRequestTest extends TestCase
{
    public function test_activity_accepts_locale_configs_and_json_payloads(): void
    {
        $payload = $this->payload();
        $payload['locale_configs'] = json_encode($payload['locale_configs'], JSON_THROW_ON_ERROR);
        $payload['audience'] = json_encode($payload['audience'], JSON_THROW_ON_ERROR);
        $request = $this->makeRequest($payload);

        $request->validateResolved();

        $this->assertSame('en_US', $request->validated('locale_configs.0.lang'));
        $this->assertSame('London Web3 Trading Meetup', $request->validated('locale_configs.0.name'));
        $this->assertSame(['web3_users', 'local_trading_community'], $request->validated('audience'));
        $this->assertArrayNotHasKey('name', $request->validated());
    }

    public function test_activity_requires_en_us_locale_config(): void
    {
        $payload = $this->payload();
        $payload['locale_configs'][0]['lang'] = 'zh_CN';

        $this->assertValidationMessage(
            $this->makeRequest($payload),
            trans('public.message.default_lang')
        );
    }

    public function test_activity_rejects_duplicate_locale_configs(): void
    {
        $payload = $this->payload();
        $payload['locale_configs'][] = $payload['locale_configs'][0];

        $this->assertValidationMessage(
            $this->makeRequest($payload),
            trans('public.message.repeat_lang')
        );
    }

    public function test_activity_create_and_update_strip_image_domains_before_validation(): void
    {
        foreach ([
            CommunityActivityStoreRequest::class => 'POST',
            CommunityActivityUpdateRequest::class => 'PUT',
        ] as $requestClass => $method) {
            $request = $this->makeRequest($this->payload(), $requestClass, $method);

            $request->validateResolved();

            $this->assertSame(
                'community_activity/20260820/cover-en.jpg',
                $request->validated('locale_configs.0.cover_image')
            );
            $this->assertSame(
                'community_activity/20260820/background-en.jpg',
                $request->validated('locale_configs.0.bg_image')
            );
        }
    }

    public function test_activity_rejects_invalid_image_key_after_stripping_domain(): void
    {
        $payload = $this->payload();
        $payload['locale_configs'][0]['cover_image'] = 'https://static.example.com/invalid/cover.jpg';

        $this->assertValidationMessage(
            $this->makeRequest($payload),
            trans('public.message.type_error')
        );
    }

    public function test_activity_requires_apply_start_at(): void
    {
        $payload = $this->payload();
        unset($payload['apply_start_at']);

        $this->assertValidationMessage(
            $this->makeRequest($payload),
            trans('public.message.in_complete')
        );
    }

    public function test_activity_accepts_apply_window_back_to_back_with_activity_window(): void
    {
        $payload = $this->payload();
        $payload['apply_start_at'] = '2026-10-01 00:00:00';
        $payload['apply_end_at'] = '2026-10-02 00:00:00';
        $payload['start_at'] = '2026-10-02 00:00:00';
        $payload['end_at'] = '2026-10-03 00:00:00';

        $request = $this->makeRequest($payload);

        $request->validateResolved();

        $this->assertSame('2026-10-02 00:00:00', $request->validated('apply_end_at'));
    }

    public function test_activity_accepts_apply_window_before_activity_window_with_gap(): void
    {
        $payload = $this->payload();
        $payload['apply_start_at'] = '2026-09-01 00:00:00';
        $payload['apply_end_at'] = '2026-09-10 00:00:00';
        $payload['start_at'] = '2026-10-01 00:00:00';
        $payload['end_at'] = '2026-10-30 00:00:00';

        $request = $this->makeRequest($payload);

        $request->validateResolved();

        $this->assertSame('2026-10-30 00:00:00', $request->validated('end_at'));
    }

    public function test_activity_rejects_apply_window_crossing_activity_window(): void
    {
        $payload = $this->payload();
        $payload['apply_start_at'] = '2026-09-12 13:00:00';
        $payload['apply_end_at'] = '2026-09-12 14:30:00';

        $this->assertValidationMessage(
            $this->makeRequest($payload),
            trans('public.message.wrong_time_select')
        );
    }

    public function test_activity_rejects_apply_window_inside_activity_window(): void
    {
        $payload = $this->payload();
        $payload['apply_start_at'] = '2026-09-12 15:00:00';
        $payload['apply_end_at'] = '2026-09-12 17:00:00';

        $this->assertValidationMessage(
            $this->makeRequest($payload),
            trans('public.message.wrong_time_select')
        );
    }

    public function test_activity_rejects_apply_end_at_before_apply_start_at(): void
    {
        $payload = $this->payload();
        $payload['apply_start_at'] = '2026-09-12 12:00:00';
        $payload['apply_end_at'] = '2026-09-12 11:00:00';

        $this->assertValidationMessage(
            $this->makeRequest($payload),
            trans('public.message.wrong_threshold')
        );
    }

    private function assertValidationMessage(CommunityActivityStoreRequest $request, string $message): void
    {
        try {
            $request->validateResolved();
            $this->fail('Expected activity request validation to fail.');
        } catch (HttpResponseException $exception) {
            $payload = $exception->getResponse()->getData(true);

            $this->assertSame($message, $payload['message']);
        }
    }

    /**
     * @param  class-string<CommunityActivityStoreRequest>  $requestClass
     */
    private function makeRequest(
        array $payload,
        string $requestClass = CommunityActivityStoreRequest::class,
        string $method = 'POST'
    ): CommunityActivityStoreRequest {
        $request = $requestClass::create('/api/community_activities', $method, $payload);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        return $request;
    }

    private function payload(): array
    {
        return [
            'type' => 1,
            'city' => 'london',
            'start_at' => '2026-09-12 14:00:00',
            'end_at' => '2026-09-12 18:00:00',
            'apply_start_at' => '2026-09-12 10:00:00',
            'apply_end_at' => '2026-09-12 13:00:00',
            'audience' => ['web3_users', 'local_trading_community'],
            'locale_configs' => [[
                'lang' => 'en_US',
                'name' => 'London Web3 Trading Meetup',
                'summary' => 'Meet local traders and learn practical Web3 trading strategies.',
                'cover_image' => 'https://static.example.com/community_activity/20260820/cover-en.jpg?version=1',
                'bg_image' => 'https://static.example.com/community_activity/20260820/background-en.jpg',
                'introduction' => 'An in-person meetup for traders who want to exchange market insights.',
                'content' => '<p>Join our analysts and local community leaders for market sharing.</p>',
            ]],
        ];
    }
}
