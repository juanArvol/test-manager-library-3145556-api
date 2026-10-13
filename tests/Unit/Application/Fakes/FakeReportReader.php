<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\ReportReader;
use Library\Domain\ValueObjects\DateRange;

final class FakeReportReader implements ReportReader
{
    /** @var array<array{loanId: string, bookId: string, bookTitle: string, isbn: string, categoryName: string, loanedAt: \DateTimeImmutable, returnedAt: \DateTimeImmutable|null}> */
    private array $loanItems = [];

    /**
     * @param array{loanId: string, bookId: string, bookTitle: string, isbn: string, categoryName: string, loanedAt: \DateTimeImmutable, returnedAt: \DateTimeImmutable|null} $item
     */
    public function addLoanItem(array $item): void
    {
        $this->loanItems[] = $item;
    }

    public function getMostBorrowedReport(DateRange $range): array
    {
        $groups = [];

        foreach ($this->loanItems as $item) {
            // Filter by date range (loaned_at between inicio and fin)
            if ($item['loanedAt'] < $range->getInicio() || $item['loanedAt'] > $range->getFin()) {
                continue;
            }

            // Group by (bookId, bookTitle, categoryName) - frozen values
            $key = $item['bookId'] . '|' . $item['bookTitle'] . '|' . $item['categoryName'];

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'book_id' => $item['bookId'],
                    'book_title' => $item['bookTitle'],
                    'category_name' => $item['categoryName'],
                    'times_borrowed' => 0,
                ];
            }

            $groups[$key]['times_borrowed']++;
        }

        // Convert to indexed array
        $result = array_values($groups);

        // Order by times_borrowed DESC
        usort($result, function ($a, $b) {
            return $b['times_borrowed'] <=> $a['times_borrowed'];
        });

        return $result;
    }
}
