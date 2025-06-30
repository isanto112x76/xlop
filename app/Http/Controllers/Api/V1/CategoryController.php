<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $query = Category::orderBy('name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%");
        }

        if ($request->has('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', $request->input('ids'));
            $query->whereIn('id', $ids);
        }

        $perPage = (int) $request->input('per_page', 15);

        // ✅ POPRAWKA: Ręczna obsługa przypadku "pobierz wszystko"
        if ($perPage === -1) {
            $items = CategoryResource::collection($query->get());

            // Ręcznie tworzymy strukturę podobną do paginacji, której oczekuje frontend
            return response()->json([
                'data' => $items,
                'meta' => [
                    'total' => $items->count(),
                ],
            ]);
        }

        // Standardowa paginacja dla pozostałych przypadków
        $paginated = $query->paginate($perPage);

        return CategoryResource::collection($paginated);
    }


    public function store(StoreCategoryRequest $request): CategoryResource
    {
        $category = Category::create($request->validated());
        return new CategoryResource($category->load('parent'));
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load(['parent', 'children']));
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());
        return new CategoryResource($category->fresh()->load(['parent', 'children']));
    }

    public function destroy(Category $category): JsonResponse
    {
        // TODO: Dodać logikę sprawdzającą, czy kategoria nie jest używana (produkty)
        // lub czy nie ma podkategorii, które trzeba by przenieść/usunąć.
        if ($category->children()->exists() || $category->products()->exists()) {
            return response()->json(['message' => 'Nie można usunąć kategorii, która ma podkategorie lub przypisane produkty.'], 422);
        }
        $category->delete();
        return response()->json(null, 204);
    }
    public function getTree(): JsonResponse
    {
        $categories = Category::whereNull('parent_id')->with('childrenRecursive')->get();

        return response()->json(CategoryResource::collection($categories));
    }
}
