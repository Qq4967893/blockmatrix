<?php

namespace Tests\Integration;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_application_health_check_is_available(): void
    {
        $this->get('/up')
            ->assertOk();
    }
}
