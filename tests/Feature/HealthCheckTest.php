<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * T-01: Verificar que GET /health responde {"status":"ok"}
     */
    public function test_health_endpoint_returns_ok_status(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    /**
     * Verificar que el endpoint health no requiere autenticación
     */
    public function test_health_endpoint_is_public(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status']);
    }
}
