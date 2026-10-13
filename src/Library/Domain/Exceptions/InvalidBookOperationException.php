<?php

declare(strict_types=1);

namespace Library\Domain\Exceptions;

/**
 * Excepción lanzada cuando una operación en Book viola invariantes
 */
final class InvalidBookOperationException extends DomainException
{
    public static function cannotReduceTotalBelowAvailable(int $newTotal, int $currentAvailable): self
    {
        return new self(
            "No se puede reducir el total a {$newTotal} cuando hay {$currentAvailable} ejemplares disponibles."
        );
    }

    public static function cannotExceedTotalCopies(int $available, int $total): self
    {
        return new self(
            "Los ejemplares disponibles ({$available}) no pueden exceder el total ({$total})."
        );
    }

    public static function totalCopiesMustBePositive(int $total): self
    {
        return new self(
            "El total de ejemplares debe ser al menos 1, recibido: {$total}."
        );
    }
}
