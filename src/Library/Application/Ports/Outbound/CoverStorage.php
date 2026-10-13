<?php

declare(strict_types=1);

namespace Library\Application\Ports\Outbound;

/**
 * CoverStorage - Puerto outbound
 * 
 * Almacenamiento de portadas (disco en local, S3 en producción).
 */
interface CoverStorage
{
    /**
     * Guarda una portada y retorna la clave opaca
     * 
     * @param string $content Contenido binario de la imagen
     * @param string $mimeType Tipo MIME (image/jpeg, image/png)
     * @return string Clave opaca para recuperar la portada
     */
    public function store(string $content, string $mimeType): string;

    /**
     * Recupera una portada por su clave
     * 
     * @return array|null ['content' => string, 'mimeType' => string] o null si no existe
     */
    public function retrieve(string $coverKey): ?array;

    /**
     * Elimina una portada
     */
    public function delete(string $coverKey): void;
}
