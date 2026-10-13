<?php

namespace Tests\Unit\Domain;

use DateTimeImmutable;
use Library\Domain\Book;
use Library\Domain\Exceptions\DuplicateBookInLoanException;
use Library\Domain\Exceptions\LoanItemAlreadyReturnedException;
use Library\Domain\Exceptions\LoanItemLimitExceededException;
use Library\Domain\Loan;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

class LoanTest extends TestCase
{
    private DateTimeImmutable $fixedNow;
    
    protected function setUp(): void
    {
        // Clock fake: 2026-10-14 10:00:00 UTC
        $this->fixedNow = new DateTimeImmutable('2026-10-14 10:00:00');
    }

    private function createBook(string $id, string $title, string $isbn): Book
    {
        return Book::create(
            id: $id,
            title: $title,
            author: 'Autor',
            isbn: new Isbn($isbn),
            categoryId: 'cat-1',
            totalCopies: new Quantity(10)
        );
    }

    /**
     * T-05: Crear préstamo vacío
     */
    public function test_creates_empty_loan(): void
    {
        $loan = Loan::create(
            id: 'loan-1',
            memberId: 'member-1',
            loanedByUserId: 'user-1',
            loanedAt: $this->fixedNow
        );
        
        $this->assertEquals('loan-1', $loan->id());
        $this->assertEquals('member-1', $loan->memberId());
        $this->assertEquals('user-1', $loan->loanedByUserId());
        $this->assertEquals($this->fixedNow, $loan->loanedAt());
        $this->assertEquals(0, $loan->itemCount());
    }

    /**
     * T-05: AddItem añade una línea con vencimiento a 14 días
     */
    public function test_add_item_creates_line_with_14_days_due_date(): void
    {
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        $book = $this->createBook('book-1', 'Cien años de soledad', '9780307474728');
        
        $loan->addItem('item-1', $book, 'Novela');
        
        $this->assertEquals(1, $loan->itemCount());
        
        $items = $loan->items();
        $this->assertEquals('book-1', $items[0]->bookId());
        $this->assertEquals('Cien años de soledad', $items[0]->bookTitle());
        $this->assertEquals('9780307474728', $items[0]->isbn()->value());
        $this->assertEquals('Novela', $items[0]->categoryName());
        
        // Vencimiento debe ser loaned_at + 14 días
        $expectedDue = $this->fixedNow->modify('+14 days');
        $this->assertEquals($expectedDue, $items[0]->dueAt());
    }

    /**
     * T-05: AddItem permite hasta 3 líneas
     */
    public function test_add_item_allows_up_to_3_items(): void
    {
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        
        $loan->addItem('item-1', $this->createBook('book-1', 'Libro 1', '9780307474728'), 'Novela');
        $loan->addItem('item-2', $this->createBook('book-2', 'Libro 2', '9780307474729'), 'Ciencia');
        $loan->addItem('item-3', $this->createBook('book-3', 'Libro 3', '9780307474730'), 'Historia');
        
        $this->assertEquals(3, $loan->itemCount());
    }

    /**
     * T-05: AddItem rechaza más de 3 líneas
     */
    public function test_add_item_rejects_more_than_3_items(): void
    {
        $this->expectException(LoanItemLimitExceededException::class);
        $this->expectExceptionMessage('Un préstamo no puede tener más de 3 libros');
        
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        
        $loan->addItem('item-1', $this->createBook('book-1', 'Libro 1', '9780307474728'), 'Novela');
        $loan->addItem('item-2', $this->createBook('book-2', 'Libro 2', '9780307474729'), 'Ciencia');
        $loan->addItem('item-3', $this->createBook('book-3', 'Libro 3', '9780307474730'), 'Historia');
        $loan->addItem('item-4', $this->createBook('book-4', 'Libro 4', '9780307474731'), 'Infantil');
    }

