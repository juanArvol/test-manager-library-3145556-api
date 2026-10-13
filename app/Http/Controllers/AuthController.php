<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Application\Services\LoginService;
use Library\Application\Services\CreateLibrarianService;
use Library\Application\Exceptions\InvalidCredentialsException;
use Library\Application\Exceptions\UsernameAlreadyExistsException;

final class AuthController extends Controller
{
    public function __construct(
        private LoginService $loginService,
        private CreateLibrarianService $createLibrarianService
    ) {}

    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->loginService->execute(
                $request->input('username'),
                $request->input('password')
            );

            return response()->json([
                'token' => $result['token'],
                'user_id' => $result['user_id'],
                'role' => $result['role'],
            ], 200);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'error' => 'INVALID_CREDENTIALS',
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function createLibrarian(Request $request): JsonResponse
    {
        try {
            $userId = $this->createLibrarianService->execute(
                $request->input('username'),
                $request->input('password')
            );

            return response()->json([
                'user_id' => $userId,
            ], 201);
        } catch (UsernameAlreadyExistsException $e) {
            return response()->json([
                'error' => 'USERNAME_ALREADY_EXISTS',
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
