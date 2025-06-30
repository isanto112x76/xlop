<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxRateRequest;
use App\Http\Requests\UpdateTaxRateRequest;
use App\Http\Resources\TaxRateResource;
use App\Models\TaxRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TaxRateController extends Controller
{
    public function index(Request $request)
    {
        $query = TaxRate::query();

        // Filter by tax rate name (partial match)
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filter by exact tax percentage/value
        if ($request->filled('rate')) {
            $query->where('rate', $request->rate);
        }

        // Filter by status (if tax rates can be active/inactive)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $taxRates = $query->paginate($request->input('per_page', 15));
        return TaxRateResource::collection($taxRates);
    }


    public function store(StoreTaxRateRequest $request): TaxRateResource
    {
        $validatedData = $request->validated();
        $taxRate = null;

        DB::transaction(function () use ($validatedData, &$taxRate) {
            if (isset($validatedData['is_default']) && $validatedData['is_default']) {
                TaxRate::where('is_default', true)->update(['is_default' => false]);
            }
            $taxRate = TaxRate::create($validatedData);
        });

        return new TaxRateResource($taxRate);
    }

    public function show(TaxRate $taxRate): TaxRateResource
    {
        return new TaxRateResource($taxRate);
    }

    public function update(UpdateTaxRateRequest $request, TaxRate $taxRate): TaxRateResource
    {
        $validatedData = $request->validated();

        DB::transaction(function () use ($validatedData, $taxRate) {
            if (isset($validatedData['is_default']) && $validatedData['is_default']) {
                TaxRate::where('id', '!=', $taxRate->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
            $taxRate->update($validatedData);
        });

        return new TaxRateResource($taxRate->fresh());
    }

    public function destroy(TaxRate $taxRate): JsonResponse
    {
        if ($taxRate->is_default) {
            return response()->json(['message' => 'Nie można usunąć domyślnej stawki VAT.'], 422);
        }
        if ($taxRate->products()->exists()) {
            return response()->json(['message' => 'Nie można usunąć stawki VAT przypisanej do produktów.'], 422);
        }
        $taxRate->delete();
        return response()->json(null, 204);
    }


}
