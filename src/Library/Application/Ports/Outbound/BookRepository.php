<?php

declare(strict_types=1);

namespace Library\Application\Ports\Outbound;

use Library\Domain\Book;

/**
 * BookRepository - Puerto outbound
 * 
 * Repositorio de libros del catálogo.
 */
interface BookRepository
{
    /**
     * Guarda un libro (create o update)
     */
    public function save(Book $book): void;

    /**
     * Busca un libro por id (solo activos)
     * Retorna null si no existe o está dado de baja
     */
    public function findById(string $id): ?Book;

    /**
     * Busca un libro por id incluyendo dados de baja
     */
    public function findByIdIncludingDeleted(string $id): ?Book;

    /**
     * Verifica si existe un libro activo con el ISBN dado
     */
    public function existsActiveWithIsbn(string $isbn): bool;

    /**
     * Busca libros por texto (título o autor) y/o categoría
     * Solo libros activos, paginado, ordenado por título
     * 
     * @return array ['items' => Book[], 'total' => int]
     */
    public function search(
        ?string $query,
        ?string $categoryId,
        int $page,
        int $size
    ): array;

    /**
     * Verifica si un libro tiene préstamos abiertos (líneas sin devolver)
     */
    public function hasOpenLoans(string $bookId): bool;
}
