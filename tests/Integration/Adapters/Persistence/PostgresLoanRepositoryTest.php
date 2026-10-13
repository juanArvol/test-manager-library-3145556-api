<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Library\Adapters\Persistence\PostgresBookRepository;
use Library\Adapters\Persistence\PostgresLoanRepository;
use Library\Adapters\Persistence\PostgresMemberRepository;
use Library\Adapters\Persistence\PostgresUserRepository;
use Library\Application\Exceptions\ConcurrencyException;
use Library\Domain\Book;
use Library\Domain\Loan;
use Library\Domain\Member;
use Library\Domain\User;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use Tests\TestCase;

final class PostgresLoanRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PostgresLoanRepository $repository;
    private PostgresBookRepository $bookRepo;
    private PostgresMemberRepository $memberRepo;
    private PostgresUserRepository $userRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PostgresLoanRepository();
        $this->bookRepo = new PostgresBookRepository();
        $this->memberRepo = new PostgresMemberRepository();
        $this->userRepo = new PostgresUserRepository();

        // Create test category
        DB::table('category')->insert([
            'category_id' => 'cat-test',
            'name' => 'Test Category',
            'created_at' => now(),
        ]);
    }

    /** @test */
    public function it_saves_and_retrieves_a_loan_with_items(): void
    {
        $member = new Member(
            $this->memberRepo->nextIdentity(),
            'John Doe',
            'john@example.com'
        );
        $this->memberRepo->save($member);

        $user = new User(
            $this->userRepo->nextIdentity(),
            'librarian',
            'hash',
            'librarian'
        );
        $this->userRepo->save($user);

        $book = Book::create(
            $this->bookRepo->nextIdentity(),
            'Clean Code',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(5),
            null
        );
        $this->bookRepo->save($book);

        // Create loan
        $loanId = $this->repository->nextIdentity();
        $loan = Loan::create(
            $loanId,
            $member->getId(),
            $user->getId(),
            new \DateTimeImmutable('2025-06-01 10:00:00')
        );

        $itemId = $this->repository->nextIdentity();
        $loan->addItem($itemId, $book, 'Test Category');

        $this->repository->save($loan);

        // Retrieve
        $retrieved = $this->repository->findById($loanId);

        $this->assertNotNull($retrieved);
        $this->assertSame($loanId, $retrieved->id());
        $this->assertSame($member->getId(), $retrieved->memberId());
        $this->assertSame($user->getId(), $retrieved->loanedByUserId());
        $this->assertCount(1, $retrieved->items());
        $this->assertSame('Clean Code', $retrieved->items()[0]->bookTitle());
    }

    /** @test */
    public function it_throws_concurrency_exception_when_version_mismatch(): void
    {
        $member = new Member(
            $this->memberRepo->nextIdentity(),
            'John Doe',
            'john@example.com'
        );
        $this->memberRepo->save($member);

        $user = new User(
            $this->userRepo->nextIdentity(),
            'librarian',
            'hash',
            'librarian'
        );
        $this->userRepo->save($user);

        $book = Book::create(
            $this->bookRepo->nextIdentity(),
            'Book One',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(5),
            null
        );
        $this->bookRepo->save($book);

        // Create and save loan
        $loanId = $this->repository->nextIdentity();
        $loan = Loan::create(
            $loanId,
            $member->getId(),
            $user->getId(),
            new \DateTimeImmutable('2025-06-01 10:00:00')
        );

        $itemId = $this->repository->nextIdentity();
        $loan->addItem($itemId, $book, 'Test Category');
        $this->repository->save($loan);

        // Simulate two concurrent processes
        $loan1 = $this->repository->findById($loanId);
        $loan2 = $this->repository->findById($loanId);

        $this->assertSame(0, $loan1->version());
        $this->assertSame(0, $loan2->version());

        // First save succeeds (version 0 -> 1)
        $this->repository->save($loan1);

        // Second save fails (still has version 0, but DB has version 1)
        $this->expectException(ConcurrencyException::class);
        $this->expectExceptionMessage('El préstamo fue modificado por otro proceso');

        $this->repository->save($loan2);
    }

    /** @test */
    public function it_simulates_two_loans_of_last_book_copy(): void
    {
        $member = new Member(
            $this->memberRepo->nextIdentity(),
            'John Doe',
            'john@example.com'
        );
        $this->memberRepo->save($member);

        $user = new User(
            $this->userRepo->nextIdentity(),
            'librarian',
            'hash',
            'librarian'
        );
        $this->userRepo->save($user);

        // Book with only 1 copy
        $bookId = $this->bookRepo->nextIdentity();
        $book = Book::create(
            $bookId,
            'Last Copy',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(1),
            null
        );
        $this->bookRepo->save($book);

        // Process 1: Load book, create loan
        $book1 = $this->bookRepo->findById($bookId);
        $this->assertSame(1, $book1->getAvailableQuantity()->getValue());

        $book1->withdraw(new Quantity(1));

        $loan1Id = $this->repository->nextIdentity();
        $loan1 = Loan::create(
            $loan1Id,
            $member->getId(),
            $user->getId(),
            new \DateTimeImmutable('2025-06-01 10:00:00')
        );
        $loan1->addItem($this->repository->nextIdentity(), $book1, 'Test Category');

        // Process 2: Load book BEFORE process 1 saves, create loan
        $book2 = $this->bookRepo->findById($bookId);
        $this->assertSame(1, $book2->getAvailableQuantity()->getValue());

        $book2->withdraw(new Quantity(1));

        $loan2Id = $this->repository->nextIdentity();
        $loan2 = Loan::create(
            $loan2Id,
            $member->getId(),
            $user->getId(),
            new \DateTimeImmutable('2025-06-01 10:01:00')
        );
        $loan2->addItem($this->repository->nextIdentity(), $book2, 'Test Category');

        // Process 1 saves successfully (201)
        $this->bookRepo->save($book1);
        $this->repository->save($loan1);

        // Verify book now has 0 available
        $bookAfterP1 = $this->bookRepo->findById($bookId);
        $this->assertSame(0, $bookAfterP1->getAvailableQuantity()->getValue());

        // Process 2 tries to save - would fail at business level (409)
        // Book should fail withdraw since available is now 0
        $this->expectException(\InvalidArgumentException::class);
        $this->bookRepo->save($book2);
    }

    /** @test */
    public function it_finds_loans_by_member_id(): void
    {
        $member = new Member(
            $this->memberRepo->nextIdentity(),
            'John Doe',
            'john@example.com'
        );
        $this->memberRepo->save($member);

        $user = new User(
            $this->userRepo->nextIdentity(),
            'librarian',
            'hash',
            'librarian'
        );
        $this->userRepo->save($user);

        // Create 2 loans for same member
        for ($i = 1; $i <= 2; $i++) {
            $loan = Loan::create(
                $this->repository->nextIdentity(),
                $member->getId(),
                $user->getId(),
                new \DateTimeImmutable("2025-06-0$i 10:00:00")
            );
            $this->repository->save($loan);
        }

        $loans = $this->repository->findByMemberId($member->getId());

        $this->assertCount(2, $loans);
    }

    /** @test */
    public function it_updates_loan_items_on_save(): void
    {
        $member = new Member(
            $this->memberRepo->nextIdentity(),
            'John Doe',
            'john@example.com'
        );
        $this->memberRepo->save($member);

        $user = new User(
            $this->userRepo->nextIdentity(),
            'librarian',
            'hash',
            'librarian'
        );
        $this->userRepo->save($user);

        $book = Book::create(
            $this->bookRepo->nextIdentity(),
            'Clean Code',
            new Isbn('9780132350884'),
            'cat-test',
            new Quantity(5),
            null
        );
        $this->bookRepo->save($book);

        // Create loan with one item
        $loanId = $this->repository->nextIdentity();
        $loan = Loan::create(
            $loanId,
            $member->getId(),
            $user->getId(),
            new \DateTimeImmutable('2025-06-01 10:00:00')
        );

        $itemId = $this->repository->nextIdentity();
        $loan->addItem($itemId, $book, 'Test Category');
        $this->repository->save($loan);

        // Return the item
        $loan = $this->repository->findById($loanId);
        $loan->returnItem($itemId, new \DateTimeImmutable('2025-06-10 10:00:00'));
        $this->repository->save($loan);

        // Verify
        $retrieved = $this->repository->findById($loanId);
        $this->assertTrue($retrieved->items()[0]->isReturned());
    }

    /** @test */
    public function it_returns_null_when_loan_not_found(): void
    {
        $retrieved = $this->repository->findById('non-existent');

        $this->assertNull($retrieved);
    }
}
