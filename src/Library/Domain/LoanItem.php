<?php

declare(strict_types=1);

namespace Library\Domain;

use DateTimeImmutable;
use Library\Domain\Exceptions\LoanItemAlreadyReturnedException;
use Library\Domain\ValueObjects\Isbn;

/**
 * LoanItem - Entidad dentro del agregado Loan
 * 
 * Representa un libro dentro de un préstamo con su vencimiento.
 * Congela título, ISBN y categoría en el instante del préstamo (ADR-004).
 */
final class LoanItem
{
    private string $id;
    private string $bookId;
    private string $bookTitle;
    private Isbn $isbn;
    private string $categoryName;
    private DateTimeImmutable $dueAt;
    private ?DateTimeImmutable $returnedAt;

    public function __construct(
        string $id,
        string $bookId,
        string $bookTitle,
        Isbn $isbn,
        string $categoryName,
        DateTimeImmutable $dueAt,
        ?DateTimeImmutable $returnedAt = null
    ) {
        $this->id = $id;
        $this->bookId = $bookId;
        $this->bookTitle = $bookTitle;
        $this->isbn = $isbn;
        $this->categoryName = $categoryName;
        $this->dueAt = $dueAt;
        $this->returnedAt = $returnedAt;
    }

    /**
     * Marca el item como devuelto
     * 
     * @throws LoanItemAlreadyReturnedException si ya está devuelto
     */
    public function markAsReturned(DateTimeImmutable $returnedAt): void
    {
        if ($this->isReturned()) {
            throw LoanItemAlreadyReturnedException::forItem($this->id);
        }
        
        $this->returnedAt = $returnedAt;
    }

    /**
     * Verifica si el item está devuelto
     */
    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    /**
     * Verifica si el item está vencido (no devuelto y fecha vencida)
     */
    public function isOverdue(DateTimeImmutable $now): bool
    {
        return !$this->isReturned() && $now > $this->dueAt;
    }

    /**
     * Calcula días de retraso (0 si no está vencido)
     */
    public function daysLate(DateTimeImmutable $now): int
    {
        if (!$this->isOverdue($now)) {
            return 0;
        }
        
        $diff = $now->diff($this->dueAt);
        return $diff->days;
    }

    // Getters

    public function id(): string
    {
        return $this->id;
    }

    public function bookId(): string
    {
        return $this->bookId;
    }

    public function bookTitle(): string
    {
        return $this->bookTitle;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function categoryName(): string
    {
        return $this->categoryName;
    }

    public function dueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }
}
