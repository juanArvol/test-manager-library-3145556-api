<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

use RuntimeException;

/**
 * Excepción base del dominio
 * 
 * Todas las excepciones de negocio heredan de esta clase.
 */
abstract class DomainException extends RuntimeException
{
    public function __construct(string $message, int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
