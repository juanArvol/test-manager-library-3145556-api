<?php

declare(strict_types=1);

namespace Library\Adapters\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Library\Application\Ports\Outbound\TokenIssuer;

final class JwtTokenIssuer implements TokenIssuer
{
    private string $secret;
    private string $algorithm;

    public function __construct(string $secret, string $algorithm = 'HS256')
    {
        $this->secret = $secret;
        $this->algorithm = $algorithm;
    }

    public function issue(string $userId, string $role): string
    {
        $payload = [
            'sub' => $userId,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24), // 24 hours
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function verify(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return [
                'user_id' => $decoded->sub,
                'role' => $decoded->role,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
