<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Exceptions\LoanItemNotFoundException;
use Library\Application\Exceptions\LoanNotFoundException;
use Library\Application\Ports\Outbound\BookRepository;
use Library\Application\Ports\Outbound\Clock;
use Library\Application\Ports\Outbound\LoanRepository;
use Library\Domain\Exceptions\LoanItemAlreadyReturnedException;

/**
 * ReturnItemService - Caso de uso de devolución de libros (HU-07)
 * 
 * Admin y librarian pueden registrar devoluciones.
 */
final readonly class ReturnItemService
{
    public function __construct(
        private LoanRepository $loanRepository,
        private BookRepository $bookRepository,
        private Clock $clock
    ) {}

    /**
     * Registra la devolución de una línea de préstamo
     * 
     * CA-07.1: Se devuelve una línea a la vez. Pone returned_at y suma 1 a los disponibles
     * CA-07.2: Devolver una línea ya devuelta: 409. Nunca suma dos veces
     * CA-07.3: Los disponibles jamás superan el total (la base lo garantiza)
     * CA-07.4: Cuando todas las líneas están devueltas, el préstamo figura como cerrado
     * 
     * @return void
     * @throws LoanNotFoundException si el préstamo no existe
     * @throws LoanItemNotFoundException si el item no existe en el préstamo
     * @throws LoanItemAlreadyReturnedException si ya fue devuelto (CA-07.2)
     */
    public function returnItem(string $loanId, string $itemId): void
    {
        // Buscar préstamo
        $loan = $this->loanRepository->findById($loanId);
        
        if ($loan === null) {
            throw LoanNotFoundException::forId($loanId);
        }

        // Buscar el item dentro del préstamo
        $item = null;
        foreach ($loan->items() as $loanItem) {
            if ($loanItem->id() === $itemId) {
                $item = $loanItem;
                break;
            }
        }

        if ($item === null) {
            throw LoanItemNotFoundException::forItem($itemId, $loanId);
        }

        // CA-07.1: marcar como devuelto (marca returned_at)
        // CA-07.2: lanza excepción si ya está devuelto
        $now = $this->clock->now();
        $loan->returnItem($itemId, $now);

        // CA-07.1: sumar 1 a los disponibles del libro
        $book = $this->bookRepository->findByIdIncludingDeleted($item->bookId());
        
        if ($book !== null) {
            $book->restock();
            $this->bookRepository->save($book);
        }

        // Guardar préstamo con la línea devuelta
        $this->loanRepository->save($loan);
        
        // CA-07.4: el estado "closed" se calcula, no se almacena
        // No necesitamos hacer nada más aquí
    }
}
