<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    /**
     * T-02: Verificar que las columnas existen según data-model.md §3
     * y que ninguna tiene DEFAULT
     */
    public function test_all_tables_and_columns_exist_without_defaults(): void
    {
        $expectedTables = [
            'library.category' => ['id', 'name'],
            'library.book' => ['id', 'title', 'author', 'isbn', 'category_id', 'total_copies', 'available_copies', 'cover_key', 'deleted_at'],
            'library.member' => ['id', 'full_name', 'document_number'],
            'library.user' => ['id', 'username', 'password_hash', 'role'],
            'library.loan' => ['id', 'member_id', 'loaned_by_user_id', 'loaned_at'],
            'library.loan_item' => ['id', 'loan_id', 'book_id', 'book_title', 'isbn', 'category_name', 'due_at', 'returned_at'],
        ];

        foreach ($expectedTables as $table => $columns) {
            foreach ($columns as $column) {
                $tableParts = explode('.', $table);
                $schema = $tableParts[0];
                $tableName = $tableParts[1];
                
                $result = DB::selectOne(
                    "SELECT column_name, data_type, is_nullable, column_default 
                     FROM information_schema.columns 
                     WHERE table_schema = ? AND table_name = ? AND column_name = ?",
                    [$schema, $tableName, $column]
                );
                
                $this->assertNotNull($result, "Column {$column} should exist in table {$table}");
                
                // Verificar que no hay DEFAULT (excepto para PKs autogeneradas por el motor)
                $this->assertNull($result->column_default, "Column {$table}.{$column} should not have DEFAULT value");
            }
        }
    }

    /**
     * T-02: Verificar que existen las restricciones (PKs, FKs, CHECKs)
     */
    public function test_constraints_exist(): void
    {
        // Verificar PKs
        $pks = DB::select(
            "SELECT c.conrelid::regclass as table_name, c.conname 
             FROM pg_constraint c 
             JOIN pg_namespace n ON n.oid = c.connamespace 
             WHERE n.nspname = 'library' AND c.contype = 'p' 
             ORDER BY 1"
        );
        
        $this->assertCount(6, $pks, 'Should have 6 primary keys (one per table)');

        // Verificar FKs
        $fks = DB::select(
            "SELECT c.conrelid::regclass as table_name, c.conname 
             FROM pg_constraint c 
             JOIN pg_namespace n ON n.oid = c.connamespace 
             WHERE n.nspname = 'library' AND c.contype = 'f' 
             ORDER BY 1"
        );
        
        $this->assertGreaterThanOrEqual(5, count($fks), 'Should have at least 5 foreign keys');

        // Verificar CHECK: ck_book_available_range
        $check = DB::selectOne(
            "SELECT c.conname 
             FROM pg_constraint c 
             JOIN pg_namespace n ON n.oid = c.connamespace 
             WHERE n.nspname = 'library' 
             AND c.contype = 'c' 
             AND c.conname = 'ck_book_available_range'"
        );
        
        $this->assertNotNull($check, 'CHECK constraint ck_book_available_range should exist');
    }

    /**
     * T-02: Verificar que existen los índices únicos
     */
    public function test_unique_indexes_exist(): void
    {
        $indexes = DB::select(
            "SELECT tablename, indexname 
             FROM pg_indexes 
             WHERE schemaname = 'library' 
             ORDER BY tablename, indexname"
        );
        
        $indexNames = array_column($indexes, 'indexname');
        
        // Índice único parcial: ISBN activos
        $this->assertContains('idx_book_isbn_active', $indexNames, 'Unique partial index on ISBN should exist');
        
        // Índice único: libro único por préstamo
        $this->assertContains('idx_loan_item_unique_book', $indexNames, 'Unique index on loan_item (loan_id, book_id) should exist');
        
        // Índices únicos en columnas
        $this->assertTrue(
            in_array('category_name_unique', $indexNames) || 
            in_array('library.category_name_unique', $indexNames) ||
            count(array_filter($indexNames, fn($n) => str_contains($n, 'category') && str_contains($n, 'name'))) > 0,
            'Unique index on category.name should exist'
        );
    }

    /**
     * T-02: Verificar que las cinco categorías están sembradas
     */
    public function test_five_categories_are_seeded(): void
    {
        $categories = DB::table('library.category')->orderBy('name')->get();
        
        $this->assertCount(5, $categories, 'Should have exactly 5 categories');
        
        $expectedCategories = [
            ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novela'],
            ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Ciencia'],
            ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Tecnología'],
            ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'Historia'],
            ['id' => '55555555-5555-4555-8555-555555555555', 'name' => 'Infantil'],
        ];
        
        foreach ($expectedCategories as $expected) {
            $category = DB::table('library.category')
                ->where('id', $expected['id'])
                ->first();
            
            $this->assertNotNull($category, "Category {$expected['name']} should exist");
            $this->assertEquals($expected['name'], $category->name);
        }
    }

    /**
     * T-02: Verificar que category_name en loan_item NO tiene FK (ADR-004)
     */
    public function test_loan_item_category_name_has_no_foreign_key(): void
    {
        $fks = DB::select(
            "SELECT c.conname, pg_get_constraintdef(c.oid) as definition
             FROM pg_constraint c 
             JOIN pg_namespace n ON n.oid = c.connamespace 
             WHERE n.nspname = 'library' 
             AND c.conrelid = 'library.loan_item'::regclass 
             AND c.contype = 'f'"
        );
        
        foreach ($fks as $fk) {
            $this->assertStringNotContainsString(
                'category_name',
                $fk->definition,
                'loan_item.category_name should NOT have foreign key (values are frozen)'
            );
        }
    }
}
