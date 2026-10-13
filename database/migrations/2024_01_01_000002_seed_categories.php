<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Semilla de las cinco categorías con identificadores fijos
     * según data-model.md §9
     */
    public function up(): void
    {
        DB::table('library.category')->insert([
            [
                'id' => '11111111-1111-4111-8111-111111111111',
                'name' => 'Novela'
            ],
            [
                'id' => '22222222-2222-4222-8222-222222222222',
                'name' => 'Ciencia'
            ],
            [
                'id' => '33333333-3333-4333-8333-333333333333',
                'name' => 'Tecnología'
            ],
            [
                'id' => '44444444-4444-4444-8444-444444444444',
                'name' => 'Historia'
            ],
            [
                'id' => '55555555-5555-4555-8555-555555555555',
                'name' => 'Infantil'
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('library.category')->whereIn('id', [
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            '33333333-3333-4333-8333-333333333333',
            '44444444-4444-4444-8444-444444444444',
            '55555555-5555-4555-8555-555555555555',
        ])->delete();
    }
};
