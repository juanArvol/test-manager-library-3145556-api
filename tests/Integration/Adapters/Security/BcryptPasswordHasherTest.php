<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters\Security;

use Library\Adapters\Security\BcryptPasswordHasher;
use PHPUnit\Framework\TestCase;

final class BcryptPasswordHasherTest extends TestCase
{
    private BcryptPasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new BcryptPasswordHasher();
    }

    /** @test */
    public function it_hashes_a_password(): void
    {
        $plain = 'my-secret-password';
        $hashed = $this->hasher->hash($plain);

        $this->assertNotSame($plain, $hashed);
        $this->assertStringStartsWith('$2y$', $hashed);
    }

    /** @test */
    public function it_verifies_correct_password(): void
    {
        $plain = 'my-secret-password';
        $hashed = $this->hasher->hash($plain);

        $this->assertTrue($this->hasher->verify($plain, $hashed));
    }

    /** @test */
    public function it_rejects_incorrect_password(): void
    {
        $plain = 'my-secret-password';
        $hashed = $this->hasher->hash($plain);

        $this->assertFalse($this->hasher->verify('wrong-password', $hashed));
    }

    /** @test */
    public function it_never_exposes_plain_password_in_hash(): void
    {
        $plain = 'my-secret-password';
        $hashed = $this->hasher->hash($plain);

        $this->assertStringNotContainsString($plain, $hashed);
    }

    /** @test */
    public function it_generates_different_hashes_for_same_password(): void
    {
        $plain = 'my-secret-password';
        $hash1 = $this->hasher->hash($plain);
        $hash2 = $this->hasher->hash($plain);

        $this->assertNotSame($hash1, $hash2);
        $this->assertTrue($this->hasher->verify($plain, $hash1));
        $this->assertTrue($this->hasher->verify($plain, $hash2));
    }
}
