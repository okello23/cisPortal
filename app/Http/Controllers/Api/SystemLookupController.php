<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportSystem;
use Illuminate\Http\JsonResponse;

class SystemLookupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            SupportSystem::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name', 'code'])
        );
    }

    public function modules(SupportSystem $system): JsonResponse
    {
        return response()->json(
            $system->modules()->where('active', true)->orderBy('sort_order')->get(['id', 'system_id', 'name', 'code'])
        );
    }
}
