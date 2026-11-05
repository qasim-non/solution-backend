<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiRouteTest extends TestCase
{
    public function test_api_test_route_returns_success(): void
    {
        $response = $this->getJson('/api/test');

        $response->assertStatus(200)
            ->assertExactJson([
                'message' => 'API route is working',
            ]);
    }
}
