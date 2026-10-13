<?php

declare(strict_types=1);

namespace Library\Application\Ports\Outbound;

/**
 * PasswordHasher - Puerto outbound
 * 
 * Hasheador de contraseñas (Argon2id en producción).
 */
interface PasswordHasher
{
    /**
     * Hashea una contraseña en texto claro
     */
    public function hash(string $plainPassword): string;

    /**
     * Verifica si una contraseña en claro coincide con un hash
     */
    public function verify(string $plainPassword, string $hash): bool;
}
