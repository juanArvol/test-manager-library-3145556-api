<?php

namespace Tests\Unit\Application\Services;

use Library\Application\Exceptions\InvalidCredentialsException;
use Library\Application\Services\LoginService;
use Library\Domain\User;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Application\Fakes\FakePasswordHasher;
use Tests\Unit\Application\Fakes\FakeTokenIssuer;
use Tests\Unit\Application\Fakes\FakeUserRepository;

class LoginServiceTest extends TestCase
{
    private FakeUserRepository $userRepository;
    private FakePasswordHasher $passwordHasher;
    private FakeTokenIssuer $tokenIssuer;
    private LoginService $loginService;

    protected function setUp(): void
    {
        $this->userRepository = new FakeUserRepository();
        $this->passwordHasher = new FakePasswordHasher();
        $this->tokenIssuer = new FakeTokenIssuer();
        
        $this->loginService = new LoginService(
            $this->userRepository,
            $this->passwordHasher,
            $this->tokenIssuer
        );
    }

    /**
     * T-07 / HU-01 / CA-01.1: Con credenciales válidas se recibe un token con vigencia de 8 horas
     */
    public function test_login_with_valid_credentials_returns_token(): void
    {
        // Preparar: crear usuario en el repositorio
        $user = User::create(
            'user-1',
            'ana',
            $this->passwordHasher->hash('password123'),
            'librarian'
        );
        $this->userRepository->addUser($user);
        
        // Ejecutar: login con credenciales válidas
        $result = $this->loginService->login('ana', 'password123');
        
        // Verificar: CA-01.1
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertArrayHasKey('role', $result);
        
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertEquals(28800, $result['expires_in']); // 8 horas
        $this->assertEquals('librarian', $result['role']);
        $this->assertStringStartsWith('fake-token-for-', $result['access_token']);
    }

    /**
     * T-07 / HU-01 / CA-01.1: Token incluye el rol del usuario
     */
    public function test_login_includes_user_role_in_response(): void
    {
        $admin = User::create(
            'admin-1',
            'admin',
            $this->passwordHasher->hash('admin123'),
            'admin'
        );
        $this->userRepository->addUser($admin);
        
        $result = $this->loginService->login('admin', 'admin123');
        
        $this->assertEquals('admin', $result['role']);
    }

    /**
     * T-07 / HU-01 / CA-01.2: Con usuario inexistente responde con InvalidCredentialsException
     */
    public function test_login_with_nonexistent_user_throws_invalid_credentials(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Credenciales inválidas');
        
        $this->loginService->login('noexiste', 'password123');
    }

    /**
     * T-07 / HU-01 / CA-01.2: Con contraseña incorrecta responde con InvalidCredentialsException
     */
    public function test_login_with_wrong_password_throws_invalid_credentials(): void
    {
        $user = User::create(
            'user-1',
            'ana',
            $this->passwordHasher->hash('password123'),
            'librarian'
        );
        $this->userRepository->addUser($user);
        
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Credenciales inválidas');
        
        $this->loginService->login('ana', 'wrongpassword');
    }

    /**
     * T-07 / HU-01 / CA-01.2: El mensaje de error no distingue entre usuario inválido o clave inválida
     */
    public function test_login_error_message_does_not_distinguish_user_from_password(): void
    {
        $user = User::create(
            'user-1',
            'ana',
            $this->passwordHasher->hash('password123'),
            'librarian'
        );
        $this->userRepository->addUser($user);
        
        // Error por usuario inexistente
        try {
            $this->loginService->login('noexiste', 'password123');
            $this->fail('Should throw InvalidCredentialsException');
        } catch (InvalidCredentialsException $e1) {
            $message1 = $e1->getMessage();
        }
        
        // Error por contraseña incorrecta
        try {
            $this->loginService->login('ana', 'wrongpassword');
            $this->fail('Should throw InvalidCredentialsException');
        } catch (InvalidCredentialsException $e2) {
            $message2 = $e2->getMessage();
        }
        
        // Ambos mensajes deben ser idénticos (CA-01.2)
        $this->assertEquals($message1, $message2);
        $this->assertEquals('Credenciales inválidas.', $message1);
    }

    /**
     * T-07: Login normaliza el username antes de buscar
     */
    public function test_login_normalizes_username(): void
    {
        $user = User::create(
            'user-1',
            'ana',
            $this->passwordHasher->hash('password123'),
            'librarian'
        );
        $this->userRepository->addUser($user);
        
        // Login con username en mayúsculas y espacios
        $result = $this->loginService->login('ANA ', 'password123');
        
        $this->assertArrayHasKey('access_token', $result);
    }

    /**
     * T-07: Login funciona con admin
     */
    public function test_login_works_with_admin_user(): void
    {
        $admin = User::create(
            'admin-1',
            'superadmin',
            $this->passwordHasher->hash('admin123'),
            'admin'
        );
        $this->userRepository->addUser($admin);
        
        $result = $this->loginService->login('superadmin', 'admin123');
        
        $this->assertEquals('admin', $result['role']);
        $this->assertArrayHasKey('access_token', $result);
    }
}
