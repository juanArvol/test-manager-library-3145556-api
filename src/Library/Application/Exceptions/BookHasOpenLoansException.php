<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

/**
 * Excepción lanzada cuando se intenta dar de baja un libro con préstamos abiertos
 */
final class BookHasOpenLoansException extends ApplicationException
{
    public static function forBook(string $bookTitle): self
    {
        return new self("No se puede dar de baja el libro «{$bookTitle}» porque tiene préstamos abiertos.");
    }
}
