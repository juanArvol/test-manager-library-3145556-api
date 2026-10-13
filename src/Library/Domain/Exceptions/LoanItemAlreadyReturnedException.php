<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class LoanItemAlreadyReturnedException extends DomainException
{
    public static function forItem(string $itemId): self
    {
        return new self("El item {$itemId} ya fue devuelto.");
    }
}
