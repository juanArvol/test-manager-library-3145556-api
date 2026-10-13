<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): JsonResponse
    {
        // Always return JSON with unified error format
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        
        if ($statusCode === 500) {
            $statusCode = $this->isHttpException($e) ? $e->getStatusCode() : 500;
        }

        return response()->json([
            'error' => $this->getErrorCode($e),
            'message' => $e->getMessage(),
        ], $statusCode);
    }

    private function getErrorCode(Throwable $e): string
    {
        $class = class_basename($e);
        
        // Map exceptions to error codes
        return match ($class) {
            'InvalidCredentialsException' => 'INVALID_CREDENTIALS',
            'UsernameAlreadyExistsException' => 'USERNAME_ALREADY_EXISTS',
            'IsbnAlreadyExistsException' => 'ISBN_ALREADY_EXISTS',
            'BookNotFoundException' => 'BOOK_NOT_FOUND',
            'MemberNotFoundException' => 'MEMBER_NOT_FOUND',
            'LoanNotFoundException' => 'LOAN_NOT_FOUND',
            'NoAvailableCopiesException' => 'NO_AVAILABLE_COPIES',
            'MemberHasOverdueLoansException' => 'MEMBER_HAS_OVERDUE_LOANS',
            'MemberLoanLimitExceededException' => 'MEMBER_LOAN_LIMIT_EXCEEDED',
            'LoanItemAlreadyReturnedException' => 'ITEM_ALREADY_RETURNED',
            'CoverTooLargeException' => 'COVER_TOO_LARGE',
            'ConcurrencyException' => 'CONFLICT',
            'ValidationException' => 'VALIDATION_ERROR',
            'AuthenticationException' => 'UNAUTHORIZED',
            'AuthorizationException' => 'FORBIDDEN',
            default => 'INTERNAL_ERROR',
        };
    }
}
