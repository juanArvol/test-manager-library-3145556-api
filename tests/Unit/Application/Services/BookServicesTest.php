<?php

namespace Tests\Unit\Application\Services;

use DateTimeImmutable;
use Library\Application\Exceptions\BookHasOpenLoansException;
use Library\Application\Exceptions\BookNotFoundException;
use Library\Application\Exceptions\IsbnAlreadyExistsException;
use Library\Application\Services\CreateBookService;
use Library\Application\Services\DeleteBookService;
use Library\Application\Services\GetBookService;
use Library\Application\Services\SearchBooksService;
use Library\Application\Services\UpdateBookService;
use Library\Domain\Book;
use Library\Domain\Exceptions\InvalidBookOperationException;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Application\Fakes\FakeBookRepository;
use Tests\Unit\Application\Fakes\FakeClock;

class BookServicesTest extends TestCase
{
    private FakeBookRepository $bookRepository;
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->bookRepository = new FakeBookRepository();
        $this->clock = new FakeClock(new DateTimeImmutable('2026-10-14 10:00:00'));
    }

    // ==================== HU-03: Gestionar libros ====================

    /**
     * T-08 / HU-03 / CA-03.1: Crear libro con título, autor, ISBN, categoría y total
     */
    public function test_creates_book_with_all_required_data(): void
    {
        $service = new CreateBookService($this->bookRepository);
        
        $book = $service->createBook(
            title: 'Cien años de soledad',
            author: 'Gabriel García Márquez',
            isbn: '9780307474728',
            categoryId: 'cat-1',
            totalCopies: 5
        );
        
        $this->assertNotNull($book->id());
        $this->assertEquals('Cien años de soledad', $book->title());
        $this->assertEquals('Gabriel García Márquez', $book->author());
        $this->assertEquals('9780307474728', $book->isbn()->value());
        $this->assertEquals('cat-1', $book->categoryId());
        $this->assertEquals(5, $book->totalCopies()->value());
        $this->assertEquals(5, $book->availableCopies()->value()); // Disponibles = total al crear
        
        // Verificar que se guardó
        $this->assertEquals(1, $this->bookRepository->count());
    }

    /**
     * T-08 / HU-03 / CA-03.2: ISBN de 13 dígitos y único entre libros activos
     */
    public function test_rejects_duplicate_isbn_among_active_books(): void
    {
        $this->expectException(IsbnAlreadyExistsException::class);
        $this->expectExceptionMessage("Ya existe un libro activo con el ISBN '9780307474728'");
        
        $service = new CreateBookService($this->bookRepository);
        
        // Crear primer libro
        $service->createBook('Libro 1', 'Autor 1', '9780307474728', 'cat-1', 5);
        
        // Intentar crear otro con el mismo ISBN
        $service->createBook('Libro 2', 'Autor 2', '9780307474728', 'cat-1', 3);
    }

    /**
     * T-08 / HU-03 / CA-03.2: Permite reutilizar ISBN de libro dado de baja
     */
    public function test_allows_reusing_isbn_from_deleted_book(): void
    {
        $service = new CreateBookService($this->bookRepository);
        $deleteService = new DeleteBookService($this->bookRepository, $this->clock);
        
        // Crear y dar de baja un libro
        $book1 = $service->createBook('Libro 1', 'Autor 1', '9780307474728', 'cat-1', 5);
        $deleteService->deleteBook($book1->id());
        
        // Ahora sí se puede crear otro con el mismo ISBN
        $book2 = $service->createBook('Libro 2', 'Autor 2', '9780307474728', 'cat-1', 3);
        
        $this->assertNotEquals($book1->id(), $book2->id());
        $this->assertEquals('9780307474728', $book2->isbn()->value());
    }

    /**
     * T-08 / HU-03 / CA-03.3: Total de ejemplares >= 1
     */
    public function test_rejects_book_with_zero_total_copies(): void
    {
        $this->expectException(InvalidBookOperationException::class);
        
        $service = new CreateBookService($this->bookRepository);
        $service->createBook('Libro', 'Autor', '9780307474728', 'cat-1', 0);
    }

    /**
     * T-08 / HU-03 / CA-03.3: Cambiar el total ajusta los disponibles en la misma diferencia
     */
    public function test_update_total_adjusts_available_by_delta(): void
    {
        $createService = new CreateBookService($this->bookRepository);
        $updateService = new UpdateBookService($this->bookRepository);
        
        // Crear libro con 10 ejemplares
        $book = $createService->createBook('Libro', 'Autor', '9780307474728', 'cat-1', 10);
        
        // Simular que se prestaron 3 (disponibles = 7)
        $bookFromRepo = $this->bookRepository->findById($book->id());
        $bookFromRepo->withdraw();
        $bookFromRepo->withdraw();
        $bookFromRepo->withdraw();
        $this->bookRepository->save($bookFromRepo);
        
        // Aumentar total de 10 a 15 (delta = +5)
        $updateService->updateBook($book->id(), 'Libro', 'Autor', 'cat-1', 15);
        
        $updated = $this->bookRepository->findById($book->id());
        $this->assertEquals(15, $updated->totalCopies()->value());
        $this->assertEquals(12, $updated->availableCopies()->value()); // 7 + 5
    }

    /**
     * T-08 / HU-03 / CA-03.3: Nunca deja disponibles < 0
     */
    public function test_update_total_never_leaves_available_negative(): void
    {
        $this->expectException(InvalidBookOperationException::class);
        
        $createService = new CreateBookService($this->bookRepository);
        $updateService = new UpdateBookService($this->bookRepository);
        
        $book = $createService->createBook('Libro', 'Autor', '9780307474728', 'cat-1', 10);
        
        // Prestar todos
        $bookFromRepo = $this->bookRepository->findById($book->id());
        for ($i = 0; $i < 10; $i++) {
            $bookFromRepo->withdraw();
        }
        $this->bookRepository->save($bookFromRepo);
        
        // Intentar reducir total a 5 haría disponibles = 0 + (5-10) = -5 (inválido)
        $updateService->updateBook($book->id(), 'Libro', 'Autor', 'cat-1', 5);
    }

    /**
     * T-08 / HU-03 / CA-03.4: Subir portada guarda clave opaca
     */
    public function test_creates_book_with_cover_key(): void
    {
        $service = new CreateBookService($this->bookRepository);
        
        $book = $service->createBook(
            'Libro',
            'Autor',
            '9780307474728',
            'cat-1',
            5,
            'covers/abc123.jpg'
        );
        
        $this->assertEquals('covers/abc123.jpg', $book->coverKey());
    }

    /**
     * T-08 / HU-03 / CA-03.5: Eliminar es baja lógica
     */
    public function test_delete_is_soft_delete(): void
    {
        $createService = new CreateBookService($this->bookRepository);
        $deleteService = new DeleteBookService($this->bookRepository, $this->clock);
        
        $book = $createService->createBook('Libro', 'Autor', '9780307474728', 'cat-1', 5);
        
        $this->assertTrue($book->isActive());
        
        $deleteService->deleteBook($book->id());
        
        // No aparece en búsqueda de activos
        $this->assertNull($this->bookRepository->findById($book->id()));
        
        // Pero sigue en la base (incluido en borrados)
        $deleted = $this->bookRepository->findByIdIncludingDeleted($book->id());
        $this->assertNotNull($deleted);
        $this->assertFalse($deleted->isActive());
        $this->assertNotNull($deleted->deletedAt());
    }

    /**
     * T-08 / HU-03 / CA-03.5: Libro con préstamos abiertos no se da de baja
     */
    public function test_cannot_delete_book_with_open_loans(): void
    {
        $this->expectException(BookHasOpenLoansException::class);
        $this->expectExceptionMessage('tiene préstamos abiertos');
        
        $createService = new CreateBookService($this->bookRepository);
        $deleteService = new DeleteBookService($this->bookRepository, $this->clock);
        
        $book = $createService->createBook('Libro', 'Autor', '9780307474728', 'cat-1', 5);
        
        // Marcar que tiene préstamos abiertos
        $this->bookRepository->setHasOpenLoans($book->id(), true);
        
        $deleteService->deleteBook($book->id());
    }

    // ==================== HU-04: Buscar libros ====================

    /**
     * T-08 / HU-04 / CA-04.1: Búsqueda por texto en título o autor
     */
    public function test_searches_books_by_text_in_title_or_author(): void
    {
        $createService = new CreateBookService($this->bookRepository);
        $searchService = new SearchBooksService($this->bookRepository);
        
        $createService->createBook('Cien años de soledad', 'Gabriel García Márquez', '1111111111111', 'cat-1', 5);
        $createService->createBook('El amor en los tiempos del cólera', 'Gabriel García Márquez', '2222222222222', 'cat-1', 3);
        $createService->createBook('Don Quijote', 'Miguel de Cervantes', '3333333333333', 'cat-2', 4);
        
        // Buscar por "García" (en autor)
        $result = $searchService->search('García', null, 1, 20);
        
        $this->assertEquals(2, $result['total']);
        $this->assertCount(2, $result['items']);
    }

    /**
     * T-08 / HU-04 / CA-04.1: Filtro por categoría
     */
    public function test_searches_books_by_category(): void
    {
        $createService = new CreateBookService($this->bookRepository);
        $searchService = new SearchBooksService($this->bookRepository);
        
        $createService->createBook('Libro 1', 'Autor', '1111111111111', 'cat-1', 5);
        $createService->createBook('Libro 2', 'Autor', '2222222222222', 'cat-1', 3);
        $createService->createBook('Libro 3', 'Autor', '3333333333333', 'cat-2', 4);
        
        $result = $searchService->search(null, 'cat-1', 1, 20);
        
        $this->assertEquals(2, $result['total']);
    }

    /**
     * T-08 / HU-04 / CA-04.1: Solo libros activos
     */
    public function test_searches_only_active_books(): void
    {
        $createService = new CreateBookService($this->bookRepository);
        $deleteService = new DeleteBookService($this->bookRepository, $this->clock);
        $searchService = new SearchBooksService($this->bookRepository);
        
        $book1 = $createService->createBook('Libro 1', 'Autor', '1111111111111', 'cat-1', 5);
        $book2 = $createService->createBook('Libro 2', 'Autor', '2222222222222', 'cat-1', 3);
        
        // Dar de baja uno
        $deleteService->deleteBook($book1->id());
        
        $result = $searchService->search(null, null, 1, 20);
        
        $this->assertEquals(1, $result['total']);
        $this->assertEquals('Libro 2', $result['items'][0]->title());
    }

    /**
     * T-08 / HU-04 / CA-04.1: Orden por título, paginado
     */
    public function test_searches_books_ordered_by_title_paginated(): void
    {
        $createService = new CreateBookService($this->bookRepository);
        $searchService = new SearchBooksService($this->bookRepository);
        
        $createService->createBook('Zebra', 'Autor', '1111111111111', 'cat-1', 5);
        $createService->createBook('Alpha', 'Autor', '2222222222222', 'cat-1', 3);
        $createService->createBook('Beta', 'Autor', '3333333333333', 'cat-1', 4);
        
        $result = $searchService->search(null, null, 1, 2);
        
        $this->assertEquals(3, $result['total']);
        $this->assertCount(2, $result['items']);
        $this->assertEquals('Alpha', $result['items'][0]->title());
        $this->assertEquals('Beta', $result['items'][1]->title());
    }

    /**
     * T-08 / HU-04 / CA-04.2: Respuesta incluye total de resultados
     */
    public function test_search_response_includes_total(): void
    {
        $createService = new CreateBookService($this->bookRepository);
        $searchService = new SearchBooksService($this->bookRepository);
        
        for ($i = 1; $i <= 25; $i++) {
            $createService->createBook("Libro {$i}", 'Autor', str_pad($i, 13, '0', STR_PAD_LEFT), 'cat-1', 5);
        }
        
        $result = $searchService->search(null, null, 1, 10);
        
        $this->assertEquals(25, $result['total']);
        $this->assertCount(10, $result['items']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(10, $result['size']);
    }

    /**
     * T-08 / HU-04 / CA-04.3: Consultar libro inexistente → 404
     */
    public function test_get_book_throws_not_found_for_nonexistent_id(): void
    {
        $this->expectException(BookNotFoundException::class);
        
        $service = new GetBookService($this->bookRepository);
        $service->getBook('nonexistent-id');
    }

    /**
     * T-08 / HU-04 / CA-04.3: Consultar libro dado de baja → 404
     */
    public function test_get_book_throws_not_found_for_deleted_book(): void
    {
        $this->expectException(BookNotFoundException::class);
        
        $createService = new CreateBookService($this->bookRepository);
        $deleteService = new DeleteBookService($this->bookRepository, $this->clock);
        $getService = new GetBookService($this->bookRepository);
        
        $book = $createService->createBook('Libro', 'Autor', '9780307474728', 'cat-1', 5);
        $deleteService->deleteBook($book->id());
        
        $getService->getBook($book->id());
    }
}
