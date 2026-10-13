<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Exceptions\UsernameAlreadyExistsException;
use Library\Application\Ports\Outbound\PasswordHasher;
use Library\Application\Ports\Outbound\UserRepository;
use Library\Domain\Exceptions\InvalidRoleException;
use Library\Domain\User;
use Ramsey\Uuid\Uuid;

/**
 * CreateLibrarianService - Caso de uso de creación de bibliotecarios (HU-02)
 * 
 * Solo el admin puede crear bibliotecarios.
 */
final readonly class CreateLibrarianService
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordHasher $passwordHasher
    ) {}

    /**
     * Crea un nuevo bibliotecario
     * 
     * CA-02.1: El admin crea un usuario con nombre, clave y rol librarian
     * CA-02.2: El nombre de usuario se guarda en minúsculas y recortado; es único
     * CA-02.3: Pedir el rol admin por la API responde 422
     * 
     * @return User
     * @throws UsernameAlreadyExistsException si el username ya existe
     * @throws InvalidRoleException si se intenta crear con rol admin
     */
    public function createLibrarian(string $username, string $plainPassword, string $requestedRole): User
    {
        // CA-02.3: rechazar rol admin por API
        if ($requestedRole === User::ROLE_ADMIN) {
            throw InvalidRoleException::adminNotAllowedViaApi();
        }
        
        // Validar que el rol sea válido (aunque solo aceptamos librarian)
        if ($requestedRole !== User::ROLE_LIBRARIAN) {
            throw InvalidRoleException::forRole($requestedRole);
        }
        
        // CA-02.2: normalizar username
        $normalizedUsername = User::normalizeUsername($username);
        
        // CA-02.2: verificar unicidad
        if ($this->userRepository->existsWithUsername($normalizedUsername)) {
            throw UsernameAlreadyExistsException::forUsername($normalizedUsername);
        }
        
        // Hashear contraseña (CA-02.3: nunca se guarda ni devuelve en claro)
        $passwordHash = $this->passwordHasher->hash($plainPassword);
        
        // Crear usuario bibliotecario
        $user = User::createLibrarian(
            id: Uuid::uuid4()->toString(),
            username: $normalizedUsername,
            passwordHash: $passwordHash
        );
        
        // Guardar
        $this->userRepository->save($user);
        
        return $user;
    }
}
