<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\PasswordHasher;

/**
 * FakePasswordHasher - Implementación simple para tests
 * 
 * Hash = "hashed:" + plainPassword
 */
final class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return 'hashed:' . $plainPassword;
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return $hash === $this->hash($plainPassword);
    }
}
