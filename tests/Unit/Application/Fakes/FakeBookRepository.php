<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\BookRepository;
use Library\Domain\Book;

/**
 * FakeBookRepository - Implementación en memoria para tests
 */
final class FakeBookRepository implements BookRepository
{
    /** @var array<string, Book> */
    private array $books = [];
    
    /** @var array<string, bool> */
    private array $booksWithOpenLoans = [];

    public function save(Book $book): void
    {
        $this->books[$book->id()] = $book;
    }

    public function findById(string $id): ?Book
    {
        $book = $this->books[$id] ?? null;
        
        // Solo retornar si está activo
        if ($book && $book->isActive()) {
            return $book;
        }
        
        return null;
    }

    public function findByIdIncludingDeleted(string $id): ?Book
    {
        return $this->books[$id] ?? null;
    }

    public function existsActiveWithIsbn(string $isbn): bool
    {
        foreach ($this->books as $book) {
            if ($book->isActive() && $book->isbn()->value() === $isbn) {
                return true;
            }
        }
        
        return false;
    }

    public function search(?string $query, ?string $categoryId, int $page, int $size): array
    {
        $filtered = [];
        
        foreach ($this->books as $book) {
            // Solo activos
            if (!$book->isActive()) {
                continue;
            }
            
            // Filtro por categoría
            if ($categoryId !== null && $book->categoryId() !== $categoryId) {
                continue;
            }
            
            // Filtro por query (título o autor)
            if ($query !== null && $query !== '') {
                $queryLower = strtolower($query);
                $titleLower = strtolower($book->title());
                $authorLower = strtolower($book->author());
                
                if (
                    !str_contains($titleLower, $queryLower) &&
                    !str_contains($authorLower, $queryLower)
                ) {
                    continue;
                }
            }
            
            $filtered[] = $book;
        }
        
        // Ordenar por título
        usort($filtered, fn($a, $b) => strcmp($a->title(), $b->title()));
        
        $total = count($filtered);
        
        // Paginar
        $offset = ($page - 1) * $size;
        $items = array_slice($filtered, $offset, $size);
        
        return [
            'items' => $items,
            'total' => $total
        ];
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return $this->booksWithOpenLoans[$bookId] ?? false;
    }

    // Métodos de ayuda para tests
    
    public function addBook(Book $book): void
    {
        $this->books[$book->id()] = $book;
    }

    public function setHasOpenLoans(string $bookId, bool $hasOpenLoans): void
    {
        $this->booksWithOpenLoans[$bookId] = $hasOpenLoans;
    }

    public function clear(): void
    {
        $this->books = [];
        $this->booksWithOpenLoans = [];
    }

    public function count(): int
    {
        return count($this->books);
    }
}
