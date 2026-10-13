<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\LoanRepository;
use Library\Domain\Loan;

/**
 * FakeLoanRepository - Implementación en memoria para tests
 */
final class FakeLoanRepository implements LoanRepository
{
    /** @var array<string, Loan> */
    private array $loans = [];

    public function save(Loan $loan): void
    {
        $this->loans[$loan->id()] = $loan;
    }

    public function findById(string $id): ?Loan
    {
        return $this->loans[$id] ?? null;
    }

    public function countUnreturnedItemsForMember(string $memberId): int
    {
        $count = 0;
        
        foreach ($this->loans as $loan) {
            if ($loan->memberId() === $memberId) {
                $count += $loan->countUnreturnedItems();
            }
        }
        
        return $count;
    }

    public function memberHasOverdueItems(string $memberId): bool
    {
        $now = new \DateTimeImmutable();
        
        foreach ($this->loans as $loan) {
            if ($loan->memberId() === $memberId && $loan->hasOverdueItems($now)) {
                return true;
            }
        }
        
        return false;
    }

    // Métodos de ayuda para tests
    
    public function addLoan(Loan $loan): void
    {
        $this->loans[$loan->id()] = $loan;
    }

    public function clear(): void
    {
        $this->loans = [];
    }

    public function count(): int
    {
        return count($this->loans);
    }
}
