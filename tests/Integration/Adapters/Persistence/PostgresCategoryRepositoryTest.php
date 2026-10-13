<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Library\Adapters\Persistence\PostgresCategoryRepository;
use Tests\TestCase;

final class PostgresCategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PostgresCategoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PostgresCategoryRepository();

        // Create test categories
        DB::table('category')->insert([
            ['category_id' => 'cat-1', 'name' => 'Programming', 'created_at' => now()],
            ['category_id' => 'cat-2', 'name' => 'Science', 'created_at' => now()],
        ]);
    }

    /** @test */
    public function it_checks_if_category_exists(): void
    {
        $this->assertTrue($this->repository->exists('cat-1'));
        $this->assertFalse($this->repository->exists('non-existent'));
    }

    /** @test */
    public function it_gets_category_name(): void
    {
        $name = $this->repository->getName('cat-1');

        $this->assertSame('Programming', $name);
    }

    /** @test */
    public function it_returns_null_for_non_existent_category(): void
    {
        $name = $this->repository->getName('non-existent');

        $this->assertNull($name);
    }
}
