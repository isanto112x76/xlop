<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB; // Dla transakcji przy is_default

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $query = Warehouse::query();

        // Filter by warehouse name (partial match)
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filter by warehouse code or identifier (partial match), if applicable
        if ($request->filled('code')) {
            $query->where('code', 'like', '%' . $request->code . '%');
        }

        // Filter by status (e.g. active/inactive warehouse)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $warehouses = $query->paginate($request->input('per_page', 15));
        return WarehouseResource::collection($warehouses);
    }


    public function store(StoreWarehouseRequest $request): WarehouseResource
    {
        $validatedData = $request->validated();

        DB::transaction(function () use ($validatedData, &$warehouse) {
            if (isset($validatedData['is_default']) && $validatedData['is_default']) {
                Warehouse::where('is_default', true)->update(['is_default' => false]);
            }
            $warehouse = Warehouse::create($validatedData);
        });

        return new WarehouseResource($warehouse);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($warehouse);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $validatedData = $request->validated();

        DB::transaction(function () use ($validatedData, $warehouse) {
            if (isset($validatedData['is_default']) && $validatedData['is_default']) {
                // Zdejmij flagę is_default z innych magazynów, jeśli ten staje się domyślny
                Warehouse::where('id', '!=', $warehouse->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
            $warehouse->update($validatedData);
        });

        return new WarehouseResource($warehouse->fresh());
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        // TODO: Dodać logikę sprawdzającą, czy magazyn nie jest używany
        // i czy nie jest domyślny, zanim zostanie usunięty.
        if ($warehouse->is_default) {
            return response()->json(['message' => 'Nie można usunąć domyślnego magazynu.'], 422);
        }
        $warehouse->delete();
        return response()->json(null, 204);
    }
}
