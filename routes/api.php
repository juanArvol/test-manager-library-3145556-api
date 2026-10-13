<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Manager Library
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Public routes
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/users', [AuthController::class, 'createLibrarian']);
});

// Protected routes
Route::prefix('v1')->middleware('auth.jwt')->group(function () {
    // Books
    Route::post('/books', [\App\Http\Controllers\BookController::class, 'create']);
    Route::get('/books', [\App\Http\Controllers\BookController::class, 'search']);
    Route::get('/books/{id}', [\App\Http\Controllers\BookController::class, 'get']);
    Route::put('/books/{id}', [\App\Http\Controllers\BookController::class, 'update']);
    Route::delete('/books/{id}', [\App\Http\Controllers\BookController::class, 'delete']);
    
    // Members
    Route::post('/members', [\App\Http\Controllers\MemberController::class, 'create']);
    Route::get('/members', [\App\Http\Controllers\MemberController::class, 'list']);
    
    // Loans
    Route::post('/loans', [\App\Http\Controllers\LoanController::class, 'lend']);
    Route::post('/loans/{loanId}/return', [\App\Http\Controllers\LoanController::class, 'returnItem']);
    Route::get('/loans/overdue', [\App\Http\Controllers\LoanController::class, 'getOverdue']);
    
    // Reports (admin only)
    Route::get('/reports/most-borrowed', [\App\Http\Controllers\ReportController::class, 'getMostBorrowed'])
        ->middleware('auth.jwt:admin');
    
    // Categories
    Route::get('/categories', [\App\Http\Controllers\CategoryController::class, 'list']);
});
