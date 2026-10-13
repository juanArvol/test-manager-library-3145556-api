<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

/**
 * Excepción lanzada cuando un rango de fechas es inválido
 */
final class InvalidDateRangeException extends ApplicationException
{
    public static function endBeforeStart(string $from, string $to): self
    {
        return new self("La fecha de fin ({$to}) no puede ser anterior a la de inicio ({$from}).");
    }

    public static function invalidFormat(): self
    {
        return new self("El formato de las fechas es inválido. Use formato ISO 8601 (YYYY-MM-DD).");
    }
}
