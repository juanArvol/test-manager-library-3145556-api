<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters\Security;

use Library\Adapters\Security\JwtTokenIssuer;
use PHPUnit\Framework\TestCase;

final class JwtTokenIssuerTest extends TestCase
{
    private JwtTokenIssuer $issuer;

    protected function setUp(): void
    {
        $this->issuer = new JwtTokenIssuer('test-secret-key');
    }

    /** @test */
    public function it_issues_a_token(): void
    {
        $token = $this->issuer->issue('user-123', 'librarian');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    /** @test */
    public function it_verifies_a_valid_token(): void
    {
        $token = $this->issuer->issue('user-123', 'librarian');

        $payload = $this->issuer->verify($token);

        $this->assertNotNull($payload);
        $this->assertSame('user-123', $payload['user_id']);
        $this->assertSame('librarian', $payload['role']);
    }

    /** @test */
    public function it_returns_null_for_invalid_token(): void
    {
        $payload = $this->issuer->verify('invalid-token');

        $this->assertNull($payload);
    }

    /** @test */
    public function it_returns_null_for_tampered_token(): void
    {
        $token = $this->issuer->issue('user-123', 'librarian');
        $tamperedToken = $token . 'extra';

        $payload = $this->issuer->verify($tamperedToken);

        $this->assertNull($payload);
    }

    /** @test */
    public function it_includes_role_in_token(): void
    {
        $adminToken = $this->issuer->issue('admin-id', 'admin');
        $librarianToken = $this->issuer->issue('librarian-id', 'librarian');

        $adminPayload = $this->issuer->verify($adminToken);
        $librarianPayload = $this->issuer->verify($librarianToken);

        $this->assertSame('admin', $adminPayload['role']);
        $this->assertSame('librarian', $librarianPayload['role']);
    }

    /** @test */
    public function it_never_exposes_secret_in_token(): void
    {
        $token = $this->issuer->issue('user-123', 'librarian');

        $this->assertStringNotContainsString('test-secret-key', $token);
    }
}
