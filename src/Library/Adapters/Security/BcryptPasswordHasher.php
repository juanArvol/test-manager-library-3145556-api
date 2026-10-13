<?php

declare(strict_types=1);

namespace Library\Adapters\Security;

use Library\Application\Ports\Outbound\PasswordHasher;

final class BcryptPasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }

    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }
}
