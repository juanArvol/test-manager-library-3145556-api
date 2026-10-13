<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Library\Adapters\Persistence\PostgresUserRepository;
use Library\Domain\User;
use Tests\TestCase;

final class PostgresUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PostgresUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PostgresUserRepository();
    }

    /** @test */
    public function it_saves_and_retrieves_a_user(): void
    {
        $userId = $this->repository->nextIdentity();
        $user = new User(
            $userId,
            'johndoe',
            'hashed_password',
            'librarian'
        );

        $this->repository->save($user);

        $retrieved = $this->repository->findById($userId);

        $this->assertNotNull($retrieved);
        $this->assertSame($userId, $retrieved->getId());
        $this->assertSame('johndoe', $retrieved->getUsername());
        $this->assertSame('hashed_password', $retrieved->getPasswordHash());
        $this->assertSame('librarian', $retrieved->getRole());
    }

    /** @test */
    public function it_updates_an_existing_user(): void
    {
        $userId = $this->repository->nextIdentity();
        $user = new User(
            $userId,
            'johndoe',
            'old_hash',
            'librarian'
        );

        $this->repository->save($user);

        // Update password
        $updatedUser = new User(
            $userId,
            'johndoe',
            'new_hash',
            'librarian'
        );

        $this->repository->save($updatedUser);

        $retrieved = $this->repository->findById($userId);

        $this->assertSame('new_hash', $retrieved->getPasswordHash());
    }

    /** @test */
    public function it_finds_user_by_username(): void
    {
        $user = new User(
            $this->repository->nextIdentity(),
            'johndoe',
            'hashed_password',
            'librarian'
        );

        $this->repository->save($user);

        $retrieved = $this->repository->findByUsername('johndoe');

        $this->assertNotNull($retrieved);
        $this->assertSame('johndoe', $retrieved->getUsername());
    }

    /** @test */
    public function it_normalizes_username_when_finding(): void
    {
        $user = new User(
            $this->repository->nextIdentity(),
            'johndoe',
            'hashed_password',
            'librarian'
        );

        $this->repository->save($user);

        // Search with spaces and uppercase
        $retrieved = $this->repository->findByUsername('  JohnDoe  ');

        $this->assertNotNull($retrieved);
        $this->assertSame('johndoe', $retrieved->getUsername());
    }

    /** @test */
    public function it_returns_null_when_user_not_found(): void
    {
        $retrieved = $this->repository->findById('non-existent');

        $this->assertNull($retrieved);
    }

    /** @test */
    public function it_returns_null_when_username_not_found(): void
    {
        $retrieved = $this->repository->findByUsername('notfound');

        $this->assertNull($retrieved);
    }

    /** @test */
    public function it_stores_admin_role(): void
    {
        $userId = $this->repository->nextIdentity();
        $user = new User(
            $userId,
            'admin',
            'hashed_password',
            'admin'
        );

        $this->repository->save($user);

        $retrieved = $this->repository->findById($userId);

        $this->assertSame('admin', $retrieved->getRole());
    }

    /** @test */
    public function it_generates_unique_identities(): void
    {
        $id1 = $this->repository->nextIdentity();
        $id2 = $this->repository->nextIdentity();

        $this->assertNotSame($id1, $id2);
    }
}
