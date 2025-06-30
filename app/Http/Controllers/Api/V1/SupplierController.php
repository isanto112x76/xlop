<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Supplier::query();

        // Wyszukiwanie
        $query->when($request->filled('q'), function ($q) use ($request) {
            $search = $request->input('q');
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('tax_id', 'like', "%{$search}%");
        });

        // Sortowanie
        $query->when($request->filled('sort_by'), function ($q) use ($request) {
            $sortBy = $request->input('sort_by');
            $orderBy = $request->input('order_by', 'asc');
            if (in_array($sortBy, ['name', 'tax_id'])) {
                $q->orderBy($sortBy, $orderBy);
            }
        });

        // Paginacja
        $perPage = $request->input('per_page', 10);

        return SupplierResource::collection($query->paginate($perPage));
    }

    public function store(Request $request): SupplierResource
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'tax_id' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $supplier = Supplier::create($validatedData);

        return new SupplierResource($supplier);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $supplier->update($request->validated());
        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();
        return response()->json(null, 204);
    }
}
