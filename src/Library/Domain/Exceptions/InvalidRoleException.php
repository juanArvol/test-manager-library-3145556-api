<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class InvalidRoleException extends DomainException
{
    public static function forRole(string $role): self
    {
        return new self("El rol '{$role}' no es válido. Los roles permitidos son: admin, librarian.");
    }

    public static function adminNotAllowedViaApi(): self
    {
        return new self("No se permite crear usuarios con rol 'admin' mediante la API.");
    }
}
