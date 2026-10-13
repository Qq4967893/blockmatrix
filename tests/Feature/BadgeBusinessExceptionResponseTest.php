<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BadgeBusinessExceptionResponseTest extends TestCase
{
    public function test_business_exception_returns_friendly_api_response(): void
    {
        Route::get('/api/test/badge-business-exception', static function (): void {
            throw new BusinessException('Badge name already exists');
        });

        $this->getJson('/api/test/badge-business-exception')
            ->assertOk()
            ->assertExactJson([
                'is_succ' => false,
                'code' => 0,
                'message' => 'Badge name already exists',
                'data' => null,
            ]);
    }
}
