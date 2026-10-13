<?php

declare(strict_types=1);

namespace Library\Domain;

use DateTimeImmutable;
use Library\Domain\Exceptions\DuplicateBookInLoanException;
use Library\Domain\Exceptions\LoanItemLimitExceededException;
use Library\Domain\ValueObjects\Isbn;

/**
 * Loan - Agregado raíz
 * 
 * Representa un préstamo con sus líneas.
 * Invariantes que defiende:
 * - 1 a 3 líneas por préstamo
 * - Sin libro repetido en el mismo préstamo
 * - Vencimiento = loaned_at + 14 días
 * - Estado calculado (no almacenado): open, overdue, closed
 */
final class Loan
{
    private const LOAN_PERIOD_DAYS = 14;
    private const MAX_ITEMS = 3;

    private string $id;
    private string $memberId;
    private string $loanedByUserId;
    private DateTimeImmutable $loanedAt;
    private int $version;
    
    /** @var LoanItem[] */
    private array $items;

    private function __construct(
        string $id,
        string $memberId,
        string $loanedByUserId,
        DateTimeImmutable $loanedAt,
        int $version = 0,
        array $items = []
    ) {
        $this->id = $id;
        $this->memberId = $memberId;
        $this->loanedByUserId = $loanedByUserId;
        $this->loanedAt = $loanedAt;
        $this->version = $version;
        $this->items = $items;
    }

    /**
     * Crea un nuevo préstamo vacío
     */
    public static function create(
        string $id,
        string $memberId,
        string $loanedByUserId,
        DateTimeImmutable $loanedAt
    ): self {
        return new self($id, $memberId, $loanedByUserId, $loanedAt, 0, []);
    }

    /**
     * Reconstituye un préstamo desde persistencia
     */
    public static function reconstitute(
        string $id,
        string $memberId,
        string $loanedByUserId,
        DateTimeImmutable $loanedAt,
        int $version,
        array $items
    ): self {
        return new self($id, $memberId, $loanedByUserId, $loanedAt, $version, $items);
    }

    /**
     * Añade un libro al préstamo
     * 
     * @throws DuplicateBookInLoanException si el libro ya está en el préstamo
     * @throws LoanItemLimitExceededException si ya tiene 3 líneas
     */
    public function addItem(
        string $itemId,
        Book $book,
        string $categoryName
    ): void {
        // Validar límite de 3 líneas
        if (count($this->items) >= self::MAX_ITEMS) {
            throw LoanItemLimitExceededException::maxItemsReached(self::MAX_ITEMS);
        }

        // Validar que no se repita el libro
        foreach ($this->items as $item) {
            if ($item->bookId() === $book->id()) {
                throw DuplicateBookInLoanException::forBook($book->title());
            }
        }

        // Calcular vencimiento: loaned_at + 14 días
        $dueAt = $this->loanedAt->modify('+' . self::LOAN_PERIOD_DAYS . ' days');

        // Crear línea con valores congelados
        $item = new LoanItem(
            id: $itemId,
            bookId: $book->id(),
            bookTitle: $book->title(),
            isbn: $book->isbn(),
            categoryName: $categoryName,
            dueAt: $dueAt,
            returnedAt: null
        );

        $this->items[] = $item;
    }

    /**
     * Devuelve un item específico
     * 
     * @throws \InvalidArgumentException si el item no existe
     */
    public function returnItem(string $itemId, DateTimeImmutable $returnedAt): void
    {
        $item = $this->findItem($itemId);
        
        if ($item === null) {
            throw new \InvalidArgumentException("Item {$itemId} no pertenece a este préstamo.");
        }
        
        $item->markAsReturned($returnedAt);
    }

    /**
     * Calcula el estado del préstamo (no se almacena - Artículo VII)
     * 
     * - open: hay líneas sin devolver y ninguna vencida
     * - overdue: alguna línea sin devolver está vencida
     * - closed: todas las líneas están devueltas
     */
    public function calculateStatus(DateTimeImmutable $now): string
    {
        $hasUnreturned = false;
        $hasOverdue = false;

        foreach ($this->items as $item) {
            if (!$item->isReturned()) {
                $hasUnreturned = true;
                
                if ($item->isOverdue($now)) {
                    $hasOverdue = true;
                    break; // Ya sabemos que es overdue
                }
            }
        }

        if (!$hasUnreturned) {
            return 'closed';
        }

        if ($hasOverdue) {
            return 'overdue';
        }

        return 'open';
    }

    /**
     * Verifica si todas las líneas están devueltas
     */
    public function isFullyReturned(): bool
    {
        foreach ($this->items as $item) {
            if (!$item->isReturned()) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Verifica si tiene alguna línea vencida sin devolver
     */
    public function hasOverdueItems(DateTimeImmutable $now): bool
    {
        foreach ($this->items as $item) {
            if ($item->isOverdue($now)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Cuenta líneas sin devolver
     */
    public function countUnreturnedItems(): int
    {
        $count = 0;
        
        foreach ($this->items as $item) {
            if (!$item->isReturned()) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Obtiene líneas vencidas sin devolver
     * 
     * @return LoanItem[]
     */
    public function getOverdueItems(DateTimeImmutable $now): array
    {
        $overdue = [];
        
        foreach ($this->items as $item) {
            if ($item->isOverdue($now)) {
                $overdue[] = $item;
            }
        }
        
        return $overdue;
    }

    // Getters

    public function id(): string
    {
        return $this->id;
    }

    public function memberId(): string
    {
        return $this->memberId;
    }

    public function loanedByUserId(): string
    {
        return $this->loanedByUserId;
    }

    public function loanedAt(): DateTimeImmutable
    {
        return $this->loanedAt;
    }

    /**
     * @return LoanItem[]
     */
    public function items(): array
    {
        return $this->items;
    }

    public function itemCount(): int
    {
        return count($this->items);
    }

    public function version(): int
    {
        return $this->version;
    }

    // Private helpers

    private function findItem(string $itemId): ?LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id() === $itemId) {
                return $item;
            }
        }
        
        return null;
    }
}
