<?php

namespace Tests\Unit\Application\Fakes;

use DateTimeImmutable;
use Library\Application\Ports\Outbound\Clock;

/**
 * FakeClock - Reloj controlable para tests
 */
final class FakeClock implements Clock
{
    private DateTimeImmutable $fixedNow;

    public function __construct(?DateTimeImmutable $fixedNow = null)
    {
        $this->fixedNow = $fixedNow ?? new DateTimeImmutable();
    }

    public function now(): DateTimeImmutable
    {
        return $this->fixedNow;
    }

    public function setNow(DateTimeImmutable $now): void
    {
        $this->fixedNow = $now;
    }
}
