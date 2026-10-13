<?php

namespace Tests\Unit\Application\Fakes;

use Library\Application\Ports\Outbound\CategoryRepository;

/**
 * FakeCategoryRepository - Implementación simple para tests
 */
final class FakeCategoryRepository implements CategoryRepository
{
    /** @var array<string, string> */
    private array $categories = [];

    public function __construct()
    {
        // Sembrar las 5 categorías por defecto
        $this->categories = [
            '11111111-1111-4111-8111-111111111111' => 'Novela',
            '22222222-2222-4222-8222-222222222222' => 'Ciencia',
            '33333333-3333-4333-8333-333333333333' => 'Tecnología',
            '44444444-4444-4444-8444-444444444444' => 'Historia',
            '55555555-5555-4555-8555-555555555555' => 'Infantil',
        ];
    }

    public function getNameById(string $categoryId): ?string
    {
        return $this->categories[$categoryId] ?? null;
    }

    // Métodos de ayuda para tests
    
    public function addCategory(string $id, string $name): void
    {
        $this->categories[$id] = $name;
    }
}
