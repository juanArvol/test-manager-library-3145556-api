<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Exceptions\BookHasOpenLoansException;
use Library\Application\Exceptions\BookNotFoundException;
use Library\Application\Ports\Outbound\BookRepository;
use Library\Application\Ports\Outbound\Clock;

/**
 * DeleteBookService - Caso de uso de baja de libros (HU-03)
 * 
 * Solo el admin puede dar de baja libros.
 */
final readonly class DeleteBookService
{
    public function __construct(
        private BookRepository $bookRepository,
        private Clock $clock
    ) {}

    /**
     * Da de baja un libro (baja lógica)
     * 
     * CA-03.5: Eliminar un libro es baja lógica: deja de aparecer en el catálogo
     *          pero sigue en los préstamos pasados. Un libro con préstamos abiertos
     *          no se da de baja (409).
     * 
     * @throws BookNotFoundException si el libro no existe o ya está dado de baja
     * @throws BookHasOpenLoansException si el libro tiene préstamos abiertos
     */
    public function deleteBook(string $bookId): void
    {
        $book = $this->bookRepository->findById($bookId);
        
        if ($book === null) {
            throw BookNotFoundException::forId($bookId);
        }
        
        // CA-03.5: verificar que no tenga préstamos abiertos
        if ($this->bookRepository->hasOpenLoans($bookId)) {
            throw BookHasOpenLoansException::forBook($book->title());
        }
        
        // Baja lógica
        $book->delete($this->clock->now());
        
        $this->bookRepository->save($book);
    }
}
