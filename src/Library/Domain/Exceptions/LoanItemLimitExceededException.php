<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class LoanItemLimitExceededException extends DomainException
{
    public static function maxItemsReached(int $max): self
    {
        return new self("Un préstamo no puede tener más de {$max} libros.");
    }
}
