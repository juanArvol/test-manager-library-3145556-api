<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Manager Library
|--------------------------------------------------------------------------
|
| Contrato HTTP según api-contract.md del spec.
| Base: /api/v1
|
*/

// Health check endpoint - público
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Autenticación - público
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', function (Request $request) {
        // TODO: Implementar en T-07
        return response()->json(['message' => 'Not implemented yet'], 501);
    });
});

// Rutas protegidas - requieren autenticación
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    
    // Usuarios (admin only)
    Route::post('/users', function (Request $request) {
        // TODO: Implementar en T-07
        return response()->json(['message' => 'Not implemented yet'], 501);
    });
    
    // Categorías (autenticado)
    Route::get('/categories', function () {
        // TODO: Implementar en T-02
        return response()->json(['message' => 'Not implemented yet'], 501);
    });
    
    // Libros
    Route::get('/books', function () {
        // TODO: Implementar en T-08
        return response()->json(['message' => 'Not implemented yet'], 501);
    });
    
    // Socios
    Route::get('/members', function () {
        // TODO: Implementar en T-06
        return response()->json(['message' => 'Not implemented yet'], 501);
    });
    
    // Préstamos
    Route::get('/loans', function () {
        // TODO: Implementar en T-09
        return response()->json(['message' => 'Not implemented yet'], 501);
    });
    
    // Reportes (admin only)
    Route::get('/reports/most-borrowed', function () {
        // TODO: Implementar en T-11
        return response()->json(['message' => 'Not implemented yet'], 501);
    });
});
