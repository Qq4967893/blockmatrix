<?php

namespace App\Services\Kafka;

use App\Events\ModelEvent;
use Illuminate\Support\Facades\Event;
use JsonException;
use UnexpectedValueException;

class ModelEventProcessor
{
    /**
     * Parse and dispatch a Scaffold ProduceModelEventJob payload for any table.
     *
     * @throws JsonException|UnexpectedValueException When the model-event payload is malformed.
     */
    public function process(string $payload): void
    {
        /** @var mixed $message */
        $message = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($message)) {
            throw new UnexpectedValueException('Kafka model event must be a JSON object.');
        }

        $table = $message['table'] ?? null;
        $eventType = $message['event_type'] ?? null;
        $data = $message['data'] ?? null;

        if (! is_string($table) || $table === '') {
            throw new UnexpectedValueException('Kafka model event has no table.');
        }

        if (! is_string($eventType) || $eventType === '') {
            throw new UnexpectedValueException('Kafka model event has no event_type.');
        }

        if (! is_array($data)) {
            throw new UnexpectedValueException('Kafka model event data must be an object.');
        }

        Event::dispatch(new ModelEvent($table, $eventType, $data, $message));
    }
}
