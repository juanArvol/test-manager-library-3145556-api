<?php

namespace Tests\Unit\Application\Services;

use Library\Application\Exceptions\UsernameAlreadyExistsException;
use Library\Application\Services\CreateLibrarianService;
use Library\Domain\Exceptions\InvalidRoleException;
use Library\Domain\User;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Application\Fakes\FakePasswordHasher;
use Tests\Unit\Application\Fakes\FakeUserRepository;

class CreateLibrarianServiceTest extends TestCase
{
    private FakeUserRepository $userRepository;
    private FakePasswordHasher $passwordHasher;
    private CreateLibrarianService $service;

    protected function setUp(): void
    {
        $this->userRepository = new FakeUserRepository();
        $this->passwordHasher = new FakePasswordHasher();
        
        $this->service = new CreateLibrarianService(
            $this->userRepository,
            $this->passwordHasher
        );
    }

    /**
     * T-07 / HU-02 / CA-02.1: El admin crea un usuario con nombre, clave y rol librarian
     */
    public function test_creates_librarian_with_valid_data(): void
    {
        $librarian = $this->service->createLibrarian('juan', 'password123', 'librarian');
        
        $this->assertNotNull($librarian->id());
        $this->assertEquals('juan', $librarian->username());
        $this->assertEquals('librarian', $librarian->role());
        $this->assertTrue($librarian->isLibrarian());
        $this->assertFalse($librarian->isAdmin());
        
        // Verificar que se guardó
        $this->assertEquals(1, $this->userRepository->count());
    }

    /**
     * T-07 / HU-02 / CA-02.2: El nombre de usuario se guarda en minúsculas y recortado
     */
    public function test_normalizes_username_to_lowercase_and_trimmed(): void
    {
        $librarian = $this->service->createLibrarian('ANA ', 'password123', 'librarian');
        
        // CA-02.2: debe quedar en minúsculas y recortado
        $this->assertEquals('ana', $librarian->username());
    }

    /**
     * T-07 / HU-02 / CA-02.2: Username es único - lanza excepción si ya existe
     */
    public function test_rejects_duplicate_username(): void
    {
        $this->expectException(UsernameAlreadyExistsException::class);
        $this->expectExceptionMessage("El nombre de usuario 'juan' ya está registrado");
        
        // Crear primer bibliotecario
        $this->service->createLibrarian('juan', 'password123', 'librarian');
        
        // Intentar crear otro con el mismo username (normalizado)
        $this->service->createLibrarian('JUAN', 'password456', 'librarian');
    }

    /**
     * T-07 / HU-02 / CA-02.3: Pedir el rol admin por la API responde 422
     */
    public function test_rejects_admin_role_via_api(): void
    {
        $this->expectException(InvalidRoleException::class);
        $this->expectExceptionMessage("No se permite crear usuarios con rol 'admin' mediante la API");
        
        $this->service->createLibrarian('adminuser', 'password123', 'admin');
    }

    /**
     * T-07 / HU-02: Rechaza roles inválidos
     */
    public function test_rejects_invalid_roles(): void
    {
        $this->expectException(InvalidRoleException::class);
        $this->expectExceptionMessage("El rol 'superuser' no es válido");
        
        $this->service->createLibrarian('juan', 'password123', 'superuser');
    }

    /**
     * T-07 / HU-02 / CA-02.3: La contraseña se hashea antes de guardar
     */
    public function test_hashes_password_before_saving(): void
    {
        $librarian = $this->service->createLibrarian('ana', 'plainpassword', 'librarian');
        
        // Verificar que el password no está en claro
        $this->assertNotEquals('plainpassword', $librarian->passwordHash());
        $this->assertStringStartsWith('hashed:', $librarian->passwordHash());
    }

    /**
     * T-07 / HU-02: Crea múltiples bibliotecarios sin conflicto
     */
    public function test_creates_multiple_librarians(): void
    {
        $librarian1 = $this->service->createLibrarian('ana', 'pass1', 'librarian');
        $librarian2 = $this->service->createLibrarian('juan', 'pass2', 'librarian');
        $librarian3 = $this->service->createLibrarian('carlos', 'pass3', 'librarian');
        
        $this->assertEquals(3, $this->userRepository->count());
        $this->assertEquals('ana', $librarian1->username());
        $this->assertEquals('juan', $librarian2->username());
        $this->assertEquals('carlos', $librarian3->username());
    }

    /**
     * T-07: El bibliotecario creado es recuperable del repositorio
     */
    public function test_created_librarian_is_retrievable(): void
    {
        $librarian = $this->service->createLibrarian('maria', 'password123', 'librarian');
        
        $retrieved = $this->userRepository->findByUsername('maria');
        
        $this->assertNotNull($retrieved);
        $this->assertEquals($librarian->id(), $retrieved->id());
        $this->assertEquals('maria', $retrieved->username());
    }

    /**
     * T-07: CreateLibrarian solo acepta rol 'librarian' explícitamente
     */
    public function test_only_accepts_librarian_role_explicitly(): void
    {
        $librarian = $this->service->createLibrarian('pedro', 'password123', 'librarian');
        
        $this->assertEquals('librarian', $librarian->role());
    }
}
