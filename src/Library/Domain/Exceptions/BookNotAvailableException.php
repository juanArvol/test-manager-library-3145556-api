<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

/**
 * Excepción lanzada cuando se intenta retirar un libro sin ejemplares disponibles
 */
final class BookNotAvailableException extends DomainException
{
    public static function noAvailableCopies(string $bookTitle): self
    {
        return new self("No quedan ejemplares disponibles de «{$bookTitle}».");
    }
}
