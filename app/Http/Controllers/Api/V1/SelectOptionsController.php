<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product; // Dodano dla opcji produktów
use App\Models\ProductVariant; // Poprawiono ścieżkę, jeśli była niepoprawna
use App\Enums\DocumentType; // Upewnij się, że ta ścieżka jest poprawna
use App\Enums\ProductStatus; // Dodano dla opcji statusów produktu
use App\Enums\ProductType;   // Dodano dla opcji typów produktu
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder; // Dodano dla kwalifikacji kolumn w zapytaniach
use App\Http\Resources\ProductVariantResource; // Dodano, jeśli potrzebujesz zasobu dla wariantów

class SelectOptionsController extends Controller
{
    // Metoda index() była generyczna i nieużywana w Twoich trasach api.php.
    // Jeśli jej nie potrzebujesz, można ją usunąć.
    // Zakładam, że poszczególne metody jak categories(), suppliers() są wywoływane bezpośrednio.

    public function categories(Request $request): JsonResponse
    {
        $query = Category::orderBy('name')->select(['id', 'name AS label', 'parent_id', 'slug']); // Dodano parent_id i slug

        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }
        if ($request->has('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', $request->input('ids'));
            $query->whereIn('id', $ids);
        }

        $categories = $query->get();

        // Możemy dodać logikę budowania drzewa, jeśli frontend tego oczekuje
        // lub zwrócić płaską listę, a frontend sam zbuduje drzewo.
        // Na razie zwracamy płaską listę z parent_id.

