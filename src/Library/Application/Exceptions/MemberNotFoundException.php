<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

/**
 * Excepción lanzada cuando no se encuentra un socio
 */
final class MemberNotFoundException extends ApplicationException
{
    public static function forId(string $memberId): self
    {
        return new self("No se encontró el socio con id '{$memberId}'.");
    }
}
