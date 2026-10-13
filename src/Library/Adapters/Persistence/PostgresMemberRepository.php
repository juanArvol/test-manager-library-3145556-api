<?php

declare(strict_types=1);

namespace Library\Adapters\Persistence;

use Illuminate\Support\Facades\DB;
use Library\Application\Ports\Outbound\MemberRepository;
use Library\Domain\Member;
use Ramsey\Uuid\Uuid;

final class PostgresMemberRepository implements MemberRepository
{
    public function save(Member $member): void
    {
        $existing = DB::table('member')
            ->where('member_id', $member->getId())
            ->first();

        if ($existing) {
            DB::table('member')
                ->where('member_id', $member->getId())
                ->update([
                    'full_name' => $member->getFullName(),
                    'email' => $member->getEmail(),
                ]);
        } else {
            DB::table('member')->insert([
                'member_id' => $member->getId(),
                'full_name' => $member->getFullName(),
                'email' => $member->getEmail(),
                'created_at' => now(),
            ]);
        }
    }

    public function findById(string $memberId): ?Member
    {
        $row = DB::table('member')
            ->where('member_id', $memberId)
            ->first();

        if (!$row) {
            return null;
        }

        return new Member(
            $row->member_id,
            $row->full_name,
            $row->email
        );
    }

    public function findByEmail(string $email): ?Member
    {
        $row = DB::table('member')
            ->where('email', $email)
            ->first();

        if (!$row) {
            return null;
        }

        return new Member(
            $row->member_id,
            $row->full_name,
            $row->email
        );
    }

    public function nextIdentity(): string
    {
        return Uuid::uuid4()->toString();
    }
}
