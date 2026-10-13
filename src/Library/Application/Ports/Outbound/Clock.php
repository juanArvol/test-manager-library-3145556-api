<?php

declare(strict_types=1);

namespace Library\Application\Ports\Outbound;

use DateTimeImmutable;

/**
 * Clock - Puerto de tiempo
 * 
 * Permite obtener el instante actual.
 * Facilita pruebas con tiempo controlado (fake).
 */
interface Clock
{
    /**
     * Obtiene el instante actual en UTC
     */
    public function now(): DateTimeImmutable;
}
