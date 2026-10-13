<?php

declare(strict_types=1);

namespace Library\Adapters\Storage;

use Illuminate\Support\Facades\Storage;
use Library\Application\Exceptions\CoverTooLargeException;
use Library\Application\Ports\Outbound\CoverStorage;

final class DiskCoverStorage implements CoverStorage
{
    private const MAX_SIZE_BYTES = 2 * 1024 * 1024; // 2 MB
    private const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/jpg'];

    public function store(string $fileContent, string $mimeType): string
    {
        // Validate size
        if (strlen($fileContent) > self::MAX_SIZE_BYTES) {
            throw new CoverTooLargeException('La portada excede el tamaño máximo de 2 MB');
        }

        // Validate mime type
        if (!in_array($mimeType, self::ALLOWED_MIMES)) {
            throw new \InvalidArgumentException('Tipo de archivo no permitido. Solo PNG y JPEG');
        }

        // Generate unique key
        $extension = match($mimeType) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
        };

        $key = 'covers/' . uniqid() . '.' . $extension;

        // Store file
        Storage::disk('public')->put($key, $fileContent);

        return $key;
    }

    public function delete(string $key): void
    {
        Storage::disk('public')->delete($key);
    }

    public function exists(string $key): bool
    {
        return Storage::disk('public')->exists($key);
    }

    public function getUrl(string $key): string
    {
        return Storage::disk('public')->url($key);
    }
}
