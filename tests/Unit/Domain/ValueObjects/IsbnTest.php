<?php

namespace Tests\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use Library\Domain\ValueObjects\Isbn;
use PHPUnit\Framework\TestCase;

class IsbnTest extends TestCase
{
    /**
     * T-03: ISBN válido de 13 dígitos
     */
    public function test_creates_valid_isbn_with_13_digits(): void
    {
        $isbn = new Isbn('9780307474728');
        
        $this->assertEquals('9780307474728', $isbn->value());
        $this->assertEquals('9780307474728', (string) $isbn);
    }

    /**
     * T-03: ISBN acepta guiones y los elimina
     */
    public function test_accepts_isbn_with_hyphens_and_removes_them(): void
    {
        $isbn = new Isbn('978-0-307-47472-8');
        
        $this->assertEquals('9780307474728', $isbn->value());
    }

    /**
     * T-03: ISBN acepta espacios y los elimina
     */
    public function test_accepts_isbn_with_spaces_and_removes_them(): void
    {
        $isbn = new Isbn('978 0 307 47472 8');
        
        $this->assertEquals('9780307474728', $isbn->value());
    }

    /**
     * T-03: ISBN rechaza menos de 13 dígitos
     */
    public function test_rejects_isbn_with_less_than_13_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN debe tener exactamente 13 dígitos');
        
        new Isbn('978030747472');
    }

    /**
     * T-03: ISBN rechaza más de 13 dígitos
     */
    public function test_rejects_isbn_with_more_than_13_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN debe tener exactamente 13 dígitos');
        
        new Isbn('97803074747281');
    }

    /**
     * T-03: ISBN rechaza caracteres no numéricos
     */
    public function test_rejects_isbn_with_non_digit_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISBN debe contener solo dígitos');
        
        new Isbn('978030747472X');
    }

    /**
     * T-03: ISBN rechaza cadena vacía
     */
    public function test_rejects_empty_isbn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        new Isbn('');
    }

    /**
     * T-03: Dos ISBN con el mismo valor son iguales
     */
    public function test_two_isbn_with_same_value_are_equal(): void
    {
        $isbn1 = new Isbn('9780307474728');
        $isbn2 = new Isbn('978-0-307-47472-8');
        
        $this->assertTrue($isbn1->equals($isbn2));
    }

    /**
     * T-03: Dos ISBN con diferente valor no son iguales
     */
    public function test_two_isbn_with_different_value_are_not_equal(): void
    {
        $isbn1 = new Isbn('9780307474728');
        $isbn2 = new Isbn('9780307474729');
        
        $this->assertFalse($isbn1->equals($isbn2));
    }
}
