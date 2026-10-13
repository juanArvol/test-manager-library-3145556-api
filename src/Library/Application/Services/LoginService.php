<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Exceptions\InvalidCredentialsException;
use Library\Application\Ports\Outbound\PasswordHasher;
use Library\Application\Ports\Outbound\TokenIssuer;
use Library\Application\Ports\Outbound\UserRepository;
use Library\Domain\User;

/**
 * LoginService - Caso de uso de autenticación (HU-01)
 * 
 * Verifica credenciales y emite token JWT.
 */
final readonly class LoginService
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordHasher $passwordHasher,
        private TokenIssuer $tokenIssuer
    ) {}

    /**
     * Autentica un usuario y retorna token
     * 
     * CA-01.1: Con credenciales válidas se recibe un token con vigencia de 8 horas
     * CA-01.2: Con credenciales inválidas responde 401 sin decir si falló usuario o clave
     * 
     * @return array ['access_token' => string, 'token_type' => 'Bearer', 'expires_in' => int, 'role' => string]
     * @throws InvalidCredentialsException
     */
    public function login(string $username, string $plainPassword): array
    {
        // Normalizar username (como lo hace el dominio)
        $normalizedUsername = User::normalizeUsername($username);
        
        // Buscar usuario
        $user = $this->userRepository->findByUsername($normalizedUsername);
        
        // Si no existe o la contraseña no coincide: mismo error
        // CA-01.2: no decir si falló el usuario o la clave
        if ($user === null || !$this->passwordHasher->verify($plainPassword, $user->passwordHash())) {
            throw InvalidCredentialsException::forLogin();
        }
        
        // Emitir token
        $tokenData = $this->tokenIssuer->issue($user->id(), $user->role());
        
        // Agregar rol a la respuesta
        $tokenData['role'] = $user->role();
        
        return $tokenData;
    }
}
