<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

/**
 * Excepción lanzada cuando un socio supera el máximo de libros sin devolver
 */
final class MemberLimitExceededException extends ApplicationException
{
    public static function forMember(string $memberName, int $currentCount, int $requestedCount): self
    {
        return new self(
            "El socio «{$memberName}» tiene {$currentCount} libros sin devolver. " .
            "Solicita {$requestedCount} más, lo que excedería el máximo de 3 libros."
        );
    }
}