    /**
     * T-05: AddItem rechaza libro repetido
     */
    public function test_add_item_rejects_duplicate_book(): void
    {
        $this->expectException(DuplicateBookInLoanException::class);
        $this->expectExceptionMessage('El libro «Cien años de soledad» ya está incluido en este préstamo');
        
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        $book = $this->createBook('book-1', 'Cien años de soledad', '9780307474728');
        
        $loan->addItem('item-1', $book, 'Novela');
        $loan->addItem('item-2', $book, 'Novela'); // Mismo libro
    }

    /**
     * T-05: Estado "open" cuando hay líneas sin devolver y ninguna vencida
     */
    public function test_status_is_open_when_items_unreturned_and_not_overdue(): void
    {
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        $loan->addItem('item-1', $this->createBook('book-1', 'Libro 1', '9780307474728'), 'Novela');
        
        // 5 días después (antes de los 14 días de vencimiento)
        $fiveDaysLater = $this->fixedNow->modify('+5 days');
        
        $this->assertEquals('open', $loan->calculateStatus($fiveDaysLater));
    }

    /**
     * T-05: Estado "overdue" cuando alguna línea está vencida sin devolver
     */
    public function test_status_is_overdue_when_some_item_is_overdue(): void
    {
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        $loan->addItem('item-1', $this->createBook('book-1', 'Libro 1', '9780307474728'), 'Novela');
        
        // 15 días después (1 día después del vencimiento)
        $fifteenDaysLater = $this->fixedNow->modify('+15 days');
        
        $this->assertEquals('overdue', $loan->calculateStatus($fifteenDaysLater));
        $this->assertTrue($loan->hasOverdueItems($fifteenDaysLater));
    }

    /**
     * T-05: Estado "closed" cuando todas las líneas están devueltas
     */
    public function test_status_is_closed_when_all_items_returned(): void
    {
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        $loan->addItem('item-1', $this->createBook('book-1', 'Libro 1', '9780307474728'), 'Novela');
        $loan->addItem('item-2', $this->createBook('book-2', 'Libro 2', '9780307474729'), 'Ciencia');
        
        $returnDate = $this->fixedNow->modify('+5 days');
        $loan->returnItem('item-1', $returnDate);
        $loan->returnItem('item-2', $returnDate);
        
        $this->assertEquals('closed', $loan->calculateStatus($returnDate));
        $this->assertTrue($loan->isFullyReturned());
    }

    /**
     * T-05: Estado "overdue" incluso si algunas líneas están devueltas
     */
    public function test_status_is_overdue_even_with_some_returned(): void
    {
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        $loan->addItem('item-1', $this->createBook('book-1', 'Libro 1', '9780307474728'), 'Novela');
        $loan->addItem('item-2', $this->createBook('book-2', 'Libro 2', '9780307474729'), 'Ciencia');
        
        // Devolver una línea a tiempo
        $tenDaysLater = $this->fixedNow->modify('+10 days');
        $loan->returnItem('item-1', $tenDaysLater);
        
        // 20 días después: la otra línea está vencida
        $twentyDaysLater = $this->fixedNow->modify('+20 days');
        
        $this->assertEquals('overdue', $loan->calculateStatus($twentyDaysLater));
    }

    /**
     * T-05: ReturnItem marca la línea como devuelta
     */
    public function test_return_item_marks_item_as_returned(): void
    {
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        $loan->addItem('item-1', $this->createBook('book-1', 'Libro 1', '9780307474728'), 'Novela');
        
        $returnDate = $this->fixedNow->modify('+7 days');
        $loan->returnItem('item-1', $returnDate);
        
        $items = $loan->items();
        $this->assertTrue($items[0]->isReturned());
        $this->assertEquals($returnDate, $items[0]->returnedAt());
    }

    /**
     * T-05: ReturnItem rechaza devolver dos veces
     */
    public function test_return_item_rejects_returning_twice(): void
    {
        $this->expectException(LoanItemAlreadyReturnedException::class);
        
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        $loan->addItem('item-1', $this->createBook('book-1', 'Libro 1', '9780307474728'), 'Novela');
        
        $returnDate = $this->fixedNow->modify('+7 days');
        $loan->returnItem('item-1', $returnDate);
        $loan->returnItem('item-1', $returnDate); // Segunda vez debe fallar
    }

