<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Quantity - Objeto de valor
 * 
 * Representa una cantidad entera positiva (> 0).
 * Usado para total_copies y available_copies.
 * Inmutable y auto-validante.
 */
final readonly class Quantity
{
    private int $value;

    public function __construct(int $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    private function validate(int $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(
                "Quantity no puede ser negativa, recibido: {$value}"
            );
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function isZero(): bool
    {
        return $this->value === 0;
    }

    public function isPositive(): bool
    {
        return $this->value > 0;
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function subtract(self $other): self
    {
        $result = $this->value - $other->value;
        
        if ($result < 0) {
            throw new InvalidArgumentException(
                "No se puede restar {$other->value} de {$this->value}: resultado sería negativo"
            );
        }
        
        return new self($result);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->value >= $other->value;
    }

    public function isLessThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function isLessThanOrEqual(self $other): bool
    {
        return $this->value <= $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
