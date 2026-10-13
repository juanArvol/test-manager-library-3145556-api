<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

/**
 * Excepción lanzada cuando no se encuentra un item dentro de un préstamo
 */
final class LoanItemNotFoundException extends ApplicationException
{
    public static function forItem(string $itemId, string $loanId): self
    {
        return new self("No se encontró el item '{$itemId}' en el préstamo '{$loanId}'.");
    }
}