    /**
     * T-05: CountUnreturnedItems cuenta líneas sin devolver
     */
    public function test_counts_unreturned_items(): void
    {
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        $loan->addItem('item-1', $this->createBook('book-1', 'Libro 1', '9780307474728'), 'Novela');
        $loan->addItem('item-2', $this->createBook('book-2', 'Libro 2', '9780307474729'), 'Ciencia');
        $loan->addItem('item-3', $this->createBook('book-3', 'Libro 3', '9780307474730'), 'Historia');
        
        $this->assertEquals(3, $loan->countUnreturnedItems());
        
        $returnDate = $this->fixedNow->modify('+5 days');
        $loan->returnItem('item-1', $returnDate);
        
        $this->assertEquals(2, $loan->countUnreturnedItems());
        
        $loan->returnItem('item-2', $returnDate);
        
        $this->assertEquals(1, $loan->countUnreturnedItems());
    }

    /**
     * T-05: GetOverdueItems devuelve solo líneas vencidas
     */
    public function test_get_overdue_items_returns_only_overdue(): void
    {
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        $loan->addItem('item-1', $this->createBook('book-1', 'Libro 1', '9780307474728'), 'Novela');
        $loan->addItem('item-2', $this->createBook('book-2', 'Libro 2', '9780307474729'), 'Ciencia');
        
        // 10 días después: ninguna vencida
        $tenDaysLater = $this->fixedNow->modify('+10 days');
        $this->assertCount(0, $loan->getOverdueItems($tenDaysLater));
        
        // 15 días después: ambas vencidas
        $fifteenDaysLater = $this->fixedNow->modify('+15 days');
        $this->assertCount(2, $loan->getOverdueItems($fifteenDaysLater));
        
        // Devolver una
        $loan->returnItem('item-1', $fifteenDaysLater);
        
        // 20 días después: solo una vencida (la no devuelta)
        $twentyDaysLater = $this->fixedNow->modify('+20 days');
        $overdueItems = $loan->getOverdueItems($twentyDaysLater);
        $this->assertCount(1, $overdueItems);
        $this->assertEquals('item-2', $overdueItems[0]->id());
    }

    /**
     * T-05: DaysLate calcula días de retraso correctamente
     */
    public function test_days_late_calculation(): void
    {
        $loan = Loan::create('loan-1', 'member-1', 'user-1', $this->fixedNow);
        $loan->addItem('item-1', $this->createBook('book-1', 'Libro 1', '9780307474728'), 'Novela');
        
        $items = $loan->items();
        $item = $items[0];
        
        // 10 días después (aún no vencido): 0 días de retraso
        $tenDaysLater = $this->fixedNow->modify('+10 days');
        $this->assertEquals(0, $item->daysLate($tenDaysLater));
        
        // 15 días después (1 día vencido): 1 día de retraso
        $fifteenDaysLater = $this->fixedNow->modify('+15 days');
        $this->assertEquals(1, $item->daysLate($fifteenDaysLater));
        
        // 20 días después (6 días vencido): 6 días de retraso
        $twentyDaysLater = $this->fixedNow->modify('+20 days');
        $this->assertEquals(6, $item->daysLate($twentyDaysLater));
    }

    /**
     * T-05: Reconstitute crea préstamo con estado específico
     */
    public function test_reconstitute_creates_loan_with_specific_state(): void
    {
        $item1 = new \Library\Domain\LoanItem(
            'item-1',
            'book-1',
            'Título',
            new Isbn('9780307474728'),
            'Novela',
            $this->fixedNow->modify('+14 days'),
            null
        );
        
        $loan = Loan::reconstitute(
            'loan-1',
            'member-1',
            'user-1',
            $this->fixedNow,
            [$item1]
        );
        
        $this->assertEquals(1, $loan->itemCount());
        $this->assertEquals('open', $loan->calculateStatus($this->fixedNow));
    }
}
