<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        $request->validate(['name' => ['required', 'string', 'max:100']]);

        $count    = ExpenseCategory::count();
        $color    = ExpenseCategory::nextColor($count);
        $category = ExpenseCategory::firstOrCreate(
            ['name' => $request->name],
            ['color' => $color]
        );

        $status = $category->wasRecentlyCreated ? 201 : 200;

        return response()->json($category, $status);
    }
}
