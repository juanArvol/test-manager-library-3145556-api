<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Exceptions\InvalidDateRangeException;
use Library\Application\Ports\Outbound\ReportReader;
use Library\Domain\ValueObjects\DateRange;

/**
 * GetMostBorrowedReportService - Caso de uso de reporte (HU-10)
 * 
 * Solo admin puede ver reportes.
 */
final readonly class GetMostBorrowedReportService
{
    public function __construct(
        private ReportReader $reportReader
    ) {}

    /**
     * Obtiene el reporte de libros más prestados en un rango de fechas
     * 
     * CA-10.1: Dado un rango de fechas, una fila por libro y nombre de categoría congelado,
     *          con la cantidad de veces prestado, ordenado de mayor a menor
     * CA-10.2: Fin anterior al inicio: 422. Rango sin préstamos: lista vacía, no error
     * CA-10.3: El reporte no se desglosa por bibliotecario ni por socio
     * CA-10.4: Un reporte de un periodo cerrado no cambia aunque luego se renombre el libro o su categoría
     * 
     * @return array ['from' => string, 'to' => string, 'rows' => [...]]
     * @throws InvalidDateRangeException si fin < inicio
     */
    public function getMostBorrowedReport(string $from, string $to): array
    {
        // Parsear fechas
        try {
            $fromDate = new \DateTimeImmutable($from);
            $toDate = new \DateTimeImmutable($to);
        } catch (\Exception $e) {
            throw InvalidDateRangeException::invalidFormat();
        }

        // CA-10.2: validar que fin >= inicio
        try {
            $dateRange = new DateRange($fromDate, $toDate);
        } catch (\InvalidArgumentException $e) {
            throw InvalidDateRangeException::endBeforeStart($from, $to);
        }

        // CA-10.1 y CA-10.4: obtener datos del reporte
        // Usa valores congelados (bookTitle, categoryName) de loan_item
        $rows = $this->reportReader->getMostBorrowedBooks(
            $dateRange->startOfDay(),
            $dateRange->endOfDay()
        );

        // CA-10.2: rango sin préstamos retorna lista vacía (no error)
        // CA-10.3: no incluye bibliotecario ni socio
        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows
        ];
    }
}
