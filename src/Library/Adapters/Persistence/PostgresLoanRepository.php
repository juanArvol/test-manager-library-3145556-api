<?php

declare(strict_types=1);

namespace Library\Adapters\Persistence;

use Illuminate\Support\Facades\DB;
use Library\Application\Exceptions\ConcurrencyException;
use Library\Application\Ports\Outbound\LoanRepository;
use Library\Domain\Loan;
use Library\Domain\LoanItem;
use Library\Domain\ValueObjects\Isbn;
use Ramsey\Uuid\Uuid;

final class PostgresLoanRepository implements LoanRepository
{
    public function save(Loan $loan): void
    {
        $existing = DB::table('loan')
            ->where('loan_id', $loan->id())
            ->first();

        if ($existing) {
            // Optimistic locking check (ADR-002)
            $updated = DB::table('loan')
                ->where('loan_id', $loan->id())
                ->where('version', $loan->version())
                ->update([
                    'version' => $loan->version() + 1,
                ]);

            if ($updated === 0) {
                throw new ConcurrencyException('El préstamo fue modificado por otro proceso');
            }

            // Delete existing items and re-insert
            DB::table('loan_item')->where('loan_id', $loan->id())->delete();
        } else {
            DB::table('loan')->insert([
                'loan_id' => $loan->id(),
                'member_id' => $loan->memberId(),
                'loaned_by_user_id' => $loan->loanedByUserId(),
                'loaned_at' => $loan->loanedAt()->format('Y-m-d H:i:s'),
                'version' => 0,
                'created_at' => now(),
            ]);
        }

        // Insert all items
        foreach ($loan->items() as $item) {
            DB::table('loan_item')->insert([
                'loan_item_id' => $item->id(),
                'loan_id' => $loan->id(),
                'book_id' => $item->bookId(),
                'book_title' => $item->bookTitle(),
                'isbn' => $item->isbn()->getValue(),
                'category_name' => $item->categoryName(),
                'due_at' => $item->dueAt()->format('Y-m-d H:i:s'),
                'returned_at' => $item->returnedAt()?->format('Y-m-d H:i:s'),
                'created_at' => now(),
            ]);
        }
    }

    public function findById(string $loanId): ?Loan
    {
        $loanRow = DB::table('loan')
            ->where('loan_id', $loanId)
            ->first();

        if (!$loanRow) {
            return null;
        }

        $itemRows = DB::table('loan_item')
            ->where('loan_id', $loanId)
            ->get();

        $items = [];
        foreach ($itemRows as $itemRow) {
            $items[] = new LoanItem(
                $itemRow->loan_item_id,
                $itemRow->book_id,
                $itemRow->book_title,
                new Isbn($itemRow->isbn),
                $itemRow->category_name,
                new \DateTimeImmutable($itemRow->due_at),
                $itemRow->returned_at ? new \DateTimeImmutable($itemRow->returned_at) : null
            );
        }

        return Loan::reconstitute(
            $loanRow->loan_id,
            $loanRow->member_id,
            $loanRow->loaned_by_user_id,
            new \DateTimeImmutable($loanRow->loaned_at),
            (int)$loanRow->version,
            $items
        );
    }

    public function findByMemberId(string $memberId): array
    {
        $loanRows = DB::table('loan')
            ->where('member_id', $memberId)
            ->get();

        $loans = [];
        foreach ($loanRows as $loanRow) {
            $loan = $this->findById($loanRow->loan_id);
            if ($loan) {
                $loans[] = $loan;
            }
        }

        return $loans;
    }

    public function findOverdueItems(\DateTimeImmutable $today): array
    {
        $rows = DB::table('loan_item')
            ->join('loan', 'loan_item.loan_id', '=', 'loan.loan_id')
            ->whereNull('loan_item.returned_at')
            ->where('loan_item.due_at', '<', $today->format('Y-m-d H:i:s'))
            ->orderByRaw("(? - loan_item.due_at) DESC", [$today->format('Y-m-d H:i:s')])
            ->select([
                'loan_item.loan_item_id',
                'loan_item.loan_id',
                'loan_item.book_id',
                'loan_item.book_title',
                'loan_item.isbn',
                'loan_item.category_name',
                'loan_item.due_at',
                'loan_item.returned_at',
                'loan.member_id',
            ])
            ->get();

        return $rows->map(function ($row) use ($today) {
            $dueAt = new \DateTimeImmutable($row->due_at);
            $daysOverdue = $today->diff($dueAt)->days;

            return [
                'loan_item_id' => $row->loan_item_id,
                'loan_id' => $row->loan_id,
                'member_id' => $row->member_id,
                'book_id' => $row->book_id,
                'book_title' => $row->book_title,
                'isbn' => $row->isbn,
                'category_name' => $row->category_name,
                'due_at' => $dueAt,
                'days_overdue' => $daysOverdue,
            ];
        })->toArray();
    }

    public function nextIdentity(): string
    {
        return Uuid::uuid4()->toString();
    }
}
