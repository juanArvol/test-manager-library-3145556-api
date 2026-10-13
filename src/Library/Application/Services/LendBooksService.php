<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Exceptions\BookNotFoundException;
use Library\Application\Exceptions\MemberHasOverdueItemsException;
use Library\Application\Exceptions\MemberLimitExceededException;
use Library\Application\Exceptions\MemberNotFoundException;
use Library\Application\Ports\Outbound\BookRepository;
use Library\Application\Ports\Outbound\CategoryRepository;
use Library\Application\Ports\Outbound\Clock;
use Library\Application\Ports\Outbound\LoanRepository;
use Library\Application\Ports\Outbound\MemberRepository;
use Library\Domain\Exceptions\BookNotAvailableException;
use Library\Domain\Loan;
use Ramsey\Uuid\Uuid;

/**
 * LendBooksService - Caso de uso de registro de préstamos (HU-06)
 * 
 * Admin y librarian pueden registrar préstamos.
 */
final readonly class LendBooksService
{
    private const MAX_UNRETURNED_ITEMS = 3;

    public function __construct(
        private MemberRepository $memberRepository,
        private BookRepository $bookRepository,
        private LoanRepository $loanRepository,
        private CategoryRepository $categoryRepository,
        private Clock $clock
    ) {}

    /**
     * Registra un préstamo de 1 a 3 libros para un socio
     * 
     * CA-06.1: Un préstamo es de un socio, lo registra el usuario autenticado y trae de 1 a 3 líneas
     * CA-06.2: No se repite el mismo libro en un préstamo (422)
     * CA-06.3: Cada línea descuenta 1 ejemplar disponible. Sin ejemplares: 409 y no se registra nada
     * CA-06.4: Un socio no puede tener más de 3 libros sin devolver en total (422)
     * CA-06.5: Un socio con algún libro vencido no puede pedir otro (409)
     * CA-06.6: La fecha de vencimiento es loaned_at + 14 días. Congela título, ISBN y categoría
     * 
     * @param string $memberId
     * @param array $bookIds Array de book IDs (1 a 3)
     * @param string $loanedByUserId
     * @return Loan
     * @throws MemberNotFoundException
     * @throws BookNotFoundException
     * @throws MemberHasOverdueItemsException
     * @throws MemberLimitExceededException
     * @throws BookNotAvailableException
     */
    public function lendBooks(string $memberId, array $bookIds, string $loanedByUserId): Loan
    {
        // Validar que el socio existe
        $member = $this->memberRepository->findById($memberId);
        if ($member === null) {
            throw MemberNotFoundException::forId($memberId);
        }

        // CA-06.5: verificar que no tenga vencidos
        if ($this->loanRepository->memberHasOverdueItems($memberId)) {
            throw MemberHasOverdueItemsException::forMember($member->fullName());
        }

        // CA-06.4: verificar límite de 3 libros sin devolver
        $currentUnreturned = $this->loanRepository->countUnreturnedItemsForMember($memberId);
        $requestedCount = count($bookIds);
        
        if ($currentUnreturned + $requestedCount > self::MAX_UNRETURNED_ITEMS) {
            throw MemberLimitExceededException::forMember(
                $member->fullName(),
                $currentUnreturned,
                $requestedCount
            );
        }

        // Obtener instante actual
        $now = $this->clock->now();

        // Crear préstamo vacío
        $loan = Loan::create(
            id: Uuid::uuid4()->toString(),
            memberId: $memberId,
            loanedByUserId: $loanedByUserId,
            loanedAt: $now
        );

        // CA-06.3 y CA-06.6: añadir cada libro al préstamo
        // Si alguno falla (sin ejemplares o duplicado), se lanza excepción y no se guarda nada
        foreach ($bookIds as $bookId) {
            $book = $this->bookRepository->findById($bookId);
            
            if ($book === null) {
                throw BookNotFoundException::forId($bookId);
            }

            // Obtener nombre de categoría para congelar (CA-06.6)
            $categoryName = $this->categoryRepository->getNameById($book->categoryId());
            
            if ($categoryName === null) {
                $categoryName = 'Desconocida';
            }

            // CA-06.3: retirar ejemplar (puede lanzar BookNotAvailableException)
            $book->withdraw();

            // CA-06.6 y CA-06.2: añadir línea al préstamo (congela valores, valida duplicados)
            $loan->addItem(
                itemId: Uuid::uuid4()->toString(),
                book: $book,
                categoryName: $categoryName
            );

            // Guardar libro con disponibles actualizados
            $this->bookRepository->save($book);
        }

        // Guardar préstamo
        $this->loanRepository->save($loan);

        return $loan;
    }
}
