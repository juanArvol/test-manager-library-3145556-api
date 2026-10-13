<?php

declare(strict_types=1);

namespace Library\Application\Ports\Outbound;

use Library\Domain\Member;

/**
 * MemberRepository - Puerto outbound
 * 
 * Repositorio de socios.
 */
interface MemberRepository
{
    /**
     * Guarda un socio (create o update)
     */
    public function save(Member $member): void;

    /**
     * Busca un socio por id
     * Retorna null si no existe
     */
    public function findById(string $id): ?Member;

    /**
     * Verifica si existe un socio con el documento dado
     */
    public function existsWithDocument(string $documentNumber): bool;
}
