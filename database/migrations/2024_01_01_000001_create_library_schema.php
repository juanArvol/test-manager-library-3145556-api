<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Crea el esquema library y las seis tablas según data-model.md
     * ADR-001: El esquema es propiedad de las migraciones
     */
    public function up(): void
    {
        // Crear esquema library
        DB::statement('CREATE SCHEMA IF NOT EXISTS library');
        
        // Tabla category - semilla de solo lectura
        Schema::create('library.category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
        });
        
        // Tabla book
        Schema::create('library.book', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 250);
            $table->string('author', 200);
            $table->char('isbn', 13);
            $table->uuid('category_id');
            $table->integer('total_copies');
            $table->integer('available_copies');
            $table->string('cover_key', 512)->nullable();
            $table->timestampTz('deleted_at')->nullable();
            
            // FK restrictiva a category
            $table->foreign('category_id')
                  ->references('id')
                  ->on('library.category')
                  ->onDelete('restrict')
                  ->onUpdate('no action');
            
            // CHECK: 0 <= available_copies <= total_copies (motor - última barrera)
            DB::statement('ALTER TABLE library.book ADD CONSTRAINT ck_book_available_range CHECK (available_copies >= 0 AND available_copies <= total_copies)');
        });
        
        // Índice único parcial: ISBN único entre libros activos
        DB::statement('CREATE UNIQUE INDEX idx_book_isbn_active ON library.book (isbn) WHERE deleted_at IS NULL');
        
        // Índice de acceso: búsqueda por categoría y título (solo activos)
        DB::statement('CREATE INDEX idx_book_category_title ON library.book (category_id, title) WHERE deleted_at IS NULL');
        
        // Tabla member
        Schema::create('library.member', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 200);
            $table->string('document_number', 30)->unique();
        });
        
        // Tabla user (entrecomillada para PostgreSQL)
        Schema::create('library.user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username', 120)->unique();
            $table->string('password_hash', 512);
            $table->string('role', 40);
        });
        
        // Tabla loan
        Schema::create('library.loan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->uuid('loaned_by_user_id');
            $table->timestampTz('loaned_at');
            
            // FK restrictivas
            $table->foreign('member_id')
                  ->references('id')
                  ->on('library.member')
                  ->onDelete('restrict')
                  ->onUpdate('no action');
            
            $table->foreign('loaned_by_user_id')
                  ->references('id')
                  ->on('library.user')
                  ->onDelete('restrict')
                  ->onUpdate('no action');
        });
        
        // Índice de acceso: préstamos por fecha
        DB::statement('CREATE INDEX idx_loan_loaned_at ON library.loan (loaned_at)');
        
        // Tabla loan_item
        Schema::create('library.loan_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_id');
            $table->uuid('book_id');
            $table->string('book_title', 250);
            $table->char('isbn', 13);
            $table->string('category_name', 120); // Congelado, sin FK a propósito (ADR-004)
            $table->timestampTz('due_at');
            $table->timestampTz('returned_at')->nullable();
            
            // FK con CASCADE a loan (composición)
            $table->foreign('loan_id')
                  ->references('id')
                  ->on('library.loan')
                  ->onDelete('cascade')
                  ->onUpdate('no action');
            
            // FK restrictiva a book (barrera de última instancia - ADR-003)
            $table->foreign('book_id')
                  ->references('id')
                  ->on('library.book')
                  ->onDelete('restrict')
                  ->onUpdate('no action');
        });
        
        // Índice único: no repetir el mismo libro en un préstamo
        DB::statement('CREATE UNIQUE INDEX idx_loan_item_unique_book ON library.loan_item (loan_id, book_id)');
        
        // Índices de acceso
        DB::statement('CREATE INDEX idx_loan_item_book_id ON library.loan_item (book_id)');
        DB::statement('CREATE INDEX idx_loan_item_due_at ON library.loan_item (due_at) WHERE returned_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library.loan_item');
        Schema::dropIfExists('library.loan');
        Schema::dropIfExists('library.user');
        Schema::dropIfExists('library.member');
        Schema::dropIfExists('library.book');
        Schema::dropIfExists('library.category');
        DB::statement('DROP SCHEMA IF EXISTS library CASCADE');
    }
};
