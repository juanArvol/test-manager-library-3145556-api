<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

/**
 * Excepción lanzada cuando no se encuentra un préstamo
 */
final class LoanNotFoundException extends ApplicationException
{
    public static function forId(string $loanId): self
    {
        return new self("No se encontró el préstamo con id '{$loanId}'.");
    }
}
