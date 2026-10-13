<?php

namespace Tests\Unit\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Library\Domain\Book;
use Library\Domain\Exceptions\BookNotAvailableException;
use Library\Domain\Exceptions\InvalidBookOperationException;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

class BookTest extends TestCase
{
    private function createBook(int $totalCopies = 5): Book
    {
        return Book::create(
            id: 'book-123',
            title: 'Cien años de soledad',
            author: 'Gabriel García Márquez',
            isbn: new Isbn('9780307474728'),
            categoryId: 'cat-1',
            totalCopies: new Quantity($totalCopies)
        );
    }

    /**
     * T-04: Crear un libro con disponibles = total
     */
    public function test_creates_book_with_available_equal_to_total(): void
    {
        $book = $this->createBook(5);
        
        $this->assertEquals(5, $book->totalCopies()->value());
        $this->assertEquals(5, $book->availableCopies()->value());
        $this->assertTrue($book->isActive());
        $this->assertTrue($book->hasAvailableCopies());
    }

    /**
     * T-04: Crear libro con total < 1 lanza excepción
     */
    public function test_rejects_book_with_zero_total_copies(): void
    {
        $this->expectException(InvalidBookOperationException::class);
        $this->expectExceptionMessage('El total de ejemplares debe ser al menos 1');
        
        Book::create(
            'book-123',
            'Título',
            'Autor',
            new Isbn('9780307474728'),
            'cat-1',
            new Quantity(0)
        );
    }

    /**
     * T-04: Crear libro con título vacío lanza excepción
     */
    public function test_rejects_book_with_empty_title(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El título del libro no puede estar vacío');
        
        Book::create(
            'book-123',
            '   ',
            'Autor',
            new Isbn('9780307474728'),
            'cat-1',
            new Quantity(5)
        );
    }

    /**
     * T-04: Withdraw reduce disponibles en 1
     */
    public function test_withdraw_reduces_available_copies_by_one(): void
    {
        $book = $this->createBook(5);
        
        $book->withdraw();
        
        $this->assertEquals(5, $book->totalCopies()->value());
        $this->assertEquals(4, $book->availableCopies()->value());
    }

    /**
     * T-04: Withdraw múltiples veces
     */
    public function test_withdraw_multiple_times(): void
    {
        $book = $this->createBook(3);
        
        $book->withdraw();
        $book->withdraw();
        $book->withdraw();
        
        $this->assertEquals(3, $book->totalCopies()->value());
        $this->assertEquals(0, $book->availableCopies()->value());
        $this->assertFalse($book->hasAvailableCopies());
    }

    /**
     * T-04: Withdraw con cero disponibles lanza excepción (nunca < 0)
     */
    public function test_withdraw_with_zero_available_throws_exception(): void
    {
        $this->expectException(BookNotAvailableException::class);
        $this->expectExceptionMessage('No quedan ejemplares disponibles');
        
        $book = $this->createBook(1);
        $book->withdraw();
        $book->withdraw(); // Segunda vez debe fallar
    }

    /**
     * T-04: Restock aumenta disponibles en 1
     */
    public function test_restock_increases_available_copies_by_one(): void
    {
        $book = $this->createBook(5);
        $book->withdraw();
        $book->withdraw();
        
        $this->assertEquals(3, $book->availableCopies()->value());
        
        $book->restock();
        
        $this->assertEquals(4, $book->availableCopies()->value());
    }

    /**
     * T-04: Restock múltiples veces hasta llegar al total
     */
    public function test_restock_multiple_times_up_to_total(): void
    {
        $book = $this->createBook(3);
        $book->withdraw();
        $book->withdraw();
        $book->withdraw();
        
        $this->assertEquals(0, $book->availableCopies()->value());
        
        $book->restock();
        $book->restock();
        $book->restock();
        
        $this->assertEquals(3, $book->availableCopies()->value());
    }

    /**
     * T-04: Restock no puede exceder el total (nunca > total)
     */
    public function test_restock_cannot_exceed_total_copies(): void
    {
        $this->expectException(InvalidBookOperationException::class);
        $this->expectExceptionMessage('Los ejemplares disponibles (4) no pueden exceder el total (3)');
        
        $book = $this->createBook(3);
        
        // Intentar devolver más de lo que hay
        $book->restock(); // 3 + 1 = 4 > 3 (total)
    }

    /**
     * T-04: UpdateTotalCopies ajusta disponibles con el delta positivo
     */
    public function test_update_total_copies_increases_available_by_delta(): void
    {
        $book = $this->createBook(5);
        $book->withdraw();
        $book->withdraw();
        
        $this->assertEquals(5, $book->totalCopies()->value());
        $this->assertEquals(3, $book->availableCopies()->value());
        
        // Aumentar total de 5 a 8 (delta = +3)
        $book->updateTotalCopies(new Quantity(8));
        
        $this->assertEquals(8, $book->totalCopies()->value());
        $this->assertEquals(6, $book->availableCopies()->value()); // 3 + 3
    }

