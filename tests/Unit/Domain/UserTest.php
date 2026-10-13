<?php

namespace Tests\Unit\Domain;

use Library\Domain\Exceptions\InvalidRoleException;
use Library\Domain\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /**
     * T-06: Crear usuario con rol admin
     */
    public function test_creates_user_with_admin_role(): void
    {
        $user = User::create(
            id: 'user-1',
            username: 'admin',
            passwordHash: 'hash123',
            role: 'admin'
        );
        
        $this->assertEquals('user-1', $user->id());
        $this->assertEquals('admin', $user->username());
        $this->assertEquals('hash123', $user->passwordHash());
        $this->assertEquals('admin', $user->role());
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isLibrarian());
    }

    /**
     * T-06: Crear usuario con rol librarian
     */
    public function test_creates_user_with_librarian_role(): void
    {
        $user = User::create(
            'user-1',
            'ana',
            'hash456',
            'librarian'
        );
        
        $this->assertEquals('librarian', $user->role());
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isLibrarian());
    }

    /**
     * T-06: NormalizeUsername convierte a minúsculas
     */
    public function test_normalize_username_converts_to_lowercase(): void
    {
        $normalized = User::normalizeUsername('ANA');
        
        $this->assertEquals('ana', $normalized);
    }

    /**
     * T-06: NormalizeUsername recorta espacios - "Ana " → "ana"
     */
    public function test_normalize_username_trims_spaces(): void
    {
        $normalized = User::normalizeUsername('Ana ');
        
        $this->assertEquals('ana', $normalized);
    }

    /**
     * T-06: NormalizeUsername combina minúsculas y trim
     */
    public function test_normalize_username_combines_lowercase_and_trim(): void
    {
        $normalized = User::normalizeUsername('  María García  ');
        
        $this->assertEquals('maría garcía', $normalized);
    }

    /**
     * T-06: Username se normaliza al crear usuario
     */
    public function test_username_is_normalized_on_creation(): void
    {
        $user = User::create('user-1', 'Ana ', 'hash123', 'librarian');
        
        $this->assertEquals('ana', $user->username());
    }

    /**
     * T-06: CreateLibrarian crea usuario con rol librarian
     */
    public function test_create_librarian_creates_user_with_librarian_role(): void
    {
        $user = User::createLibrarian(
            'user-1',
            'juan',
            'hash789'
        );
        
        $this->assertEquals('librarian', $user->role());
        $this->assertTrue($user->isLibrarian());
        $this->assertFalse($user->isAdmin());
    }

    /**
     * T-06: CreateLibrarian también normaliza username
     */
    public function test_create_librarian_normalizes_username(): void
    {
        $user = User::createLibrarian('user-1', 'JUAN ', 'hash789');
        
        $this->assertEquals('juan', $user->username());
    }

    /**
     * T-06: Rechaza rol inválido
     */
    public function test_rejects_invalid_role(): void
    {
        $this->expectException(InvalidRoleException::class);
        $this->expectExceptionMessage("El rol 'superuser' no es válido");
        
        User::create('user-1', 'ana', 'hash123', 'superuser');
    }

    /**
     * T-06: Rechaza rol vacío
     */
    public function test_rejects_empty_role(): void
    {
        $this->expectException(InvalidRoleException::class);
        
        User::create('user-1', 'ana', 'hash123', '');
    }

    /**
     * T-06: IsValidRole verifica roles válidos
     */
    public function test_is_valid_role_validates_roles(): void
    {
        $this->assertTrue(User::isValidRole('admin'));
        $this->assertTrue(User::isValidRole('librarian'));
        $this->assertFalse(User::isValidRole('superuser'));
        $this->assertFalse(User::isValidRole(''));
        $this->assertFalse(User::isValidRole('ADMIN')); // Case-sensitive
    }

    /**
     * T-06: UpdatePassword cambia el hash
     */
    public function test_update_password_changes_hash(): void
    {
        $user = User::create('user-1', 'ana', 'oldHash', 'librarian');
        
        $user->updatePassword('newHash');
        
        $this->assertEquals('newHash', $user->passwordHash());
    }

    /**
     * T-06: Reconstitute crea usuario con datos específicos
     */
    public function test_reconstitute_creates_user(): void
    {
        $user = User::reconstitute(
            'user-1',
            'carlos',
            'hash555',
            'admin'
        );
        
        $this->assertEquals('user-1', $user->id());
        $this->assertEquals('carlos', $user->username());
        $this->assertEquals('hash555', $user->passwordHash());
        $this->assertEquals('admin', $user->role());
    }

    /**
     * T-06: Reconstitute también normaliza username
     */
    public function test_reconstitute_normalizes_username(): void
    {
        $user = User::reconstitute('user-1', 'CARLOS ', 'hash555', 'admin');
        
        $this->assertEquals('carlos', $user->username());
    }

    /**
     * T-06: Rol admin por API se debe rechazar (lógica de aplicación)
     * Nota: Esta validación se implementará en el servicio de aplicación,
     * pero el dominio permite crear usuarios admin (para el arranque desde entorno)
     */
    public function test_domain_allows_admin_role_for_bootstrap(): void
    {
        // El dominio permite crear admin (necesario para arranque)
        $user = User::create('user-1', 'admin', 'hash', 'admin');
        
        $this->assertEquals('admin', $user->role());
        
        // La restricción "admin no por API" será en el servicio de aplicación
        // que solo usará User::createLibrarian() para peticiones HTTP
    }
}
