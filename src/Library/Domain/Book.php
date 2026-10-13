<?php

declare(strict_types=1);

namespace Library\Domain;

use DateTimeImmutable;
use Library\Domain\Exceptions\BookNotAvailableException;
use Library\Domain\Exceptions\InvalidBookOperationException;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;

/**
 * Book - Agregado raíz
 * 
 * Representa un libro del catálogo con sus ejemplares.
 * Invariantes que defiende:
 * - 0 <= available_copies <= total_copies
 * - total_copies >= 1
 * - título no vacío
 * 
 * Operaciones:
 * - Withdraw: retira un ejemplar (préstamo)
 * - Restock: devuelve un ejemplar (devolución)
 * - Delete: baja lógica
 */
final class Book
{
    private string $id;
    private string $title;
    private string $author;
    private Isbn $isbn;
    private string $categoryId;
    private Quantity $totalCopies;
    private Quantity $availableCopies;
    private ?string $coverKey;
    private ?DateTimeImmutable $deletedAt;

    private function __construct(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        Quantity $totalCopies,
        Quantity $availableCopies,
        ?string $coverKey = null,
        ?DateTimeImmutable $deletedAt = null
    ) {
        $this->validateTitle($title);
        $this->validateCopiesRange($availableCopies, $totalCopies);
        
        $this->id = $id;
        $this->title = trim($title);
        $this->author = trim($author);
        $this->isbn = $isbn;
        $this->categoryId = $categoryId;
        $this->totalCopies = $totalCopies;
        $this->availableCopies = $availableCopies;
        $this->coverKey = $coverKey;
        $this->deletedAt = $deletedAt;
    }

    /**
     * Crea un nuevo libro
     */
    public static function create(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        Quantity $totalCopies,
        ?string $coverKey = null
    ): self {
        if (!$totalCopies->isPositive()) {
            throw InvalidBookOperationException::totalCopiesMustBePositive($totalCopies->value());
        }
        
        // Al crear, disponibles = total
        return new self(
            $id,
            $title,
            $author,
            $isbn,
            $categoryId,
            $totalCopies,
            $totalCopies, // available = total al inicio
            $coverKey,
            null
        );
    }

    /**
     * Reconstituye un libro desde persistencia
     */
    public static function reconstitute(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        Quantity $totalCopies,
        Quantity $availableCopies,
        ?string $coverKey,
        ?DateTimeImmutable $deletedAt
    ): self {
        return new self(
            $id,
            $title,
            $author,
            $isbn,
            $categoryId,
            $totalCopies,
            $availableCopies,
            $coverKey,
            $deletedAt
        );
    }

    /**
     * Retira un ejemplar (para préstamo)
     * 
     * @throws BookNotAvailableException si no hay ejemplares disponibles
     */
    public function withdraw(): void
    {
        if ($this->availableCopies->isZero()) {
            throw BookNotAvailableException::noAvailableCopies($this->title);
        }
        
        $this->availableCopies = $this->availableCopies->subtract(new Quantity(1));
    }

    /**
     * Devuelve un ejemplar (al devolver préstamo)
     * 
     * @throws InvalidBookOperationException si excedería el total
     */
    public function restock(): void
    {
        $newAvailable = $this->availableCopies->add(new Quantity(1));
        
        if ($newAvailable->isGreaterThan($this->totalCopies)) {
            throw InvalidBookOperationException::cannotExceedTotalCopies(
                $newAvailable->value(),
                $this->totalCopies->value()
            );
        }
        
        $this->availableCopies = $newAvailable;
    }

    /**
     * Actualiza el total de ejemplares (ajusta los disponibles proporcionalmente)
     * 
     * @throws InvalidBookOperationException si la operación violaría invariantes
     */
    public function updateTotalCopies(Quantity $newTotal): void
    {
        if (!$newTotal->isPositive()) {
            throw InvalidBookOperationException::totalCopiesMustBePositive($newTotal->value());
        }
        
        $delta = $newTotal->value() - $this->totalCopies->value();
        $newAvailable = new Quantity($this->availableCopies->value() + $delta);
        
        // Validar que no quede negativo
        if ($newAvailable->value() < 0) {
            throw InvalidBookOperationException::cannotReduceTotalBelowAvailable(
                $newTotal->value(),
                $this->availableCopies->value()
            );
        }
        
        $this->totalCopies = $newTotal;
        $this->availableCopies = $newAvailable;
    }

    /**
     * Actualiza título, autor y categoría
     */
    public function update(string $title, string $author, string $categoryId): void
    {
        $this->validateTitle($title);
        
        $this->title = trim($title);
        $this->author = trim($author);
        $this->categoryId = $categoryId;
    }

    /**
     * Asigna una portada
     */
    public function setCover(string $coverKey): void
    {
        $this->coverKey = $coverKey;
    }

    /**
     * Elimina la portada
     */
    public function removeCover(): void
    {
        $this->coverKey = null;
    }

    /**
     * Baja lógica del libro
     */
    public function delete(DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * Verifica si el libro está activo
     */
    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    /**
     * Verifica si tiene ejemplares disponibles
     */
    public function hasAvailableCopies(): bool
    {
        return $this->availableCopies->isPositive();
    }

    // Getters
    
    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function isbn(): Isbn
    {
        return $this->isbn;
    }

    public function categoryId(): string
    {
        return $this->categoryId;
    }

    public function totalCopies(): Quantity
    {
        return $this->totalCopies;
    }

    public function availableCopies(): Quantity
    {
        return $this->availableCopies;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    // Validaciones privadas

    private function validateTitle(string $title): void
    {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('El título del libro no puede estar vacío.');
        }
    }

    private function validateCopiesRange(Quantity $available, Quantity $total): void
    {
        if ($available->isGreaterThan($total)) {
            throw InvalidBookOperationException::cannotExceedTotalCopies(
                $available->value(),
                $total->value()
            );
        }
    }
}
