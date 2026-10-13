<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\Clock;
use Library\Application\Ports\Outbound\LoanRepository;

/**
 * GetOverdueItemsService - Caso de uso de libros vencidos (HU-09)
 * 
 * Solo admin y librarian pueden consultar vencidos.
 */
final readonly class GetOverdueItemsService
{
    public function __construct(
        private LoanRepository $loanRepository,
        private Clock $clock
    ) {}

    /**
     * Lista las líneas sin devolver con vencimiento pasado
     * 
     * CA-09.1: Lista las líneas sin devolver con due_at anterior a ahora: libro, socio, días de retraso
     * CA-09.2: Ordenadas por más días de retraso primero
     * 
     * @return array Array de ['loanId', 'itemId', 'bookTitle', 'memberName', 'dueAt', 'daysLate']
     */
    public function getOverdueItems(): array
    {
        $now = $this->clock->now();
        
        // CA-09.1: obtener líneas vencidas sin devolver
        $overdueItems = $this->loanRepository->findOverdueItems($now);
        
        // CA-09.2: ordenar por días de retraso descendente (más días primero)
        usort($overdueItems, function ($a, $b) {
            return $b['daysLate'] <=> $a['daysLate'];
        });
        
        return $overdueItems;
    }
}
