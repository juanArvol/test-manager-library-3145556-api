<?php

declare(strict_types=1);

namespace Library\Application\Exceptions;

use RuntimeException;

/**
 * Excepción base de la capa de aplicación
 */
abstract class ApplicationException extends RuntimeException
{
    public function __construct(string $message, int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