    /**
     * T-04: UpdateTotalCopies ajusta disponibles con el delta negativo
     */
    public function test_update_total_copies_decreases_available_by_delta(): void
    {
        $book = $this->createBook(10);
        $book->withdraw();
        $book->withdraw();
        
        $this->assertEquals(10, $book->totalCopies()->value());
        $this->assertEquals(8, $book->availableCopies()->value());
        
        // Reducir total de 10 a 7 (delta = -3)
        $book->updateTotalCopies(new Quantity(7));
        
        $this->assertEquals(7, $book->totalCopies()->value());
        $this->assertEquals(5, $book->availableCopies()->value()); // 8 - 3
    }

    /**
     * T-04: UpdateTotalCopies no permite dejar disponibles negativos
     */
    public function test_update_total_copies_cannot_make_available_negative(): void
    {
        $this->expectException(InvalidBookOperationException::class);
        $this->expectExceptionMessage('No se puede reducir el total a 2 cuando hay 4 ejemplares disponibles');
        
        $book = $this->createBook(5);
        $book->withdraw();
        
        // Total=5, disponibles=4
        // Intentar reducir total a 2 haría disponibles = 4 + (2-5) = 1
        // Pero si prestados = 1, entonces 2 - 1 = 1 disponible (válido)
        // Probemos con más prestados:
        
        $book2 = $this->createBook(10);
        // Total=10, disponibles=10
        
        // Reducir total a 2 haría disponibles = 10 + (2-10) = 2
        $book2->updateTotalCopies(new Quantity(2));
        $this->assertEquals(2, $book2->availableCopies()->value());
        
        // Ahora prestamos los 2
        $book2->withdraw();
        $book2->withdraw();
        // Total=2, disponibles=0, prestados=2
        
        // Intentar reducir a 1 haría disponibles = 0 + (1-2) = -1 (inválido)
        $book2->updateTotalCopies(new Quantity(1));
    }

    /**
     * T-04: Baja lógica marca deletedAt
     */
    public function test_delete_marks_book_as_deleted(): void
    {
        $book = $this->createBook(5);
        $now = new DateTimeImmutable();
        
        $this->assertTrue($book->isActive());
        $this->assertNull($book->deletedAt());
        
        $book->delete($now);
        
        $this->assertFalse($book->isActive());
        $this->assertEquals($now, $book->deletedAt());
    }

    /**
     * T-04: Update actualiza título, autor y categoría
     */
    public function test_update_changes_title_author_and_category(): void
    {
        $book = $this->createBook(5);
        
        $book->update('Nuevo Título', 'Nuevo Autor', 'cat-2');
        
        $this->assertEquals('Nuevo Título', $book->title());
        $this->assertEquals('Nuevo Autor', $book->author());
        $this->assertEquals('cat-2', $book->categoryId());
    }

    /**
     * T-04: SetCover asigna coverKey
     */
    public function test_set_cover_assigns_cover_key(): void
    {
        $book = $this->createBook(5);
        
        $this->assertNull($book->coverKey());
        
        $book->setCover('covers/abc123.jpg');
        
        $this->assertEquals('covers/abc123.jpg', $book->coverKey());
    }

    /**
     * T-04: RemoveCover elimina coverKey
     */
    public function test_remove_cover_clears_cover_key(): void
    {
        $book = Book::create(
            'book-123',
            'Título',
            'Autor',
            new Isbn('9780307474728'),
            'cat-1',
            new Quantity(5),
            'covers/existing.jpg'
        );
        
        $this->assertEquals('covers/existing.jpg', $book->coverKey());
        
        $book->removeCover();
        
        $this->assertNull($book->coverKey());
    }

    /**
     * T-04: Reconstitute crea libro con estado específico
     */
    public function test_reconstitute_creates_book_with_specific_state(): void
    {
        $book = Book::reconstitute(
            id: 'book-123',
            title: 'Título',
            author: 'Autor',
            isbn: new Isbn('9780307474728'),
            categoryId: 'cat-1',
            totalCopies: new Quantity(10),
            availableCopies: new Quantity(7),
            coverKey: 'covers/test.jpg',
            deletedAt: null
        );
        
        $this->assertEquals(10, $book->totalCopies()->value());
        $this->assertEquals(7, $book->availableCopies()->value());
        $this->assertEquals('covers/test.jpg', $book->coverKey());
        $this->assertTrue($book->isActive());
    }

    /**
     * T-04: Invariante al reconstituir: disponibles no pueden exceder total
     */
    public function test_reconstitute_validates_copies_range(): void
    {
        $this->expectException(InvalidBookOperationException::class);
        
        Book::reconstitute(
            'book-123',
            'Título',
            'Autor',
            new Isbn('9780307474728'),
            'cat-1',
            new Quantity(5),
            new Quantity(8), // Disponibles > total (inválido)
            null,
            null
        );
    }
}
