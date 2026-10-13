<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

/**
 * Excepción lanzada cuando se intenta crear un libro con ISBN duplicado
 */
final class IsbnAlreadyExistsException extends ApplicationException
{
    public static function forIsbn(string $isbn): self
    {
        return new self("Ya existe un libro activo con el ISBN '{$isbn}'.");
    }
}
