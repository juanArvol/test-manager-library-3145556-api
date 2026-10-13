<?php

declare(strict_types=1);

namespace Library\Application\Ports\Outbound;

use DateTimeImmutable;

/**
 * ReportReader - Puerto outbound de lectura
 * 
 * Consultas optimizadas para reportes (agregaciones).
 */
interface ReportReader
{
    /**
     * Obtiene los libros más prestados en un rango de fechas
     * 
     * Retorna una fila por combinación de (bookId, bookTitle, categoryName)
     * congelados en loan_item. No lee del catálogo vivo (ADR-004).
     * 
     * @param DateTimeImmutable $from Fecha de inicio (inclusiva)
     * @param DateTimeImmutable $to Fecha de fin (inclusiva)
     * @return array Array de ['book_id', 'book_title', 'category_name', 'times_borrowed']
     *               ordenado por times_borrowed DESC, luego book_title ASC
     */
    public function getMostBorrowedBooks(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