        return response()->json($categories);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $query = Supplier::orderBy('name')->select(['id', 'name AS label', 'tax_id', 'email']); // Dodano tax_id, email
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%')
                ->orWhere('tax_id', 'like', '%' . $request->input('search') . '%')
                ->orWhere('email', 'like', '%' . $request->input('search') . '%');
        }
        if ($request->has('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', $request->input('ids'));
            $query->whereIn('id', $ids);
        }
        return response()->json($query->get());
    }

    public function warehouses(Request $request): JsonResponse
    {
        $query = Warehouse::orderBy('name')->select(['id', 'name AS label', 'symbol', 'is_default']); // Dodano is_default
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%')
                ->orWhere('symbol', 'like', '%' . $request->input('search') . '%');
        }
        if ($request->has('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', $request->input('ids'));
            $query->whereIn('id', $ids);
        }
        return response()->json($query->get());
    }
    public function getDocumentMappings()
    {
        $docTypeLabels = collect(DocumentType::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()]);

        $typeColors = [
            'PZ' => 'info',
            'FVZ' => 'primary',
            'WZ' => 'success',
            'FS' => 'success',
            'MM' => 'warning',
            'PW' => 'secondary',
            'RW' => 'error',
            'ZW' => 'purple',
            'ZRW' => 'indigo',
            'INW' => 'cyan',
        ];

        $statusIcons = [
            'open' => ['icon' => 'tabler-circle', 'color' => 'success', 'tooltip' => 'Otwarty'],
            'closed' => ['icon' => 'tabler-lock', 'color' => 'info', 'tooltip' => 'Zamknięty'],
            'deleted' => ['icon' => 'tabler-trash', 'color' => 'error', 'tooltip' => 'Usunięty'],
        ];

        return response()->json([
            'docTypeLabels' => $docTypeLabels,
            'typeColors' => $typeColors,
            'statusIcons' => $statusIcons,
        ]);
    }
    public function manufacturers(Request $request): JsonResponse
    {
        // Zakładam, że Manufacturer nie ma kolumny 'slug' na podstawie poprzedniej rozmowy
        $query = Manufacturer::orderBy('name')->select(['id', 'name AS label']);
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }
        if ($request->has('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', $request->input('ids'));
            $query->whereIn('id', $ids);
        }
        return response()->json($query->get());
    }

    public function taxRates(Request $request): JsonResponse
    {
        $query = TaxRate::orderBy('rate')->select(['id', 'name AS label', 'rate', 'is_default']); // Dodano is_default
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%')
                ->orWhere('rate', 'like', '%' . $request->input('search') . '%');
        }
        if ($request->has('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', $request->input('ids'));
            $query->whereIn('id', $ids);
        }
        return response()->json($query->get());
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::orderBy('name')->where('is_active', true)->select(['id', 'name AS label', 'email']); // Dodano email
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%')
                ->orWhere('email', 'like', '%' . $request->input('search') . '%');
        }
        if ($request->has('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', $request->input('ids'));
            $query->whereIn('id', $ids);
        }
        return response()->json($query->get());
    }

    public function documentTypes(): JsonResponse
    {
        $types = [];
        foreach (DocumentType::cases() as $case) {
            // Użyj metody label(), jeśli ją zdefiniowałeś w Enum,
            // w przeciwnym razie $case->name lub dostosuj.
            $types[] = ['value' => $case->value, 'label' => method_exists($case, 'label') ? $case->label() : $case->name];
        }
        return response()->json($types);
    }

    public function productVariants(Request $request): JsonResponse
    {
        $query = ProductVariant::with('product:id,name')
            ->select(['id', 'product_id', 'name AS variant_name', 'sku', 'ean', 'barcode']); // Dodano ean, barcode

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('product_variants.sku', 'like', "%{$search}%") // Kwalifikuj kolumny
                    ->orWhere('product_variants.name', 'like', "%{$search}%")
                    ->orWhere('product_variants.ean', 'like', "%{$search}%")
                    ->orWhereHas('product', function (Builder $prodQ) use ($search) {
                        $prodQ->where('products.name', 'like', "%{$search}%"); // Kwalifikuj kolumny
                    });
            });
        }

        // Filtr po ID produktu, jeśli przekazany
        if ($request->filled('product_id')) {
            $query->where('product_variants.product_id', $request->input('product_id'));
        }

        // Filtr po ID wariantów, jeśli przekazane
        if ($request->has('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', $request->input('ids'));
            $query->whereIn('product_variants.id', $ids);
        }


        $limit = $request->input('limit', 50);
        $variants = $query->limit($limit)->get()->map(function ($variant) {
            $label = $variant->product ? $variant->product->name : 'Brak produktu';
            $label .= ($variant->variant_name ? ' - ' . $variant->variant_name : '');
            $label .= ' (SKU: ' . $variant->sku . ')';
            return [
                'id' => $variant->id, // Zwracamy ID wariantu
                'value' => $variant->id, // Dla niektórych komponentów select
                'label' => $label,
                'sku' => $variant->sku,
                'product_name' => $variant->product?->name, // Dodatkowe info
                'variant_name_only' => $variant->variant_name, // Dodatkowe info
            ];
        });
        return response()->json($variants);
    }

    /**
     * Zwraca listę produktów (bez wariantów) dla opcji select.
     */
    public function products(Request $request): JsonResponse
    {
        // Krok 1: Dodajemy with('media'), aby załadować relacje do zdjęć
        $query = Product::with('media')
            ->orderBy('name')
            ->select(['id', 'name AS label', 'sku']); // Select pozostaje bez zmian

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%");
        }

        if ($request->has('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', $request->input('ids'));
            $query->whereIn('id', $ids);
        }

        $products = $query->limit(50)->get();

        // Krok 2: Mapujemy wyniki, aby dodać URL miniaturki do każdego produktu
        $formattedProducts = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'title' => $product->label, // Używamy aliasu 'label' dla VAutocomplete
                'sku' => $product->sku,
                'thumbnail' => $product->getFirstMediaUrl('product_images', 'thumb'),
            ];
        });

        return response()->json($formattedProducts);
    }

    /**
     * Zwraca opcje dla statusów produktu.
     */
    public function productStatuses(): JsonResponse
    {
        // Zakładając, że masz Enum App\Enums\ProductStatus
        // lub tablicę zdefiniowanych statusów
        if (class_exists(ProductStatus::class) && method_exists(ProductStatus::class, 'cases')) {
            $statuses = [];
            foreach (ProductStatus::cases() as $case) {
                $statuses[] = ['value' => $case->value, 'label' => method_exists($case, 'label') ? $case->label() : $case->name];
            }
            return response()->json($statuses);
        }
        // Fallback, jeśli Enum nie istnieje
        return response()->json([
            ['value' => 'active', 'label' => 'Aktywny'],
            ['value' => 'inactive', 'label' => 'Nieaktywny'],
            ['value' => 'draft', 'label' => 'Szkic'],
            ['value' => 'archived', 'label' => 'Zarchiwizowany'],
        ]);
    }

    /**
     * Zwraca opcje dla typów produktu.
     */
    public function productTypes(): JsonResponse
    {
        // Zakładając, że masz Enum App\Enums\ProductType
        if (class_exists(ProductType::class) && method_exists(ProductType::class, 'cases')) {
            $types = [];
            foreach (ProductType::cases() as $case) {
                $types[] = ['value' => $case->value, 'label' => method_exists($case, 'label') ? $case->label() : $case->name];
            }
            return response()->json($types);
        }
        // Fallback
        return response()->json([
            ['value' => 'standard', 'label' => 'Standardowy'],
            ['value' => 'bundle', 'label' => 'Zestaw (Bundle)'],
            // Możesz dodać 'variable' jeśli rozróżniasz produkty z wariantami od prostych
        ]);
    }

    /**
     * Zwraca opcje dla tagów.
     */
    public function tags(Request $request): JsonResponse
    {
        // Zakładając, że model Tag nie ma 'slug'
        $query = \App\Models\Tag::orderBy('name')->select(['id', 'name AS label']);
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }
        if ($request->has('ids')) {
            $ids = is_array($request->input('ids')) ? $request->input('ids') : explode(',', $request->input('ids'));
            $query->whereIn('id', $ids);
        }
        return response()->json($query->get());
    }
}
