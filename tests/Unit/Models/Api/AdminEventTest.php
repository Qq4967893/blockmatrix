<?php

namespace Tests\Unit\Models\Api;

use App\Models\Api\AdminEvent;
use Scaffold\Framework\Enums\TypeAdminEvent;
use Scaffold\Framework\Traits\ProduceModelEvent;
use Tests\TestCase;

class AdminEventTest extends TestCase
{
    public function test_admin_event_uses_api_table_casts_and_model_event_trait(): void
    {
        $event = new AdminEvent;
        $event->setRawAttributes([
            'event_key' => TypeAdminEvent::OpenBonusFund->value,
            'event_params' => '{"user_code":587239582,"amount":10000}',
            'status' => AdminEvent::STATUS_PENDING,
        ]);

        $this->assertSame(DB_API, $event->getConnectionName());
        $this->assertSame('admin_event', $event->getTable());
        $this->assertSame([], $event->getGuarded());
        $this->assertContains(ProduceModelEvent::class, class_uses_recursive(AdminEvent::class));
        $this->assertSame(TypeAdminEvent::class, $event->getCasts()['event_key']);
        $this->assertSame('array', $event->getCasts()['event_params']);
        $this->assertSame('integer', $event->getCasts()['status']);
        $this->assertSame(TypeAdminEvent::OpenBonusFund, $event->event_key);
        $this->assertSame(['user_code' => 587239582, 'amount' => 10000], $event->event_params);
        $this->assertSame(0, AdminEvent::STATUS_PENDING);
        $this->assertSame(1, AdminEvent::STATUS_SUCCESS);
    }
}
