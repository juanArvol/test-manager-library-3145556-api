<?php

namespace Tests\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use Library\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    /**
     * T-03: Quantity acepta cero
     */
    public function test_creates_quantity_with_zero(): void
    {
        $quantity = new Quantity(0);
        
        $this->assertEquals(0, $quantity->value());
        $this->assertTrue($quantity->isZero());
        $this->assertFalse($quantity->isPositive());
    }

    /**
     * T-03: Quantity acepta valores positivos
     */
    public function test_creates_quantity_with_positive_value(): void
    {
        $quantity = new Quantity(5);
        
        $this->assertEquals(5, $quantity->value());
        $this->assertFalse($quantity->isZero());
        $this->assertTrue($quantity->isPositive());
    }

    /**
     * T-03: Quantity rechaza valores negativos
     */
    public function test_rejects_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity no puede ser negativa');
        
        new Quantity(-1);
    }

    /**
     * T-03: Sumar dos cantidades
     */
    public function test_adds_two_quantities(): void
    {
        $q1 = new Quantity(3);
        $q2 = new Quantity(2);
        
        $result = $q1->add($q2);
        
        $this->assertEquals(5, $result->value());
        // Verificar inmutabilidad
        $this->assertEquals(3, $q1->value());
        $this->assertEquals(2, $q2->value());
    }

    /**
     * T-03: Restar dos cantidades cuando el resultado es positivo
     */
    public function test_subtracts_two_quantities_with_positive_result(): void
    {
        $q1 = new Quantity(5);
        $q2 = new Quantity(2);
        
        $result = $q1->subtract($q2);
        
        $this->assertEquals(3, $result->value());
    }

    /**
     * T-03: Restar dos cantidades cuando el resultado es cero
     */
    public function test_subtracts_two_quantities_with_zero_result(): void
    {
        $q1 = new Quantity(3);
        $q2 = new Quantity(3);
        
        $result = $q1->subtract($q2);
        
        $this->assertEquals(0, $result->value());
        $this->assertTrue($result->isZero());
    }

    /**
     * T-03: Restar rechaza resultado negativo
     */
    public function test_subtract_rejects_negative_result(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('resultado sería negativo');
        
        $q1 = new Quantity(2);
        $q2 = new Quantity(5);
        $q1->subtract($q2);
    }

    /**
     * T-03: Comparaciones entre cantidades
     */
    public function test_compares_quantities(): void
    {
        $q1 = new Quantity(3);
        $q2 = new Quantity(3);
        $q3 = new Quantity(5);
        $q4 = new Quantity(1);
        
        // Igualdad
        $this->assertTrue($q1->equals($q2));
        $this->assertFalse($q1->equals($q3));
        
        // Mayor que
        $this->assertTrue($q3->isGreaterThan($q1));
        $this->assertFalse($q1->isGreaterThan($q3));
        
        // Mayor o igual
        $this->assertTrue($q1->isGreaterThanOrEqual($q2));
        $this->assertTrue($q3->isGreaterThanOrEqual($q1));
        
        // Menor que
        $this->assertTrue($q4->isLessThan($q1));
        $this->assertFalse($q3->isLessThan($q1));
        
        // Menor o igual
        $this->assertTrue($q1->isLessThanOrEqual($q2));
        $this->assertTrue($q4->isLessThanOrEqual($q1));
    }

    /**
     * T-03: Conversión a string
     */
    public function test_converts_to_string(): void
    {
        $quantity = new Quantity(42);
        
        $this->assertEquals('42', (string) $quantity);
    }
}
