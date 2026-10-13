<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Exceptions\IsbnAlreadyExistsException;
use Library\Application\Ports\Outbound\BookRepository;
use Library\Domain\Book;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use Ramsey\Uuid\Uuid;

/**
 * CreateBookService - Caso de uso de creación de libros (HU-03)
 * 
 * Solo el admin puede crear libros.
 */
final readonly class CreateBookService
{
    public function __construct(
        private BookRepository $bookRepository
    ) {}

    /**
     * Crea un nuevo libro
     * 
     * CA-03.1: Crear libro con título, autor, ISBN, categoría y total de ejemplares
     * CA-03.2: El ISBN tiene 13 dígitos y es único entre libros activos
     * CA-03.3: Total de ejemplares ≥ 1
     * 
     * @return Book
     * @throws IsbnAlreadyExistsException si el ISBN ya existe en un libro activo
     */
    public function createBook(
        string $title,
        string $author,
        string $isbn,
        string $categoryId,
        int $totalCopies,
        ?string $coverKey = null
    ): Book {
        // CA-03.2: validar ISBN y verificar unicidad
        $isbnValue = new Isbn($isbn);
        
        if ($this->bookRepository->existsActiveWithIsbn($isbnValue->value())) {
            throw IsbnAlreadyExistsException::forIsbn($isbnValue->value());
        }
        
        // CA-03.3: validar que total >= 1 (lo hace el dominio)
        $totalCopiesQty = new Quantity($totalCopies);
        
        // CA-03.1: crear libro con disponibles = total
        $book = Book::create(
            id: Uuid::uuid4()->toString(),
            title: $title,
            author: $author,
            isbn: $isbnValue,
            categoryId: $categoryId,
            totalCopies: $totalCopiesQty,
            coverKey: $coverKey
        );
        
        $this->bookRepository->save($book);
        
        return $book;
    }
}
