<?php

namespace Tests\Unit\Application\Services;

use DateTimeImmutable;
use Library\Application\Exceptions\BookNotFoundException;
use Library\Application\Exceptions\MemberHasOverdueItemsException;
use Library\Application\Exceptions\MemberLimitExceededException;
use Library\Application\Exceptions\MemberNotFoundException;
use Library\Application\Services\LendBooksService;
use Library\Domain\Book;
use Library\Domain\Exceptions\BookNotAvailableException;
use Library\Domain\Exceptions\DuplicateBookInLoanException;
use Library\Domain\Loan;
use Library\Domain\Member;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Application\Fakes\FakeBookRepository;
use Tests\Unit\Application\Fakes\FakeCategoryRepository;
use Tests\Unit\Application\Fakes\FakeClock;
use Tests\Unit\Application\Fakes\FakeLoanRepository;
use Tests\Unit\Application\Fakes\FakeMemberRepository;

class LendBooksServiceTest extends TestCase
{
    private FakeMemberRepository $memberRepository;
    private FakeBookRepository $bookRepository;
    private FakeLoanRepository $loanRepository;
    private FakeCategoryRepository $categoryRepository;
    private FakeClock $clock;
    private LendBooksService $service;

    protected function setUp(): void
    {
        $this->memberRepository = new FakeMemberRepository();
        $this->bookRepository = new FakeBookRepository();
        $this->loanRepository = new FakeLoanRepository();
        $this->categoryRepository = new FakeCategoryRepository();
        $this->clock = new FakeClock(new DateTimeImmutable('2026-10-14 10:00:00'));
        
        $this->service = new LendBooksService(
            $this->memberRepository,
            $this->bookRepository,
            $this->loanRepository,
            $this->categoryRepository,
            $this->clock
        );
    }

    private function createMember(string $id, string $name): Member
    {
        $member = Member::create($id, $name, '12345678');
        $this->memberRepository->addMember($member);
        return $member;
    }

    private function createBook(string $id, string $title, int $totalCopies): Book
    {
        $book = Book::create(
            $id,
            $title,
            'Autor',
            new Isbn(str_pad($id, 13, '0')),
            '11111111-1111-4111-8111-111111111111', // Novela
            new Quantity($totalCopies)
        );
        $this->bookRepository->addBook($book);
        return $book;
    }

    /**
     * T-09 / HU-06 / CA-06.1: Un préstamo es de un socio, lo registra el usuario y trae 1 a 3 líneas
     */
    public function test_creates_loan_with_one_book(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book = $this->createBook('book-1', 'Cien años de soledad', 5);
        
        $loan = $this->service->lendBooks(
            memberId: 'member-1',
            bookIds: ['book-1'],
            loanedByUserId: 'user-1'
        );
        
        $this->assertNotNull($loan->id());
        $this->assertEquals('member-1', $loan->memberId());
        $this->assertEquals('user-1', $loan->loanedByUserId());
        $this->assertEquals(1, $loan->itemCount());
        
        // Verificar que se guardó
        $this->assertEquals(1, $this->loanRepository->count());
    }

    /**
     * T-09 / HU-06 / CA-06.1: Préstamo con 3 libros
     */
    public function test_creates_loan_with_three_books(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book1 = $this->createBook('book-1', 'Libro 1', 5);
        $book2 = $this->createBook('book-2', 'Libro 2', 3);
        $book3 = $this->createBook('book-3', 'Libro 3', 4);
        
        $loan = $this->service->lendBooks('member-1', ['book-1', 'book-2', 'book-3'], 'user-1');
        
        $this->assertEquals(3, $loan->itemCount());
    }

    /**
     * T-09 / HU-06 / CA-06.2: No se repite el mismo libro en un préstamo
     */
    public function test_rejects_duplicate_book_in_same_loan(): void
    {
        $this->expectException(DuplicateBookInLoanException::class);
        
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book = $this->createBook('book-1', 'Cien años de soledad', 5);
        
        $this->service->lendBooks('member-1', ['book-1', 'book-1'], 'user-1');
    }

