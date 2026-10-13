<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Library\Adapters\Persistence\PostgresMemberRepository;
use Library\Domain\Member;
use Tests\TestCase;

final class PostgresMemberRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PostgresMemberRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PostgresMemberRepository();
    }

    /** @test */
    public function it_saves_and_retrieves_a_member(): void
    {
        $memberId = $this->repository->nextIdentity();
        $member = new Member(
            $memberId,
            'John Doe',
            'john@example.com'
        );

        $this->repository->save($member);

        $retrieved = $this->repository->findById($memberId);

        $this->assertNotNull($retrieved);
        $this->assertSame($memberId, $retrieved->getId());
        $this->assertSame('John Doe', $retrieved->getFullName());
        $this->assertSame('john@example.com', $retrieved->getEmail());
    }

    /** @test */
    public function it_updates_an_existing_member(): void
    {
        $memberId = $this->repository->nextIdentity();
        $member = new Member(
            $memberId,
            'John Doe',
            'john@example.com'
        );

        $this->repository->save($member);

        // Update
        $updatedMember = new Member(
            $memberId,
            'Jane Doe',
            'jane@example.com'
        );

        $this->repository->save($updatedMember);

        $retrieved = $this->repository->findById($memberId);

        $this->assertSame('Jane Doe', $retrieved->getFullName());
        $this->assertSame('jane@example.com', $retrieved->getEmail());
    }

    /** @test */
    public function it_finds_member_by_email(): void
    {
        $member = new Member(
            $this->repository->nextIdentity(),
            'John Doe',
            'john@example.com'
        );

        $this->repository->save($member);

        $retrieved = $this->repository->findByEmail('john@example.com');

        $this->assertNotNull($retrieved);
        $this->assertSame('john@example.com', $retrieved->getEmail());
        $this->assertSame('John Doe', $retrieved->getFullName());
    }

    /** @test */
    public function it_returns_null_when_member_not_found(): void
    {
        $retrieved = $this->repository->findById('non-existent');

        $this->assertNull($retrieved);
    }

    /** @test */
    public function it_returns_null_when_email_not_found(): void
    {
        $retrieved = $this->repository->findByEmail('notfound@example.com');

        $this->assertNull($retrieved);
    }

    /** @test */
    public function it_generates_unique_identities(): void
    {
        $id1 = $this->repository->nextIdentity();
        $id2 = $this->repository->nextIdentity();

        $this->assertNotSame($id1, $id2);
    }
}
