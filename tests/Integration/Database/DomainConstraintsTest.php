<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DomainConstraintsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_book_with_zero_total_quantity(): void
    {
        $this->expectException(\Exception::class);

        DB::table('category')->insert([
            'category_id' => 'cat-1',
            'name' => 'Test',
            'created_at' => now(),
        ]);

        DB::table('book')->insert([
            'book_id' => 'book-1',
            'title' => 'Test Book',
            'isbn' => '9780000000001',
            'category_id' => 'cat-1',
            'total_quantity' => 0, // Violates constraint
            'available_quantity' => 0,
            'created_at' => now(),
        ]);
    }

    /** @test */
    public function it_rejects_book_with_negative_total_quantity(): void
    {
        $this->expectException(\Exception::class);

        DB::table('category')->insert([
            'category_id' => 'cat-1',
            'name' => 'Test',
            'created_at' => now(),
        ]);

        DB::table('book')->insert([
            'book_id' => 'book-1',
            'title' => 'Test Book',
            'isbn' => '9780000000001',
            'category_id' => 'cat-1',
            'total_quantity' => -1, // Violates constraint
            'available_quantity' => 0,
            'created_at' => now(),
        ]);
    }

    /** @test */
    public function it_accepts_book_with_positive_total_quantity(): void
    {
        DB::table('category')->insert([
            'category_id' => 'cat-1',
            'name' => 'Test',
            'created_at' => now(),
        ]);

        DB::table('book')->insert([
            'book_id' => 'book-1',
            'title' => 'Test Book',
            'isbn' => '9780000000001',
            'category_id' => 'cat-1',
            'total_quantity' => 5, // Valid
            'available_quantity' => 5,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('book', ['book_id' => 'book-1']);
    }

    /** @test */
    public function it_rejects_user_with_invalid_role(): void
    {
        $this->expectException(\Exception::class);

        DB::table('user')->insert([
            'user_id' => 'user-1',
            'username' => 'testuser',
            'password_hash' => 'hash',
            'role' => 'superuser', // Invalid role
            'created_at' => now(),
        ]);
    }

    /** @test */
    public function it_accepts_user_with_admin_role(): void
    {
        DB::table('user')->insert([
            'user_id' => 'user-1',
            'username' => 'admin',
            'password_hash' => 'hash',
            'role' => 'admin', // Valid
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('user', ['user_id' => 'user-1']);
    }

    /** @test */
    public function it_accepts_user_with_librarian_role(): void
    {
        DB::table('user')->insert([
            'user_id' => 'user-1',
            'username' => 'librarian',
            'password_hash' => 'hash',
            'role' => 'librarian', // Valid
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('user', ['user_id' => 'user-1']);
    }
}
