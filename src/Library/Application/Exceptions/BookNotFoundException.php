<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

/**
 * Excepción lanzada cuando no se encuentra un libro o está dado de baja
 */
final class BookNotFoundException extends ApplicationException
{
    public static function forId(string $bookId): self
    {
        return new self("No se encontró el libro con id '{$bookId}' o está dado de baja.");
    }
}
