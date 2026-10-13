<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\MemberRepository;
use Library\Domain\Member;

/**
 * FakeMemberRepository - Implementación en memoria para tests
 */
final class FakeMemberRepository implements MemberRepository
{
    /** @var array<string, Member> */
    private array $members = [];

    public function save(Member $member): void
    {
        $this->members[$member->id()] = $member;
    }

    public function findById(string $id): ?Member
    {
        return $this->members[$id] ?? null;
    }

    public function existsWithDocument(string $documentNumber): bool
    {
        foreach ($this->members as $member) {
            if ($member->documentNumber() === $documentNumber) {
                return true;
            }
        }
        
        return false;
    }

    // Métodos de ayuda para tests
    
    public function addMember(Member $member): void
    {
        $this->members[$member->id()] = $member;
    }

    public function clear(): void
    {
        $this->members = [];
    }

    public function count(): int
    {
        return count($this->members);
    }
}
