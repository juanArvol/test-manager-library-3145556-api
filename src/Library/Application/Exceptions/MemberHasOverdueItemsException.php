<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

/**
 * Excepción lanzada cuando un socio con libros vencidos intenta pedir otro
 */
final class MemberHasOverdueItemsException extends ApplicationException
{
    public static function forMember(string $memberName): self
    {
        return new self("El socio «{$memberName}» tiene libros vencidos sin devolver y no puede solicitar más préstamos.");
    }
}
