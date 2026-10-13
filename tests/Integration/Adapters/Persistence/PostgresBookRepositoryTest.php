<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Library\Adapters\Persistence\PostgresBookRepository;
use Library\Domain\Book;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use Tests\TestCase;

final class PostgresBookRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PostgresBookRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PostgresBookRepository();

        // Create a test category
        DB::table('category')->insert([
            'category_id' => 'cat-test',
            'name' => 'Test Category',
            'created_at' => now(),
        ]);
    }

    /** @test */
    public function it_saves_and_retrieves_a_book(): void
    {
        $bookId = $this->repository->nextIdentity();
        $book = Book::create(
            $bookId,
            'Clean Code',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(5),
            null
        );

        $this->repository->save($book);

        $retrieved = $this->repository->findById($bookId);

        $this->assertNotNull($retrieved);
        $this->assertSame($bookId, $retrieved->getId());
        $this->assertSame('Clean Code', $retrieved->getTitle());
        $this->assertSame('9780132350884', $retrieved->getIsbn()->getValue());
        $this->assertSame(5, $retrieved->getTotalQuantity()->getValue());
        $this->assertSame(5, $retrieved->getAvailableQuantity()->getValue());
        $this->assertNull($retrieved->getCoverKey());
        $this->assertFalse($retrieved->isDeleted());
    }

    /** @test */
    public function it_updates_an_existing_book(): void
    {
        $bookId = $this->repository->nextIdentity();
        $book = Book::create(
            $bookId,
            'Original Title',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(3),
            null
        );

        $this->repository->save($book);

        // Modify and save again
        $book = $this->repository->findById($bookId);
        $book->withdraw(new Quantity(2));
        $this->repository->save($book);

        $retrieved = $this->repository->findById($bookId);

        $this->assertSame(3, $retrieved->getTotalQuantity()->getValue());
        $this->assertSame(1, $retrieved->getAvailableQuantity()->getValue());
    }

    /** @test */
    public function it_finds_book_by_isbn(): void
    {
        $isbn = new Isbn('9780132350884');
        $book = Book::create(
            $this->repository->nextIdentity(),
            'Clean Code',
            $isbn,
            'cat-test',
            new Quantity(5),
            null
        );

        $this->repository->save($book);

        $retrieved = $this->repository->findByIsbn($isbn);

        $this->assertNotNull($retrieved);
        $this->assertSame('9780132350884', $retrieved->getIsbn()->getValue());
    }

    /** @test */
    public function it_returns_null_when_book_not_found(): void
    {
        $retrieved = $this->repository->findById('non-existent');

        $this->assertNull($retrieved);
    }

    /** @test */
    public function it_searches_books_by_title(): void
    {
        $book1 = Book::create(
            $this->repository->nextIdentity(),
            'Clean Code',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(3),
            null
        );

        $book2 = Book::create(
            $this->repository->nextIdentity(),
            'Clean Architecture',
            new Isbn('9780134494166'),
            'cat-test',
            new Quantity(2),
            null
        );

        $book3 = Book::create(
            $this->repository->nextIdentity(),
            'Refactoring',
            new Isbn('9780201485677'),
            'cat-test',
            new Quantity(1),
            null
        );

        $this->repository->save($book1);
        $this->repository->save($book2);
        $this->repository->save($book3);

        $results = $this->repository->search('clean', 0, 10);

        $this->assertCount(2, $results);
        $titles = array_map(fn($b) => $b->getTitle(), $results);
        $this->assertContains('Clean Architecture', $titles);
        $this->assertContains('Clean Code', $titles);
    }

    /** @test */
    public function it_searches_books_by_isbn(): void
    {
        $book = Book::create(
            $this->repository->nextIdentity(),
            'Clean Code',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(3),
            null
        );

        $this->repository->save($book);

        $results = $this->repository->search('9780132350884', 0, 10);

        $this->assertCount(1, $results);
        $this->assertSame('9780132350884', $results[0]->getIsbn()->getValue());
    }

    /** @test */
    public function it_excludes_deleted_books_from_search(): void
    {
        $book1 = Book::create(
            $this->repository->nextIdentity(),
            'Clean Code',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(3),
            null
        );

        $book2 = Book::create(
            $this->repository->nextIdentity(),
            'Clean Architecture',
            new Isbn('9780134494166'),
            'cat-test',
            new Quantity(2),
            null
        );

        $book1->delete();

        $this->repository->save($book1);
        $this->repository->save($book2);

        $results = $this->repository->search('clean', 0, 10);

        $this->assertCount(1, $results);
        $this->assertSame('Clean Architecture', $results[0]->getTitle());
    }

    /** @test */
    public function it_paginates_search_results(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $book = Book::create(
                $this->repository->nextIdentity(),
                "Book $i",
                new Isbn('978000000000' . $i),
                'cat-test',
                new Quantity(1),
                null
            );
            $this->repository->save($book);
        }

        $page1 = $this->repository->search('book', 0, 2);
        $page2 = $this->repository->search('book', 2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertNotSame($page1[0]->getId(), $page2[0]->getId());
    }

    /** @test */
    public function it_preserves_withdrawn_state(): void
    {
        $bookId = $this->repository->nextIdentity();
        $book = Book::create(
            $bookId,
            'Test Book',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(10),
            null
        );

        $book->withdraw(new Quantity(7));
        $this->repository->save($book);

        $retrieved = $this->repository->findById($bookId);

        $this->assertSame(10, $retrieved->getTotalQuantity()->getValue());
        $this->assertSame(3, $retrieved->getAvailableQuantity()->getValue());
    }

    /** @test */
    public function it_preserves_deleted_state(): void
    {
        $bookId = $this->repository->nextIdentity();
        $book = Book::create(
            $bookId,
            'Test Book',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(5),
            null
        );

        $book->delete();
        $this->repository->save($book);

        $retrieved = $this->repository->findById($bookId);

        $this->assertTrue($retrieved->isDeleted());
    }

    /** @test */
    public function it_preserves_cover_key(): void
    {
        $bookId = $this->repository->nextIdentity();
        $book = Book::create(
            $bookId,
            'Test Book',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(5),
            'covers/test-key.png'
        );

        $this->repository->save($book);

        $retrieved = $this->repository->findById($bookId);

        $this->assertSame('covers/test-key.png', $retrieved->getCoverKey());
    }
}
