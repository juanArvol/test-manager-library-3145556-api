<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use Library\Application\Exceptions\InvalidDateRangeException;
use Library\Application\Services\GetMostBorrowedReportService;
use Library\Domain\ValueObjects\DateRange;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Application\Fakes\FakeReportReader;

final class GetMostBorrowedReportServiceTest extends TestCase
{
    private FakeReportReader $reportReader;
    private GetMostBorrowedReportService $service;

    protected function setUp(): void
    {
        $this->reportReader = new FakeReportReader();
        $this->service = new GetMostBorrowedReportService($this->reportReader);
    }

    /** @test */
    public function it_returns_empty_list_when_no_loans_in_range(): void
    {
        $range = new DateRange(
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-12-31')
        );

        $result = $this->service->execute($range);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_aggregates_by_frozen_book_values(): void
    {
        $bookId = 'book-123';

        // Same book, loaned 3 times
        $this->reportReader->addLoanItem([
            'loanId' => 'loan-1',
            'bookId' => $bookId,
            'bookTitle' => 'Clean Code',
            'isbn' => '9780132350884',
            'categoryName' => 'Programación',
            'loanedAt' => new \DateTimeImmutable('2025-06-10'),
            'returnedAt' => new \DateTimeImmutable('2025-06-20'),
        ]);

        $this->reportReader->addLoanItem([
            'loanId' => 'loan-2',
            'bookId' => $bookId,
            'bookTitle' => 'Clean Code',
            'isbn' => '9780132350884',
            'categoryName' => 'Programación',
            'loanedAt' => new \DateTimeImmutable('2025-07-01'),
            'returnedAt' => null,
        ]);

        $this->reportReader->addLoanItem([
            'loanId' => 'loan-3',
            'bookId' => $bookId,
            'bookTitle' => 'Clean Code',
            'isbn' => '9780132350884',
            'categoryName' => 'Programación',
            'loanedAt' => new \DateTimeImmutable('2025-08-15'),
            'returnedAt' => null,
        ]);

        $range = new DateRange(
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-12-31')
        );

        $result = $this->service->execute($range);

        $this->assertCount(1, $result);
        $this->assertSame($bookId, $result[0]['book_id']);
        $this->assertSame('Clean Code', $result[0]['book_title']);
        $this->assertSame('Programación', $result[0]['category_name']);
        $this->assertSame(3, $result[0]['times_borrowed']);
    }

    /** @test */
    public function it_orders_by_times_borrowed_desc(): void
    {
        // Book A: loaned 5 times
        for ($i = 1; $i <= 5; $i++) {
            $this->reportReader->addLoanItem([
                'loanId' => "loan-a-$i",
                'bookId' => 'book-a',
                'bookTitle' => 'Book A',
                'isbn' => '9780000000001',
                'categoryName' => 'Category A',
                'loanedAt' => new \DateTimeImmutable("2025-06-0$i"),
                'returnedAt' => null,
            ]);
        }

        // Book B: loaned 2 times
        for ($i = 1; $i <= 2; $i++) {
            $this->reportReader->addLoanItem([
                'loanId' => "loan-b-$i",
                'bookId' => 'book-b',
                'bookTitle' => 'Book B',
                'isbn' => '9780000000002',
                'categoryName' => 'Category B',
                'loanedAt' => new \DateTimeImmutable("2025-06-1$i"),
                'returnedAt' => null,
            ]);
        }

        // Book C: loaned 8 times
        for ($i = 1; $i <= 8; $i++) {
            $this->reportReader->addLoanItem([
                'loanId' => "loan-c-$i",
                'bookId' => 'book-c',
                'bookTitle' => 'Book C',
                'isbn' => '9780000000003',
                'categoryName' => 'Category C',
                'loanedAt' => new \DateTimeImmutable("2025-07-0$i"),
                'returnedAt' => null,
            ]);
        }

        $range = new DateRange(
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-12-31')
        );

        $result = $this->service->execute($range);

        $this->assertCount(3, $result);
        $this->assertSame('book-c', $result[0]['book_id']);
        $this->assertSame(8, $result[0]['times_borrowed']);
        $this->assertSame('book-a', $result[1]['book_id']);
        $this->assertSame(5, $result[1]['times_borrowed']);
        $this->assertSame('book-b', $result[2]['book_id']);
        $this->assertSame(2, $result[2]['times_borrowed']);
    }

    /** @test */
    public function it_filters_by_date_range(): void
    {
        // Loan inside range
        $this->reportReader->addLoanItem([
            'loanId' => 'loan-1',
            'bookId' => 'book-inside',
            'bookTitle' => 'Inside Range',
            'isbn' => '9780000000001',
            'categoryName' => 'Category',
            'loanedAt' => new \DateTimeImmutable('2025-06-15'),
            'returnedAt' => null,
        ]);

        // Loan outside range (before)
        $this->reportReader->addLoanItem([
            'loanId' => 'loan-2',
            'bookId' => 'book-before',
            'bookTitle' => 'Before Range',
            'isbn' => '9780000000002',
            'categoryName' => 'Category',
            'loanedAt' => new \DateTimeImmutable('2025-05-01'),
            'returnedAt' => null,
        ]);

        // Loan outside range (after)
        $this->reportReader->addLoanItem([
            'loanId' => 'loan-3',
            'bookId' => 'book-after',
            'bookTitle' => 'After Range',
            'isbn' => '9780000000003',
            'categoryName' => 'Category',
            'loanedAt' => new \DateTimeImmutable('2025-08-01'),
            'returnedAt' => null,
        ]);

        $range = new DateRange(
            new \DateTimeImmutable('2025-06-01'),
            new \DateTimeImmutable('2025-06-30')
        );

        $result = $this->service->execute($range);

        $this->assertCount(1, $result);
        $this->assertSame('book-inside', $result[0]['book_id']);
    }

    /** @test */
    public function it_validates_date_range_fin_must_be_after_inicio(): void
    {
        $this->expectException(InvalidDateRangeException::class);
        $this->expectExceptionMessage('La fecha de fin debe ser posterior o igual a la fecha de inicio');

        new DateRange(
            new \DateTimeImmutable('2025-12-31'),
            new \DateTimeImmutable('2025-01-01')
        );
    }

    /** @test */
    public function it_allows_same_inicio_and_fin_date(): void
    {
        $this->reportReader->addLoanItem([
            'loanId' => 'loan-1',
            'bookId' => 'book-1',
            'bookTitle' => 'Book Title',
            'isbn' => '9780000000001',
            'categoryName' => 'Category',
            'loanedAt' => new \DateTimeImmutable('2025-06-15'),
            'returnedAt' => null,
        ]);

        $range = new DateRange(
            new \DateTimeImmutable('2025-06-15'),
            new \DateTimeImmutable('2025-06-15')
        );

        $result = $this->service->execute($range);

        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_preserves_frozen_book_title_per_adr_004(): void
    {
        $bookId = 'book-123';

        // First loan with original title
        $this->reportReader->addLoanItem([
            'loanId' => 'loan-1',
            'bookId' => $bookId,
            'bookTitle' => 'Original Title',
            'isbn' => '9780132350884',
            'categoryName' => 'Programación',
            'loanedAt' => new \DateTimeImmutable('2025-06-01'),
            'returnedAt' => new \DateTimeImmutable('2025-06-10'),
        ]);

        // Book renamed to "New Title" after first loan

        // Second loan with new title (same book, different frozen title)
        $this->reportReader->addLoanItem([
            'loanId' => 'loan-2',
            'bookId' => $bookId,
            'bookTitle' => 'New Title',
            'isbn' => '9780132350884',
            'categoryName' => 'Programación',
            'loanedAt' => new \DateTimeImmutable('2025-07-01'),
            'returnedAt' => null,
        ]);

        $range = new DateRange(
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-12-31')
        );

        $result = $this->service->execute($range);

        // ADR-004: Two separate entries because frozen bookTitle differs
        $this->assertCount(2, $result);

        // Both have same bookId but different frozen titles
        $this->assertSame($bookId, $result[0]['book_id']);
        $this->assertSame($bookId, $result[1]['book_id']);

        $titles = [$result[0]['book_title'], $result[1]['book_title']];
        sort($titles);

        $this->assertSame(['New Title', 'Original Title'], $titles);
        $this->assertSame(1, $result[0]['times_borrowed']);
        $this->assertSame(1, $result[1]['times_borrowed']);
    }

    /** @test */
    public function it_groups_correctly_when_same_frozen_values_appear_multiple_times(): void
    {
        $bookId = 'book-123';

        // Three loans with identical frozen values
        for ($i = 1; $i <= 3; $i++) {
            $this->reportReader->addLoanItem([
                'loanId' => "loan-$i",
                'bookId' => $bookId,
                'bookTitle' => 'Same Title',
                'isbn' => '9780132350884',
                'categoryName' => 'Same Category',
                'loanedAt' => new \DateTimeImmutable("2025-06-0$i"),
                'returnedAt' => null,
            ]);
        }

        // One loan with different frozen title (same book)
        $this->reportReader->addLoanItem([
            'loanId' => 'loan-4',
            'bookId' => $bookId,
            'bookTitle' => 'Different Title',
            'isbn' => '9780132350884',
            'categoryName' => 'Same Category',
            'loanedAt' => new \DateTimeImmutable('2025-07-01'),
            'returnedAt' => null,
        ]);

        $range = new DateRange(
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-12-31')
        );

        $result = $this->service->execute($range);

        $this->assertCount(2, $result);

        // First group: "Same Title" x3
        $this->assertSame('Same Title', $result[0]['book_title']);
        $this->assertSame(3, $result[0]['times_borrowed']);

        // Second group: "Different Title" x1
        $this->assertSame('Different Title', $result[1]['book_title']);
        $this->assertSame(1, $result[1]['times_borrowed']);
    }

    /** @test */
    public function it_includes_both_returned_and_open_loans(): void
    {
        // Returned loan
        $this->reportReader->addLoanItem([
            'loanId' => 'loan-1',
            'bookId' => 'book-1',
            'bookTitle' => 'Book One',
            'isbn' => '9780000000001',
            'categoryName' => 'Category',
            'loanedAt' => new \DateTimeImmutable('2025-06-01'),
            'returnedAt' => new \DateTimeImmutable('2025-06-10'),
        ]);

        // Open loan
        $this->reportReader->addLoanItem([
            'loanId' => 'loan-2',
            'bookId' => 'book-1',
            'bookTitle' => 'Book One',
            'isbn' => '9780000000001',
            'categoryName' => 'Category',
            'loanedAt' => new \DateTimeImmutable('2025-06-15'),
            'returnedAt' => null,
        ]);

        $range = new DateRange(
            new \DateTimeImmutable('2025-06-01'),
            new \DateTimeImmutable('2025-06-30')
        );

        $result = $this->service->execute($range);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['times_borrowed']);
    }

    /** @test */
    public function it_differentiates_books_by_category_name_even_with_same_title(): void
    {
        // Same title, different categories
        $this->reportReader->addLoanItem([
            'loanId' => 'loan-1',
            'bookId' => 'book-1',
            'bookTitle' => 'Introduction to X',
            'isbn' => '9780000000001',
            'categoryName' => 'Science',
            'loanedAt' => new \DateTimeImmutable('2025-06-01'),
            'returnedAt' => null,
        ]);

        $this->reportReader->addLoanItem([
            'loanId' => 'loan-2',
            'bookId' => 'book-2',
            'bookTitle' => 'Introduction to X',
            'isbn' => '9780000000002',
            'categoryName' => 'History',
            'loanedAt' => new \DateTimeImmutable('2025-06-02'),
            'returnedAt' => null,
        ]);

        $range = new DateRange(
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-12-31')
        );

        $result = $this->service->execute($range);

        $this->assertCount(2, $result);
        $this->assertSame('Introduction to X', $result[0]['book_title']);
        $this->assertSame('Introduction to X', $result[1]['book_title']);
        $this->assertNotSame($result[0]['category_name'], $result[1]['category_name']);
    }
}
