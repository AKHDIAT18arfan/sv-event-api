<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/** Format respons sukses yang seragam: { "success": true, "data": ... } */
trait ApiResponse
{
    protected function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
