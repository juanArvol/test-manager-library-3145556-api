<?php

declare(strict_types=1);

namespace Library\Adapters\Persistence;

use Illuminate\Support\Facades\DB;
use Library\Application\Ports\Outbound\CategoryRepository;

final class PostgresCategoryRepository implements CategoryRepository
{
    public function exists(string $categoryId): bool
    {
        return DB::table('category')
            ->where('category_id', $categoryId)
            ->exists();
    }

    public function getName(string $categoryId): ?string
    {
        $row = DB::table('category')
            ->where('category_id', $categoryId)
            ->first();

        return $row?->name;
    }
}
