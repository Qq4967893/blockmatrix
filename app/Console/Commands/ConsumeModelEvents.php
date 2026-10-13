<?php

namespace App\Console\Commands;

use App\Services\Kafka\ModelEventProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RuntimeException;

#[Signature('kafka:consume-model-events {--once : Consume one Kafka message and exit} {--timeout=1000 : Poll timeout in milliseconds}')]
#[Description('Consume model events for all tables from the php-common Kafka topic')]
class ConsumeModelEvents extends Command
{
    public function handle(ModelEventProcessor $processor): int
    {
        if (! class_exists(KafkaConsumer::class)) {
            $this->components->error('The rdkafka PHP extension is required.');

            return self::FAILURE;
        }

        $brokers = trim((string) config('kafka.model_event.brokers'));
        if ($brokers === '') {
            $this->components->error('KAFKA_BROKERS is not configured.');

            return self::FAILURE;
        }

        $topic = trim((string) config('kafka.model_event.topic'));
        if ($topic === '') {
            $this->components->error('KAFKA_MODEL_EVENT_TOPIC is not configured.');

            return self::FAILURE;
        }

        $groupId = trim((string) config('kafka.model_event.group_id'));
        if ($groupId === '') {
            $this->components->error('KAFKA_MODEL_EVENT_GROUP_ID is not configured.');

            return self::FAILURE;
        }

        $conf = new Conf;
        $conf->set('metadata.broker.list', $brokers);
        $conf->set('group.id', $groupId);
        $conf->set('enable.auto.commit', 'false');
        $conf->set('auto.offset.reset', (string) config('kafka.model_event.auto_offset_reset'));

        $consumer = new KafkaConsumer($conf);
        $timeout = max(1, (int) $this->option('timeout'));

        try {
            $consumer->subscribe([$topic]);
            $this->components->info("Consuming {$topic} as {$groupId}.");

            do {
                $message = $consumer->consume($timeout);

                if ($message->err === RD_KAFKA_RESP_ERR__TIMED_OUT || $message->err === RD_KAFKA_RESP_ERR__PARTITION_EOF) {
                    continue;
                }

                if ($message->err !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                    throw new RuntimeException(sprintf('Kafka consume failed: %s', $message->errstr()));
                }

                Log::info('Kafka model event received.', [
                    'topic' => $message->topic_name,
                    'partition' => $message->partition,
                    'offset' => $message->offset,
                    'key' => $message->key,
                    'payload' => $message->payload,
                ]);

                $processor->process((string) $message->payload);
                // Commit only after all handlers for this table event have succeeded.
                $consumer->commit($message);
            } while (! $this->option('once'));
        } finally {
            $consumer->close();
        }

        return self::SUCCESS;
    }
}
