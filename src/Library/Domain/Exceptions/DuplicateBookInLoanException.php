<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

final class DuplicateBookInLoanException extends DomainException
{
    public static function forBook(string $bookTitle): self
    {
        return new self("El libro «{$bookTitle}» ya está incluido en este préstamo.");
    }
}
