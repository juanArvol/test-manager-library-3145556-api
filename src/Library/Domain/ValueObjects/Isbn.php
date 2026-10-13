<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * ISBN - Objeto de valor
 * 
 * Representa un ISBN de 13 dígitos sin guiones.
 * Inmutable y auto-validante.
 */
final readonly class Isbn
{
    private string $value;

    public function __construct(string $value)
    {
        $cleaned = $this->clean($value);
        $this->validate($cleaned);
        $this->value = $cleaned;
    }

    private function clean(string $value): string
    {
        // Eliminar guiones y espacios
        return preg_replace('/[\s\-]/', '', $value);
    }

    private function validate(string $value): void
    {
        // Debe tener exactamente 13 caracteres
        if (strlen($value) !== 13) {
            throw new InvalidArgumentException(
                "ISBN debe tener exactamente 13 dígitos, recibido: {$value}"
            );
        }

        // Todos deben ser dígitos
        if (!ctype_digit($value)) {
            throw new InvalidArgumentException(
                "ISBN debe contener solo dígitos, recibido: {$value}"
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