    /**
     * T-09 / HU-06 / CA-06.3: Cada línea descuenta 1 ejemplar disponible
     */
    public function test_each_item_decreases_available_copies_by_one(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book = $this->createBook('book-1', 'Cien años de soledad', 5);
        
        $this->assertEquals(5, $book->availableCopies()->value());
        
        $this->service->lendBooks('member-1', ['book-1'], 'user-1');
        
        $bookAfter = $this->bookRepository->findById('book-1');
        $this->assertEquals(4, $bookAfter->availableCopies()->value());
    }

    /**
     * T-09 / HU-06 / CA-06.3: Sin ejemplares lanza excepción y no se registra nada
     */
    public function test_without_available_copies_throws_exception_and_saves_nothing(): void
    {
        $this->expectException(BookNotAvailableException::class);
        $this->expectExceptionMessage('No quedan ejemplares disponibles');
        
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book = $this->createBook('book-1', 'Cien años de soledad', 1);
        
        // Prestar el único ejemplar
        $this->service->lendBooks('member-1', ['book-1'], 'user-1');
        
        // Intentar prestar de nuevo (sin ejemplares)
        try {
            $this->service->lendBooks('member-1', ['book-1'], 'user-1');
        } catch (BookNotAvailableException $e) {
            // Verificar que no se guardó segundo préstamo
            $this->assertEquals(1, $this->loanRepository->count());
            throw $e;
        }
    }

    /**
     * T-09 / HU-06 / CA-06.3: Si falla al prestar el segundo libro, no se registra nada
     */
    public function test_if_second_book_fails_nothing_is_saved(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book1 = $this->createBook('book-1', 'Libro 1', 5);
        $book2 = $this->createBook('book-2', 'Libro 2', 1);
        
        // Prestar el único ejemplar del libro 2
        $this->service->lendBooks('member-1', ['book-2'], 'user-1');
        
        // Intentar prestar libro1 y libro2 (libro2 no tiene ejemplares)
        try {
            $this->service->lendBooks('member-1', ['book-1', 'book-2'], 'user-1');
            $this->fail('Should throw BookNotAvailableException');
        } catch (BookNotAvailableException $e) {
            // Verificar que libro1 no se prestó (transacción completa o nada)
            $book1After = $this->bookRepository->findById('book-1');
            $this->assertEquals(5, $book1After->availableCopies()->value());
            
            // Solo hay 1 préstamo (el primero)
            $this->assertEquals(1, $this->loanRepository->count());
        }
    }

    /**
     * T-09 / HU-06 / CA-06.4: Un socio no puede tener más de 3 libros sin devolver
     */
    public function test_member_cannot_have_more_than_3_unreturned_items(): void
    {
        $this->expectException(MemberLimitExceededException::class);
        $this->expectExceptionMessage('excedería el máximo de 3 libros');
        
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book1 = $this->createBook('book-1', 'Libro 1', 5);
        $book2 = $this->createBook('book-2', 'Libro 2', 5);
        $book3 = $this->createBook('book-3', 'Libro 3', 5);
        $book4 = $this->createBook('book-4', 'Libro 4', 5);
        
        // Prestar 3 libros
        $this->service->lendBooks('member-1', ['book-1', 'book-2', 'book-3'], 'user-1');
        
        // Intentar prestar un cuarto
        $this->service->lendBooks('member-1', ['book-4'], 'user-1');
    }

    /**
     * T-09 / HU-06 / CA-06.4: Si tiene 2 sin devolver, puede pedir solo 1 más
     */
    public function test_member_with_2_unreturned_can_borrow_only_1_more(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book1 = $this->createBook('book-1', 'Libro 1', 5);
        $book2 = $this->createBook('book-2', 'Libro 2', 5);
        $book3 = $this->createBook('book-3', 'Libro 3', 5);
        
        // Prestar 2 libros
        $this->service->lendBooks('member-1', ['book-1', 'book-2'], 'user-1');
        
        // Puede pedir 1 más
        $loan2 = $this->service->lendBooks('member-1', ['book-3'], 'user-1');
        $this->assertNotNull($loan2);
        
        // Total: 3 sin devolver
        $this->assertEquals(3, $this->loanRepository->countUnreturnedItemsForMember('member-1'));
    }

