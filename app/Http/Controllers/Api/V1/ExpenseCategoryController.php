<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ExpenseCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $category = ExpenseCategory::create($data);

        return response()->json(['data' => $category->only('id', 'name')], 201);
    }
}
