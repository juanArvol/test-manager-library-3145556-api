<?php

declare(strict_types=1);

namespace Library\Application\Ports\Outbound;

use Library\Domain\User;

/**
 * UserRepository - Puerto outbound
 * 
 * Repositorio de usuarios (admin y librarian).
 */
interface UserRepository
{
    /**
     * Guarda un usuario (create o update)
     */
    public function save(User $user): void;

    /**
     * Busca un usuario por username
     * Retorna null si no existe
     */
    public function findByUsername(string $username): ?User;

    /**
     * Busca un usuario por id
     * Retorna null si no existe
     */
    public function findById(string $id): ?User;

    /**
     * Verifica si existe un usuario con el username dado
     */
    public function existsWithUsername(string $username): bool;
}
