<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

/**
 * Excepción lanzada cuando se intenta crear un usuario con username duplicado
 */
final class UsernameAlreadyExistsException extends ApplicationException
{
    public static function forUsername(string $username): self
    {
        return new self("El nombre de usuario '{$username}' ya está registrado.");
    }
}
