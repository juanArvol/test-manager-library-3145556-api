<?php

declare(strict_types=1);

namespace Library\Application\Ports\Outbound;

use Library\Domain\Loan;

/**
 * LoanRepository - Puerto outbound
 * 
 * Repositorio de préstamos.
 */
interface LoanRepository
{
    /**
     * Guarda un préstamo (create o update)
     */
    public function save(Loan $loan): void;

    /**
     * Busca un préstamo por id
     * Retorna null si no existe
     */
    public function findById(string $id): ?Loan;

    /**
     * Cuenta líneas sin devolver de un socio
     */
    public function countUnreturnedItemsForMember(string $memberId): int;

    /**
     * Verifica si un socio tiene alguna línea vencida sin devolver
     */
    public function memberHasOverdueItems(string $memberId): bool;

    /**
     * Obtiene todas las líneas vencidas sin devolver del sistema
     * 
     * @param \DateTimeImmutable $now Instante actual
     * @return array Array de ['loanId', 'itemId', 'bookTitle', 'memberName', 'dueAt', 'daysLate']
     */
    public function findOverdueItems(\DateTimeImmutable $now): array;
}
