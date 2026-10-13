<?php

declare(strict_types=1);

namespace Library\Adapters\Persistence;

use Illuminate\Support\Facades\DB;
use Library\Application\Ports\Outbound\BookRepository;
use Library\Domain\Book;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use Ramsey\Uuid\Uuid;

final class PostgresBookRepository implements BookRepository
{
    public function save(Book $book): void
    {
        $existing = DB::table('book')
            ->where('book_id', $book->getId())
            ->first();

        if ($existing) {
            DB::table('book')
                ->where('book_id', $book->getId())
                ->update([
                    'title' => $book->getTitle(),
                    'isbn' => $book->getIsbn()->getValue(),
                    'category_id' => $book->getCategoryId(),
                    'total_quantity' => $book->getTotalQuantity()->getValue(),
                    'available_quantity' => $book->getAvailableQuantity()->getValue(),
                    'cover_key' => $book->getCoverKey(),
                    'deleted_at' => $book->isDeleted() ? now() : null,
                ]);
        } else {
            DB::table('book')->insert([
                'book_id' => $book->getId(),
                'title' => $book->getTitle(),
                'isbn' => $book->getIsbn()->getValue(),
                'category_id' => $book->getCategoryId(),
                'total_quantity' => $book->getTotalQuantity()->getValue(),
                'available_quantity' => $book->getAvailableQuantity()->getValue(),
                'cover_key' => $book->getCoverKey(),
                'deleted_at' => $book->isDeleted() ? now() : null,
                'created_at' => now(),
            ]);
        }
    }

    public function findById(string $bookId): ?Book
    {
        $row = DB::table('book')
            ->where('book_id', $bookId)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByIsbn(Isbn $isbn): ?Book
    {
        $row = DB::table('book')
            ->where('isbn', $isbn->getValue())
            ->first();

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function search(string $query, int $offset, int $limit): array
    {
        $rows = DB::table('book')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($query) {
                $q->where('title', 'ILIKE', "%$query%")
                  ->orWhere('isbn', 'LIKE', "%$query%");
            })
            ->orderBy('title')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $rows->map(fn($row) => $this->hydrate($row))->all();
    }

    public function nextIdentity(): string
    {
        return Uuid::uuid4()->toString();
    }

    private function hydrate(object $row): Book
    {
        $book = Book::create(
            $row->book_id,
            $row->title,
            new Isbn($row->isbn),
            $row->category_id,
            new Quantity($row->total_quantity),
            $row->cover_key
        );

        // Restore available quantity
        $withdrawn = $row->total_quantity - $row->available_quantity;
        if ($withdrawn > 0) {
            $book->withdraw(new Quantity($withdrawn));
        }

        // Restore deleted state
        if ($row->deleted_at !== null) {
            $book->delete();
        }

        return $book;
    }
}
