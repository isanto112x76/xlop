<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Expense::query()->with('supplier');

        // Filter by expense description or name (partial match)
        if ($request->filled('description')) {
            $query->where('description', 'like', '%' . $request->description . '%');
        }
        if ($request->filled('name')) {
            // If there's a 'name' field for expense (alias for description), use it similarly
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filter by related supplier ID or name (if expense is linked to a supplier)
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('supplier_name')) {
            $query->whereHas('supplier', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->supplier_name . '%');
            });
        }

        // Filter by expense type or category (if such a field exists)
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status (e.g. paid/unpaid, if applicable)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $expenses = $query->paginate($request->input('per_page', 15));
        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request): ExpenseResource
    {
        $expense = Expense::create($request->validated());
        return new ExpenseResource($expense->load(['category', 'supplier']));
    }

    public function show(Expense $expense): ExpenseResource
    {
        return new ExpenseResource($expense->load(['category', 'supplier']));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $expense->update($request->validated());
        return new ExpenseResource($expense->load(['category', 'supplier']));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();
        return response()->json(null, 204);
    }


}
