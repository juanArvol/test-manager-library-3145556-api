<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

/**
 * Excepción lanzada cuando las credenciales son inválidas
 * (usuario no existe o contraseña incorrecta)
 */
final class InvalidCredentialsException extends ApplicationException
{
    public static function forLogin(): self
    {
        return new self('Credenciales inválidas.');
    }
}
