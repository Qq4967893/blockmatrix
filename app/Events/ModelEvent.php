<?php

namespace App\Events;

class ModelEvent
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public readonly string $table,
        public readonly string $eventType,
        public readonly array $data,
        public readonly array $message,
    ) {}

    public function tableName(): string
    {
        return str_contains($this->table, '.')
            ? (string) substr($this->table, strrpos($this->table, '.') + 1)
            : $this->table;
    }

    public function is(string $table, ?string $eventType = null): bool
    {
        return $this->tableName() === $table
            && ($eventType === null || $this->eventType === $eventType);
    }
}
