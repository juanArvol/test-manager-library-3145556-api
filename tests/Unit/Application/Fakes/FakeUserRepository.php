<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\UserRepository;
use Library\Domain\User;

/**
 * FakeUserRepository - Implementación en memoria para tests
 */
final class FakeUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->id()] = $user;
    }

    public function findByUsername(string $username): ?User
    {
        foreach ($this->users as $user) {
            if ($user->username() === $username) {
                return $user;
            }
        }
        
        return null;
    }

    public function findById(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function existsWithUsername(string $username): bool
    {
        return $this->findByUsername($username) !== null;
    }

    // Métodos de ayuda para tests
    
    public function addUser(User $user): void
    {
        $this->users[$user->id()] = $user;
    }

    public function clear(): void
    {
        $this->users = [];
    }

    public function count(): int
    {
        return count($this->users);
    }
}
