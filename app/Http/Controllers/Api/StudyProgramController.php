<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudyProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudyProgramController extends Controller
{
    use ApiResponse;

    /** Daftar semua program studi untuk dropdown berpencarian (tanpa fakultas). */
    public function index(Request $request): JsonResponse
    {
        $programs = StudyProgram::query()
            ->when($request->query('q'), fn ($query, $q) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return $this->ok($programs);
    }
}
