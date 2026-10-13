<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class CategoryController extends Controller
{
    public function list(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
