<?php

declare(strict_types=1);

namespace Library\Domain;

use Library\Domain\Exceptions\InvalidRoleException;

/**
 * User - Agregado raíz
 * 
 * Representa un usuario operador del sistema (admin o librarian).
 * Invariantes que defiende:
 * - username normalizado (minúsculas, recortado) y único
 * - rol válido: 'admin' o 'librarian'
 * - password_hash obligatorio (nunca en claro)
 */
final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_LIBRARIAN = 'librarian';

    private string $id;
    private string $username;
    private string $passwordHash;
    private string $role;

    private function __construct(
        string $id,
        string $username,
        string $passwordHash,
        string $role
    ) {
        $this->validateRole($role);
        
        $this->id = $id;
        $this->username = self::normalizeUsername($username);
        $this->passwordHash = $passwordHash;
        $this->role = $role;
    }

    /**
     * Crea un nuevo usuario
     */
    public static function create(
        string $id,
        string $username,
        string $passwordHash,
        string $role
    ): self {
        return new self($id, $username, $passwordHash, $role);
    }

    /**
     * Crea un bibliotecario (valida que no sea admin)
     */
    public static function createLibrarian(
        string $id,
        string $username,
        string $passwordHash
    ): self {
        return new self($id, $username, $passwordHash, self::ROLE_LIBRARIAN);
    }

    /**
     * Reconstituye un usuario desde persistencia
     */
    public static function reconstitute(
        string $id,
        string $username,
        string $passwordHash,
        string $role
    ): self {
        return new self($id, $username, $passwordHash, $role);
    }

    /**
     * Normaliza el username: minúsculas y recortado
     * 
     * Ejemplo: "Ana " → "ana"
     */
    public static function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    /**
     * Actualiza el password hash
     */
    public function updatePassword(string $newPasswordHash): void
    {
        $this->passwordHash = $newPasswordHash;
    }

    /**
     * Verifica si el usuario es administrador
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Verifica si el usuario es bibliotecario
     */
    public function isLibrarian(): bool
    {
        return $this->role === self::ROLE_LIBRARIAN;
    }

    /**
     * Verifica si un rol es válido
     */
    public static function isValidRole(string $role): bool
    {
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_LIBRARIAN], true);
    }

    // Getters

    public function id(): string
    {
        return $this->id;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): string
    {
        return $this->role;
    }

    // Validaciones privadas

    private function validateRole(string $role): void
    {
        if (!self::isValidRole($role)) {
            throw InvalidRoleException::forRole($role);
        }
    }
}
