<?php

namespace Tests\Unit\Application\Services;

use DateTimeImmutable;
use Library\Application\Exceptions\LoanItemNotFoundException;
use Library\Application\Exceptions\LoanNotFoundException;
use Library\Application\Services\GetOverdueItemsService;
use Library\Application\Services\LendBooksService;
use Library\Application\Services\ReturnItemService;
use Library\Domain\Book;
use Library\Domain\Exceptions\LoanItemAlreadyReturnedException;
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

class ReturnAndOverdueServicesTest extends TestCase
{
    private FakeMemberRepository $memberRepository;
    private FakeBookRepository $bookRepository;
    private FakeLoanRepository $loanRepository;
    private FakeCategoryRepository $categoryRepository;
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->memberRepository = new FakeMemberRepository();
        $this->bookRepository = new FakeBookRepository();
        $this->loanRepository = new FakeLoanRepository();
        $this->categoryRepository = new FakeCategoryRepository();
        $this->clock = new FakeClock(new DateTimeImmutable('2026-10-14 10:00:00'));
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
            '11111111-1111-4111-8111-111111111111',
            new Quantity($totalCopies)
        );
        $this->bookRepository->addBook($book);
        return $book;
    }

    private function createLoan(): Loan
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book = $this->createBook('book-1', 'Cien años de soledad', 5);
        
        $lendService = new LendBooksService(
            $this->memberRepository,
            $this->bookRepository,
            $this->loanRepository,
            $this->categoryRepository,
            $this->clock
        );
        
        return $lendService->lendBooks('member-1', ['book-1'], 'user-1');
    }

    // ==================== HU-07: Registrar devolución ====================

    /**
     * T-10 / HU-07 / CA-07.1: Se devuelve una línea, pone returned_at y suma 1 a disponibles
     */
    public function test_return_item_marks_as_returned_and_increases_available(): void
    {
        $loan = $this->createLoan();
        $item = $loan->items()[0];
        
        // Verificar estado inicial
        $this->assertNull($item->returnedAt());
        $book = $this->bookRepository->findById('book-1');
        $this->assertEquals(4, $book->availableCopies()->value()); // 5 - 1
        
        // Devolver
        $returnService = new ReturnItemService(
            $this->loanRepository,
            $this->bookRepository,
            $this->clock
        );
        
        $returnService->returnItem($loan->id(), $item->id());
        
        // Verificar que se marcó como devuelto
        $loanAfter = $this->loanRepository->findById($loan->id());
        $itemAfter = $loanAfter->items()[0];
        $this->assertNotNull($itemAfter->returnedAt());
        $this->assertEquals($this->clock->now(), $itemAfter->returnedAt());
        
        // Verificar que aumentó disponibles
        $bookAfter = $this->bookRepository->findById('book-1');
        $this->assertEquals(5, $bookAfter->availableCopies()->value()); // 4 + 1
    }

    /**
     * T-10 / HU-07 / CA-07.2: Devolver una línea ya devuelta lanza excepción
     */
    public function test_return_already_returned_item_throws_exception(): void
    {
        $this->expectException(LoanItemAlreadyReturnedException::class);
        $this->expectExceptionMessage('ya fue devuelto');
        
        $loan = $this->createLoan();
        $item = $loan->items()[0];
        
        $returnService = new ReturnItemService(
            $this->loanRepository,
            $this->bookRepository,
            $this->clock
        );
        
        // Devolver primera vez
        $returnService->returnItem($loan->id(), $item->id());
        
        // Intentar devolver segunda vez
        $returnService->returnItem($loan->id(), $item->id());
    }

    /**
     * T-10 / HU-07 / CA-07.2: Nunca suma dos veces (verificación adicional)
     */
    public function test_never_adds_available_copies_twice(): void
    {
        $loan = $this->createLoan();
        $item = $loan->items()[0];
        
        $returnService = new ReturnItemService(
            $this->loanRepository,
            $this->bookRepository,
            $this->clock
        );
        
        // Devolver
        $returnService->returnItem($loan->id(), $item->id());
        
        $bookAfter = $this->bookRepository->findById('book-1');
        $this->assertEquals(5, $bookAfter->availableCopies()->value());
        
        // Intentar devolver de nuevo (debe fallar antes de sumar)
        try {
            $returnService->returnItem($loan->id(), $item->id());
            $this->fail('Should throw LoanItemAlreadyReturnedException');
        } catch (LoanItemAlreadyReturnedException $e) {
            // Verificar que los disponibles no cambiaron
            $bookFinal = $this->bookRepository->findById('book-1');
            $this->assertEquals(5, $bookFinal->availableCopies()->value());
        }
    }

    /**
     * T-10 / HU-07 / CA-07.3: Los disponibles jamás superan el total (garantizado por dominio)
     */
    public function test_available_never_exceeds_total(): void
    {
        // Este test verifica que el dominio protege la invariante
        // ya verificado en T-04, pero aquí confirmamos en contexto de devolución
        
        $loan = $this->createLoan();
        $item = $loan->items()[0];
        
        $returnService = new ReturnItemService(
            $this->loanRepository,
            $this->bookRepository,
            $this->clock
        );
        
        $returnService->returnItem($loan->id(), $item->id());
        
        $book = $this->bookRepository->findById('book-1');
        $this->assertLessThanOrEqual(
            $book->totalCopies()->value(),
            $book->availableCopies()->value()
        );
    }

    /**
     * T-10 / HU-07 / CA-07.4: Cuando todas las líneas están devueltas, el préstamo figura como cerrado
     */
    public function test_loan_status_is_closed_when_all_items_returned(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book1 = $this->createBook('book-1', 'Libro 1', 5);
        $book2 = $this->createBook('book-2', 'Libro 2', 5);
        
        $lendService = new LendBooksService(
            $this->memberRepository,
            $this->bookRepository,
            $this->loanRepository,
            $this->categoryRepository,
            $this->clock
        );
        
        $loan = $lendService->lendBooks('member-1', ['book-1', 'book-2'], 'user-1');
        
        // Estado inicial: open
        $this->assertEquals('open', $loan->calculateStatus($this->clock->now()));
        
        $returnService = new ReturnItemService(
            $this->loanRepository,
            $this->bookRepository,
            $this->clock
        );
        
        // Devolver primer libro
        $item1 = $loan->items()[0];
        $returnService->returnItem($loan->id(), $item1->id());
        
        $loanAfter1 = $this->loanRepository->findById($loan->id());
        $this->assertEquals('open', $loanAfter1->calculateStatus($this->clock->now()));
        
        // Devolver segundo libro
        $item2 = $loan->items()[1];
        $returnService->returnItem($loan->id(), $item2->id());
        
        // CA-07.4: ahora debe estar cerrado
        $loanFinal = $this->loanRepository->findById($loan->id());
        $this->assertEquals('closed', $loanFinal->calculateStatus($this->clock->now()));
        $this->assertTrue($loanFinal->isFullyReturned());
    }

    /**
     * T-10 / HU-07: Rechaza préstamo inexistente
     */
    public function test_return_rejects_nonexistent_loan(): void
    {
        $this->expectException(LoanNotFoundException::class);
        
        $returnService = new ReturnItemService(
            $this->loanRepository,
            $this->bookRepository,
            $this->clock
        );
        
        $returnService->returnItem('nonexistent-loan', 'item-1');
    }

    /**
     * T-10 / HU-07: Rechaza item inexistente en el préstamo
     */
    public function test_return_rejects_nonexistent_item_in_loan(): void
    {
        $this->expectException(LoanItemNotFoundException::class);
        
        $loan = $this->createLoan();
        
        $returnService = new ReturnItemService(
            $this->loanRepository,
            $this->bookRepository,
            $this->clock
        );
        
        $returnService->returnItem($loan->id(), 'nonexistent-item');
    }

    // ==================== HU-09: Libros vencidos ====================

    /**
     * T-10 / HU-09 / CA-09.1: Lista líneas sin devolver con due_at anterior a ahora
     */
    public function test_get_overdue_items_returns_only_overdue_unreturned(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book1 = $this->createBook('book-1', 'Libro 1', 5);
        $book2 = $this->createBook('book-2', 'Libro 2', 5);
        
        $lendService = new LendBooksService(
            $this->memberRepository,
            $this->bookRepository,
            $this->loanRepository,
            $this->categoryRepository,
            $this->clock
        );
        
        // Prestar 2 libros
        $loan = $lendService->lendBooks('member-1', ['book-1', 'book-2'], 'user-1');
        
        // Inicialmente no hay vencidos (día 0)
        $overdueService = new GetOverdueItemsService($this->loanRepository, $this->clock);
        $overdueInitial = $overdueService->getOverdueItems();
        $this->assertCount(0, $overdueInitial);
        
        // Avanzar 15 días (1 día después del vencimiento)
        $this->clock->setNow(new DateTimeImmutable('2026-10-29 10:00:00'));
        
        // CA-09.1: ahora ambos están vencidos
        $overdue = $overdueService->getOverdueItems();
        $this->assertCount(2, $overdue);
        
        // Devolver uno
        $returnService = new ReturnItemService(
            $this->loanRepository,
            $this->bookRepository,
            $this->clock
        );
        $returnService->returnItem($loan->id(), $loan->items()[0]->id());
        
        // Solo queda 1 vencido (el no devuelto)
        $overdueAfterReturn = $overdueService->getOverdueItems();
        $this->assertCount(1, $overdueAfterReturn);
    }

    /**
     * T-10 / HU-09 / CA-09.1: Incluye libro, socio y días de retraso
     */
    public function test_overdue_items_include_book_member_and_days_late(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book = $this->createBook('book-1', 'Cien años de soledad', 5);
        
        $lendService = new LendBooksService(
            $this->memberRepository,
            $this->bookRepository,
            $this->loanRepository,
            $this->categoryRepository,
            $this->clock
        );
        
        $loan = $lendService->lendBooks('member-1', ['book-1'], 'user-1');
        
        // Avanzar 20 días (6 días de retraso)
        $this->clock->setNow(new DateTimeImmutable('2026-11-03 10:00:00'));
        
        $overdueService = new GetOverdueItemsService($this->loanRepository, $this->clock);
        $overdue = $overdueService->getOverdueItems();
        
        $this->assertCount(1, $overdue);
        $item = $overdue[0];
        
        $this->assertEquals($loan->id(), $item['loanId']);
        $this->assertEquals($loan->items()[0]->id(), $item['itemId']);
        $this->assertEquals('Cien años de soledad', $item['bookTitle']);
        $this->assertNotNull($item['memberName']);
        $this->assertInstanceOf(DateTimeImmutable::class, $item['dueAt']);
        $this->assertEquals(6, $item['daysLate']);
    }

    /**
     * T-10 / HU-09 / CA-09.2: Ordenadas por más días de retraso primero
     */
    public function test_overdue_items_ordered_by_most_days_late_first(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        
        // Prestar libro 1 hoy
        $book1 = $this->createBook('book-1', 'Libro 1', 5);
        $lendService = new LendBooksService(
            $this->memberRepository,
            $this->bookRepository,
            $this->loanRepository,
            $this->categoryRepository,
            $this->clock
        );
        $loan1 = $lendService->lendBooks('member-1', ['book-1'], 'user-1');
        
        // Avanzar 10 días y prestar libro 2
        $this->clock->setNow(new DateTimeImmutable('2026-10-24 10:00:00'));
        $book2 = $this->createBook('book-2', 'Libro 2', 5);
        $loan2 = $lendService->lendBooks('member-1', ['book-2'], 'user-1');
        
        // Avanzar a día 25 (libro1 vencido hace 11 días, libro2 vencido hace 1 día)
        $this->clock->setNow(new DateTimeImmutable('2026-11-08 10:00:00'));
        
        $overdueService = new GetOverdueItemsService($this->loanRepository, $this->clock);
        $overdue = $overdueService->getOverdueItems();
        
        $this->assertCount(2, $overdue);
        
        // CA-09.2: ordenados por días de retraso descendente
        $this->assertGreaterThan($overdue[1]['daysLate'], $overdue[0]['daysLate']);
        $this->assertEquals('Libro 1', $overdue[0]['bookTitle']); // Más días
        $this->assertEquals('Libro 2', $overdue[1]['bookTitle']); // Menos días
    }

    /**
     * T-10 / HU-09: Sin vencidos retorna lista vacía
     */
    public function test_get_overdue_items_returns_empty_when_none(): void
    {
        $overdueService = new GetOverdueItemsService($this->loanRepository, $this->clock);
        $overdue = $overdueService->getOverdueItems();
        
        $this->assertIsArray($overdue);
        $this->assertCount(0, $overdue);
    }

    /**
     * T-10: Integración devolver + vencidos
     */
    public function test_integration_return_removes_from_overdue(): void
    {
        $member = $this->createMember('member-1', 'Juan Pérez');
        $book = $this->createBook('book-1', 'Libro', 5);
        
        $lendService = new LendBooksService(
            $this->memberRepository,
            $this->bookRepository,
            $this->loanRepository,
            $this->categoryRepository,
            $this->clock
        );
        
        $loan = $lendService->lendBooks('member-1', ['book-1'], 'user-1');
        
        // Vencer
        $this->clock->setNow(new DateTimeImmutable('2026-10-29 10:00:00'));
        
        $overdueService = new GetOverdueItemsService($this->loanRepository, $this->clock);
        $this->assertCount(1, $overdueService->getOverdueItems());
        
        // Devolver
        $returnService = new ReturnItemService(
            $this->loanRepository,
            $this->bookRepository,
            $this->clock
        );
        $returnService->returnItem($loan->id(), $loan->items()[0]->id());
        
        // Ya no aparece en vencidos
        $this->assertCount(0, $overdueService->getOverdueItems());
    }
}
