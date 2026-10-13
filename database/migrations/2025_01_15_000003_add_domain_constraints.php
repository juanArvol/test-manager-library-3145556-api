<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Constraint: book.total_quantity must be positive (> 0)
        DB::statement('
            ALTER TABLE book
            ADD CONSTRAINT ck_book_total_positive
            CHECK (total_quantity > 0)
        ');

        // Constraint: user.role must be 'admin' or 'librarian'
        DB::statement("
            ALTER TABLE \"user\"
            ADD CONSTRAINT ck_user_role
            CHECK (role IN ('admin', 'librarian'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE book DROP CONSTRAINT IF EXISTS ck_book_total_positive');
        DB::statement('ALTER TABLE "user" DROP CONSTRAINT IF EXISTS ck_user_role');
    }
};
