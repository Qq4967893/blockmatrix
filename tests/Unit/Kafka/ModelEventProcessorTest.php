<?php

namespace Tests\Unit\Kafka;

use App\Events\ModelEvent;
use App\Services\Kafka\ModelEventProcessor;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ModelEventProcessorTest extends TestCase
{
    public function test_it_dispatches_events_for_any_table(): void
    {
        Event::fake();

        app(ModelEventProcessor::class)->process(json_encode([
            'app_name' => 'user-service',
            'table' => 'api.login_log',
            'event_type' => 'created',
            'data' => ['id' => 123, 'user_code' => 456],
        ], JSON_THROW_ON_ERROR));

        Event::assertDispatched(ModelEvent::class, function (ModelEvent $event): bool {
            return $event->is('login_log', 'created')
                && $event->data['id'] === 123
                && $event->message['app_name'] === 'user-service';
        });
    }

    public function test_it_dispatches_an_updated_event_for_another_table(): void
    {
        Event::fake();

        app(ModelEventProcessor::class)->process(json_encode([
            'table' => 'api.user',
            'event_type' => 'updated',
            'data' => ['dirty' => ['status' => 0], 'original' => ['status' => 1]],
        ], JSON_THROW_ON_ERROR));

        Event::assertDispatched(ModelEvent::class, fn (ModelEvent $event): bool => $event->is('user', 'updated'));
    }
}
