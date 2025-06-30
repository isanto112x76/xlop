<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManufacturerRequest;
use App\Http\Requests\UpdateManufacturerRequest;
use App\Http\Resources\ManufacturerResource;
use App\Models\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ManufacturerController extends Controller
{
    public function index(Request $request)
    {
        $query = Manufacturer::query();

        // Filter by manufacturer name (partial match)
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filter by status (e.g. active/inactive)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $manufacturers = $query->paginate($request->input('per_page', 15));
        return ManufacturerResource::collection($manufacturers);
    }


    public function store(StoreManufacturerRequest $request): ManufacturerResource
    {
        $manufacturer = Manufacturer::create($request->validated());
        return new ManufacturerResource($manufacturer);
    }

    public function show(Manufacturer $manufacturer): ManufacturerResource
    {
        return new ManufacturerResource($manufacturer);
    }

    public function update(UpdateManufacturerRequest $request, Manufacturer $manufacturer): ManufacturerResource
    {
        $manufacturer->update($request->validated());
        return new ManufacturerResource($manufacturer);
    }

    public function destroy(Manufacturer $manufacturer): JsonResponse
    {
        // TODO: Sprawdzić, czy producent nie jest przypisany do żadnych produktów
        if ($manufacturer->products()->exists()) {
            return response()->json(['message' => 'Nie można usunąć producenta przypisanego do produktów.'], 422);
        }
        $manufacturer->delete();
        return response()->json(null, 204);
    }


}
