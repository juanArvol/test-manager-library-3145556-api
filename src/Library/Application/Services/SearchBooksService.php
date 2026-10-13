<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\Outbound\BookRepository;

/**
 * SearchBooksService - Caso de uso de búsqueda de libros (HU-04)
 * 
 * Disponible para usuarios autenticados.
 */
final readonly class SearchBooksService
{
    public function __construct(
        private BookRepository $bookRepository
    ) {}

    /**
     * Busca libros por texto y/o categoría
     * 
     * CA-04.1: Búsqueda por texto parcial en título o autor, filtro por categoría,
     *          solo libros activos, orden por título, paginada
     * CA-04.2: La respuesta incluye el total de resultados
     * 
     * @return array ['items' => Book[], 'page' => int, 'size' => int, 'total' => int]
     */
    public function search(
        ?string $query,
        ?string $categoryId,
        int $page = 1,
        int $size = 20
    ): array {
        // Validar tamaño máximo (CA-04.1: máx. 50)
        if ($size > 50) {
            $size = 50;
        }
        
        if ($size < 1) {
            $size = 1;
        }
        
        if ($page < 1) {
            $page = 1;
        }
        
        $result = $this->bookRepository->search($query, $categoryId, $page, $size);
        
        // CA-04.2: incluir total de resultados
        return [
            'items' => $result['items'],
            'page' => $page,
            'size' => $size,
            'total' => $result['total']
        ];
    }
}
