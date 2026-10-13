<?php

declare(strict_types=1);

namespace Library\Adapters\Persistence;

use Illuminate\Support\Facades\DB;
use Library\Application\Ports\Outbound\UserRepository;
use Library\Domain\User;
use Ramsey\Uuid\Uuid;

final class PostgresUserRepository implements UserRepository
{
    public function save(User $user): void
    {
        $existing = DB::table('user')
            ->where('user_id', $user->getId())
            ->first();

        if ($existing) {
            DB::table('user')
                ->where('user_id', $user->getId())
                ->update([
                    'username' => $user->getUsername(),
                    'password_hash' => $user->getPasswordHash(),
                    'role' => $user->getRole(),
                ]);
        } else {
            DB::table('user')->insert([
                'user_id' => $user->getId(),
                'username' => $user->getUsername(),
                'password_hash' => $user->getPasswordHash(),
                'role' => $user->getRole(),
                'created_at' => now(),
            ]);
        }
    }

    public function findById(string $userId): ?User
    {
        $row = DB::table('user')
            ->where('user_id', $userId)
            ->first();

        if (!$row) {
            return null;
        }

        return new User(
            $row->user_id,
            $row->username,
            $row->password_hash,
            $row->role
        );
    }

    public function findByUsername(string $username): ?User
    {
        $normalized = User::normalizeUsername($username);

        $row = DB::table('user')
            ->where('username', $normalized)
            ->first();

        if (!$row) {
            return null;
        }

        return new User(
            $row->user_id,
            $row->username,
            $row->password_hash,
            $row->role
        );
    }

    public function nextIdentity(): string
    {
        return Uuid::uuid4()->toString();
    }
}
