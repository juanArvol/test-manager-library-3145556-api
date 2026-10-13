<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Exceptions\BookNotFoundException;
use Library\Application\Ports\Outbound\BookRepository;
use Library\Domain\Book;

/**
 * GetBookService - Caso de uso de consulta de libro por id (HU-04)
 * 
 * Disponible para usuarios autenticados.
 */
final readonly class GetBookService
{
    public function __construct(
        private BookRepository $bookRepository
    ) {}

    /**
     * Obtiene un libro por su id
     * 
     * CA-04.3: Consultar un libro por id inexistente o dado de baja: 404
     * 
     * @return Book
     * @throws BookNotFoundException si no existe o está dado de baja
     */
    public function getBook(string $bookId): Book
    {
        $book = $this->bookRepository->findById($bookId);
        
        if ($book === null) {
            throw BookNotFoundException::forId($bookId);
        }
        
        return $book;
    }
}
