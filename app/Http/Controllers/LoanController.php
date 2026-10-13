<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoanController extends Controller
{
    public function lend(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function returnItem(Request $request, string $loanId): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function getOverdue(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
