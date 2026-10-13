<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\TokenIssuer;

/**
 * FakeTokenIssuer - Implementación simple para tests
 * 
 * Token = "fake-token-for-{userId}"
 */
final class FakeTokenIssuer implements TokenIssuer
{
    public function issue(string $userId, string $role): array
    {
        return [
            'access_token' => "fake-token-for-{$userId}",
            'token_type' => 'Bearer',
            'expires_in' => 28800, // 8 horas
        ];
    }
}
