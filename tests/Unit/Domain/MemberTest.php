<?php

namespace Tests\Unit\Domain;

use InvalidArgumentException;
use Library\Domain\Member;
use PHPUnit\Framework\TestCase;

class MemberTest extends TestCase
{
    /**
     * T-06: Crear socio con datos válidos
     */
    public function test_creates_member_with_valid_data(): void
    {
        $member = Member::create(
            id: 'member-1',
            fullName: 'Juan Pérez',
            documentNumber: '12345678'
        );
        
        $this->assertEquals('member-1', $member->id());
        $this->assertEquals('Juan Pérez', $member->fullName());
        $this->assertEquals('12345678', $member->documentNumber());
    }

    /**
     * T-06: Nombre y documento se recortan automáticamente
     */
    public function test_trims_full_name_and_document_number(): void
    {
        $member = Member::create(
            'member-1',
            '  María García  ',
            '  87654321  '
        );
        
        $this->assertEquals('María García', $member->fullName());
        $this->assertEquals('87654321', $member->documentNumber());
    }

    /**
     * T-06: Rechaza nombre vacío
     */
    public function test_rejects_empty_full_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El nombre completo no puede estar vacío');
        
        Member::create('member-1', '   ', '12345678');
    }

    /**
     * T-06: Rechaza documento vacío
     */
    public function test_rejects_empty_document_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El número de documento no puede estar vacío');
        
        Member::create('member-1', 'Juan Pérez', '   ');
    }

    /**
     * T-06: UpdateFullName actualiza el nombre
     */
    public function test_update_full_name_changes_name(): void
    {
        $member = Member::create('member-1', 'Juan Pérez', '12345678');
        
        $member->updateFullName('Juan Carlos Pérez');
        
        $this->assertEquals('Juan Carlos Pérez', $member->fullName());
    }

    /**
     * T-06: UpdateFullName también recorta espacios
     */
    public function test_update_full_name_trims_spaces(): void
    {
        $member = Member::create('member-1', 'Juan Pérez', '12345678');
        
        $member->updateFullName('  Nuevo Nombre  ');
        
        $this->assertEquals('Nuevo Nombre', $member->fullName());
    }

    /**
     * T-06: UpdateFullName rechaza nombre vacío
     */
    public function test_update_full_name_rejects_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        $member = Member::create('member-1', 'Juan Pérez', '12345678');
        $member->updateFullName('   ');
    }

    /**
     * T-06: Reconstitute crea socio con datos específicos
     */
    public function test_reconstitute_creates_member(): void
    {
        $member = Member::reconstitute(
            'member-1',
            'Ana López',
            '11111111'
        );
        
        $this->assertEquals('member-1', $member->id());
        $this->assertEquals('Ana López', $member->fullName());
        $this->assertEquals('11111111', $member->documentNumber());
    }
}
