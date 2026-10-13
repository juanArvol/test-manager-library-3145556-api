<?php

declare(strict_types=1);

namespace Library\Application\Ports\Outbound;

/**
 * TokenIssuer - Puerto outbound
 * 
 * Generador de tokens JWT (HS256, 8 horas).
 */
interface TokenIssuer
{
    /**
     * Genera un token JWT para un usuario
     * 
     * @param string $userId
     * @param string $role
     * @return array ['access_token' => string, 'token_type' => 'Bearer', 'expires_in' => int]
     */
    public function issue(string $userId, string $role): array;
}
