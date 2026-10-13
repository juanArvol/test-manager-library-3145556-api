<?php

declare(strict_types=1);

namespace Library\Domain;

/**
 * Member - Agregado raíz
 * 
 * Representa un socio de la biblioteca.
 * Invariantes que defiende:
 * - documento obligatorio y único
 * - nombre completo no vacío
 */
final class Member
{
    private string $id;
    private string $fullName;
    private string $documentNumber;

    private function __construct(
        string $id,
        string $fullName,
        string $documentNumber
    ) {
        $this->validateFullName($fullName);
        $this->validateDocumentNumber($documentNumber);
        
        $this->id = $id;
        $this->fullName = trim($fullName);
        $this->documentNumber = trim($documentNumber);
    }

    /**
     * Crea un nuevo socio
     */
    public static function create(
        string $id,
        string $fullName,
        string $documentNumber
    ): self {
        return new self($id, $fullName, $documentNumber);
    }

    /**
     * Reconstituye un socio desde persistencia
     */
    public static function reconstitute(
        string $id,
        string $fullName,
        string $documentNumber
    ): self {
        return new self($id, $fullName, $documentNumber);
    }

    /**
     * Actualiza el nombre completo
     */
    public function updateFullName(string $fullName): void
    {
        $this->validateFullName($fullName);
        $this->fullName = trim($fullName);
    }

    // Getters

    public function id(): string
    {
        return $this->id;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function documentNumber(): string
    {
        return $this->documentNumber;
    }

    // Validaciones privadas

    private function validateFullName(string $fullName): void
    {
        if (trim($fullName) === '') {
            throw new \InvalidArgumentException('El nombre completo no puede estar vacío.');
        }
    }

    private function validateDocumentNumber(string $documentNumber): void
    {
        if (trim($documentNumber) === '') {
            throw new \InvalidArgumentException('El número de documento no puede estar vacío.');
        }
    }
}
