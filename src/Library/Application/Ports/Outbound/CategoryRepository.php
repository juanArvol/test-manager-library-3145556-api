<?php

declare(strict_types=1);

namespace Library\Application\Ports\Outbound;

/**
 * CategoryRepository - Puerto outbound
 * 
 * Repositorio de categorías (solo lectura).
 */
interface CategoryRepository
{
    /**
     * Obtiene el nombre de una categoría por su id
     * Retorna null si no existe
     */
    public function getNameById(string $categoryId): ?string;
}
