<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Exceptions\BookNotFoundException;
use Library\Application\Ports\Outbound\BookRepository;
use Library\Domain\ValueObjects\Quantity;

/**
 * UpdateBookService - Caso de uso de actualización de libros (HU-03)
 * 
 * Solo el admin puede actualizar libros.
 */
final readonly class UpdateBookService
{
    public function __construct(
        private BookRepository $bookRepository
    ) {}

    /**
     * Actualiza título, autor, categoría y total de ejemplares
     * 
     * CA-03.3: Cambiar el total ajusta los disponibles en la misma diferencia
     *          y nunca deja disponibles < 0
     * 
     * @throws BookNotFoundException si el libro no existe o está dado de baja
     */
    public function updateBook(
        string $bookId,
        string $title,
        string $author,
        string $categoryId,
        int $totalCopies
    ): void {
        $book = $this->bookRepository->findById($bookId);
        
        if ($book === null) {
            throw BookNotFoundException::forId($bookId);
        }
        
        // Actualizar título, autor y categoría
        $book->update($title, $author, $categoryId);
        
        // CA-03.3: actualizar total (ajusta disponibles automáticamente)
        $newTotal = new Quantity($totalCopies);
        $book->updateTotalCopies($newTotal);
        
        $this->bookRepository->save($book);
    }
}