    /**
     * T-09 / HU-06 / CA-06.5: Un socio con algún libro vencido no puede pedir otro
     */
    public function test_member_with_overdue_items_cannot_borrow(): void
    {
        $this->expectException(MemberHasOverdueItemsException::class);
        $this->expectExceptionMessage('tiene libros vencidos sin devolver');
        
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book1 = $this->createBook('book-1', 'Libro 1', 5);
        $book2 = $this->createBook('book-2', 'Libro 2', 5);
        
        // Prestar libro
        $loan = $this->service->lendBooks('member-1', ['book-1'], 'user-1');
        $this->loanRepository->addLoan($loan);
        
        // Avanzar tiempo más de 14 días (vencido)
        $this->clock->setNow(new DateTimeImmutable('2026-10-29 10:00:00')); // 15 días después
        
        // Intentar prestar otro libro
        $this->service->lendBooks('member-1', ['book-2'], 'user-1');
    }

    /**
     * T-09 / HU-06 / CA-06.6: La fecha de vencimiento es loaned_at + 14 días
     */
    public function test_due_date_is_14_days_from_loan_date(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book = $this->createBook('book-1', 'Cien años de soledad', 5);
        
        $loan = $this->service->lendBooks('member-1', ['book-1'], 'user-1');
        
        $items = $loan->items();
        $expectedDue = new DateTimeImmutable('2026-10-28 10:00:00'); // 14 días después
        
        $this->assertEquals($expectedDue, $items[0]->dueAt());
    }

    /**
     * T-09 / HU-06 / CA-06.6: La línea congela título, ISBN y categoría
     */
    public function test_loan_item_freezes_book_title_isbn_and_category(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book = $this->createBook('book-1', 'Cien años de soledad', 5);
        
        $loan = $this->service->lendBooks('member-1', ['book-1'], 'user-1');
        
        $items = $loan->items();
        $this->assertEquals('Cien años de soledad', $items[0]->bookTitle());
        $this->assertEquals(str_pad('book-1', 13, '0'), $items[0]->isbn()->value());
        $this->assertEquals('Novela', $items[0]->categoryName()); // Categoría congelada
    }

    /**
     * T-09 / HU-06: Rechaza socio inexistente
     */
    public function test_rejects_nonexistent_member(): void
    {
        $this->expectException(MemberNotFoundException::class);
        
        $book = $this->createBook('book-1', 'Libro', 5);
        
        $this->service->lendBooks('nonexistent-member', ['book-1'], 'user-1');
    }

    /**
     * T-09 / HU-06: Rechaza libro inexistente
     */
    public function test_rejects_nonexistent_book(): void
    {
        $this->expectException(BookNotFoundException::class);
        
        $member = $this->createMember('member-1', 'Juan Pérez');
        
        $this->service->lendBooks('member-1', ['nonexistent-book'], 'user-1');
    }

    /**
     * T-09 / HU-06: Múltiples socios pueden prestar al mismo tiempo
     */
    public function test_multiple_members_can_borrow_simultaneously(): void
    {
        $member1 = $this->createMember('member-1', 'Juan Pérez');
        $member2 = $this->createMember('member-2', 'Ana López');
        $book = $this->createBook('book-1', 'Libro', 10);
        
        $loan1 = $this->service->lendBooks('member-1', ['book-1'], 'user-1');
        $loan2 = $this->service->lendBooks('member-2', ['book-1'], 'user-1');
        
        $this->assertNotEquals($loan1->id(), $loan2->id());
        $this->assertEquals(2, $this->loanRepository->count());
        
        $bookAfter = $this->bookRepository->findById('book-1');
        $this->assertEquals(8, $bookAfter->availableCopies()->value());
    }
}
