<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * DateRange - Objeto de valor
 * 
 * Representa un rango de fechas donde fin >= inicio.
 * Usado para consultas de reportes y listados.
 * Inmutable y auto-validante.
 */
final readonly class DateRange
{
    private DateTimeImmutable $start;
    private DateTimeImmutable $end;

    public function __construct(DateTimeImmutable $start, DateTimeImmutable $end)
    {
        $this->validate($start, $end);
        $this->start = $start;
        $this->end = $end;
    }

    private function validate(DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        if ($end < $start) {
            throw new InvalidArgumentException(
                "La fecha de fin ({$end->format('Y-m-d')}) no puede ser anterior a la de inicio ({$start->format('Y-m-d')})"
            );
        }
    }

    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): DateTimeImmutable
    {
        return $this->end;
    }

    /**
     * Verifica si una fecha está dentro del rango (inclusivo)
     */
    public function contains(DateTimeImmutable $date): bool
    {
        return $date >= $this->start && $date <= $this->end;
    }

    /**
     * Obtiene el inicio del día (00:00:00) para la fecha de inicio
     */
    public function startOfDay(): DateTimeImmutable
    {
        return $this->start->setTime(0, 0, 0, 0);
    }

    /**
     * Obtiene el fin del día (23:59:59) para la fecha de fin
     */
    public function endOfDay(): DateTimeImmutable
    {
        return $this->end->setTime(23, 59, 59, 999999);
    }

    /**
     * Calcula la cantidad de días en el rango (inclusivo)
     */
    public function days(): int
    {
        $diff = $this->start->diff($this->end);
        return $diff->days + 1; // +1 para incluir ambos extremos
    }

    public function equals(self $other): bool
    {
        return $this->start == $other->start && $this->end == $other->end;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s hasta %s',
            $this->start->format('Y-m-d'),
            $this->end->format('Y-m-d')
        );
    }
}
