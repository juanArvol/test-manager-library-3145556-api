<?php

namespace Tests\Unit\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use Library\Domain\ValueObjects\DateRange;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    /**
     * T-03: DateRange válido con fin >= inicio
     */
    public function test_creates_valid_date_range_with_end_after_start(): void
    {
        $start = new DateTimeImmutable('2026-10-01');
        $end = new DateTimeImmutable('2026-10-31');
        
        $range = new DateRange($start, $end);
        
        $this->assertEquals($start, $range->start());
        $this->assertEquals($end, $range->end());
    }

    /**
     * T-03: DateRange válido con fin = inicio (mismo día)
     */
    public function test_creates_valid_date_range_with_same_start_and_end(): void
    {
        $date = new DateTimeImmutable('2026-10-15');
        
        $range = new DateRange($date, $date);
        
        $this->assertEquals($date, $range->start());
        $this->assertEquals($date, $range->end());
    }

    /**
     * T-03: DateRange rechaza fin < inicio
     */
    public function test_rejects_date_range_with_end_before_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La fecha de fin (2026-10-01) no puede ser anterior a la de inicio (2026-10-31)');
        
        $start = new DateTimeImmutable('2026-10-31');
        $end = new DateTimeImmutable('2026-10-01');
        
        new DateRange($start, $end);
    }

    /**
     * T-03: Verificar si una fecha está dentro del rango
     */
    public function test_checks_if_date_is_within_range(): void
    {
        $start = new DateTimeImmutable('2026-10-01');
        $end = new DateTimeImmutable('2026-10-31');
        $range = new DateRange($start, $end);
        
        // Dentro del rango
        $this->assertTrue($range->contains(new DateTimeImmutable('2026-10-15')));
        $this->assertTrue($range->contains($start)); // Inclusivo al inicio
        $this->assertTrue($range->contains($end));   // Inclusivo al final
        
        // Fuera del rango
        $this->assertFalse($range->contains(new DateTimeImmutable('2026-09-30')));
        $this->assertFalse($range->contains(new DateTimeImmutable('2026-11-01')));
    }

    /**
     * T-03: Obtener inicio del día
     */
    public function test_gets_start_of_day(): void
    {
        $start = new DateTimeImmutable('2026-10-01 14:30:00');
        $end = new DateTimeImmutable('2026-10-31');
        $range = new DateRange($start, $end);
        
        $startOfDay = $range->startOfDay();
        
        $this->assertEquals('2026-10-01 00:00:00', $startOfDay->format('Y-m-d H:i:s'));
    }

    /**
     * T-03: Obtener fin del día
     */
    public function test_gets_end_of_day(): void
    {
        $start = new DateTimeImmutable('2026-10-01');
        $end = new DateTimeImmutable('2026-10-31 10:00:00');
        $range = new DateRange($start, $end);
        
        $endOfDay = $range->endOfDay();
        
        $this->assertEquals('2026-10-31 23:59:59', $endOfDay->format('Y-m-d H:i:s'));
    }

    /**
     * T-03: Calcular días en el rango (inclusivo)
     */
    public function test_calculates_days_in_range(): void
    {
        // 1 día (mismo día)
        $range1 = new DateRange(
            new DateTimeImmutable('2026-10-15'),
            new DateTimeImmutable('2026-10-15')
        );
        $this->assertEquals(1, $range1->days());
        
        // 31 días (octubre completo)
        $range2 = new DateRange(
            new DateTimeImmutable('2026-10-01'),
            new DateTimeImmutable('2026-10-31')
        );
        $this->assertEquals(31, $range2->days());
        
        // 7 días (una semana)
        $range3 = new DateRange(
            new DateTimeImmutable('2026-10-01'),
            new DateTimeImmutable('2026-10-07')
        );
        $this->assertEquals(7, $range3->days());
    }

    /**
     * T-03: Dos rangos con las mismas fechas son iguales
     */
    public function test_two_ranges_with_same_dates_are_equal(): void
    {
        $range1 = new DateRange(
            new DateTimeImmutable('2026-10-01'),
            new DateTimeImmutable('2026-10-31')
        );
        
        $range2 = new DateRange(
            new DateTimeImmutable('2026-10-01'),
            new DateTimeImmutable('2026-10-31')
        );
        
        $this->assertTrue($range1->equals($range2));
    }

    /**
     * T-03: Dos rangos con diferentes fechas no son iguales
     */
    public function test_two_ranges_with_different_dates_are_not_equal(): void
    {
        $range1 = new DateRange(
            new DateTimeImmutable('2026-10-01'),
            new DateTimeImmutable('2026-10-31')
        );
        
        $range2 = new DateRange(
            new DateTimeImmutable('2026-11-01'),
            new DateTimeImmutable('2026-11-30')
        );
        
        $this->assertFalse($range1->equals($range2));
    }

    /**
     * T-03: Conversión a string
     */
    public function test_converts_to_string(): void
    {
        $range = new DateRange(
            new DateTimeImmutable('2026-10-01'),
            new DateTimeImmutable('2026-10-31')
        );
        
        $this->assertEquals('2026-10-01 hasta 2026-10-31', (string) $range);
    }
}
