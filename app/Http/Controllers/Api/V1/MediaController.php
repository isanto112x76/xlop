<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product; // Potrzebne do znalezienia modelu na podstawie typu i ID
use App\Models\ProductVariant; // Potrzebne do znalezienia modelu na podstawie typu i ID
use Spatie\MediaLibrary\MediaCollections\Models\Media; // Model Media z pakietu Spatie
use App\Http\Resources\MediaResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator; // Do walidacji
use Illuminate\Validation\Rule; // Do reguł walidacji
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB; // Do transakcji bazy danych
use Illuminate\Support\Facades\Gate; // Do sprawdzania uprawnień, jeśli potrzebne
use App\Http\Resources\MediaCollection; // Jeśli chcesz zwrócić kolekcję mediów

class MediaController extends Controller
{
    /**
     * Przechowuje nowo przesłane medium i przypisuje je do modelu.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'media' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:2048', // Max 2MB
            'model_type' => ['required', 'string', Rule::in(['Product', 'ProductVariant'])], // Akceptowalne typy modeli
            'model_id' => 'required|integer|min:1',
            'collection_name' => 'required|string|max:255', // Np. 'product_images' lub 'variant_images'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $modelTypeInput = $request->input('model_type');
        $modelId = $request->input('model_id');
        $collectionName = $request->input('collection_name');

        $model = null;
        if ($modelTypeInput === 'Product') {
            $model = Product::find($modelId);
        } elseif ($modelTypeInput === 'ProductVariant') {
            $model = ProductVariant::find($modelId);
        }

        if (!$model) {
            return response()->json(['message' => 'Nie znaleziono powiązanego modelu.'], Response::HTTP_NOT_FOUND);
        }

        if (!method_exists($model, 'addMediaFromRequest')) {
            return response()->json(['message' => 'Model nie obsługuje mediów (brak traita InteractsWithMedia).'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $media = $model->addMediaFromRequest('media') // 'media' to nazwa pola w formularzu
                ->toMediaCollection($collectionName);
            return response()->json([
                'message' => 'Medium zostało pomyślnie przesłane.',
                'data' => new MediaResource($media)
            ], Response::HTTP_CREATED);
        } catch (\Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist $e) {
            return response()->json(['message' => 'Plik nie istnieje.'], Response::HTTP_BAD_REQUEST);
        } catch (\Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig $e) {
            return response()->json(['message' => 'Plik jest za duży.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        } catch (\Exception $e) {
            \Log::error('Błąd uploadu media: ' . $e->getMessage());
            return response()->json(['message' => 'Wystąpił błąd podczas przesyłania medium.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Usuwa określone medium.
     */
    public function destroy(Media $media): Response // Route model binding dla Spatie Media
    {
        // Opcjonalnie: Sprawdź uprawnienia, czy użytkownik może usunąć to medium
        // if (Gate::denies('delete-media', $media)) {
        //     return response()->json(['message' => 'Brak uprawnień.'], Response::HTTP_FORBIDDEN);
        // }

        try {
            $media->delete();
            return response()->noContent();
        } catch (\Exception $e) {
            \Log::error('Błąd usuwania media: ' . $e->getMessage());
            return response()->json(['message' => 'Wystąpił błąd podczas usuwania medium.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Zmienia kolejność mediów dla danego modelu i kolekcji.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'media_ids' => 'required|array',
            'media_ids.*' => 'integer|exists:media,id',
            'collection_name' => 'sometimes|string|max:255', // Opcjonalne, jeśli masz jedną domyślną
            'model_type' => ['required', 'string', Rule::in(['App\Models\Product', 'App\Models\ProductVariant'])], // Pełna ścieżka do modelu
            'model_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $modelType = $request->input('model_type');
        $modelId = $request->input('model_id');
        $collectionName = $request->input('collection_name', 'default'); // Domyślna kolekcja, jeśli nie podano
        $orderedMediaIds = $request->input('media_ids');

        $model = app($modelType)->find($modelId);

        if (!$model) {
            return response()->json(['message' => 'Nie znaleziono powiązanego modelu.'], Response::HTTP_NOT_FOUND);
        }
        if (!method_exists($model, 'updateMediaOrder')) { // metoda z Spatie\MediaLibrary\InteractsWithMedia
            // Możesz też użyć manualnej zmiany `order_column` jak w ProductService
            // Media::setNewOrder($orderedMediaIds); // To jest globalne, potrzebujemy dla kolekcji modelu
            // Ręczna zmiana kolejności:
            DB::transaction(function () use ($orderedMediaIds) {
                foreach ($orderedMediaIds as $index => $mediaId) {
                    Media::where('id', $mediaId)->update(['order_column' => $index + 1]);
                }
            });
            return response()->json(['message' => 'Kolejność mediów zaktualizowana (ręcznie).']);
        }

        // Metoda updateMediaOrder może nie istnieć bezpośrednio w HasMedia,
        // ale Spatie używa 'order_column'. Ustawmy go ręcznie.
        try {
            DB::transaction(function () use ($orderedMediaIds) {
                foreach ($orderedMediaIds as $order => $mediaId) {
                    Media::where('id', $mediaId)->update(['order_column' => $order + 1]);
                }
            });
            return response()->json(['message' => 'Kolejność mediów zaktualizowana.']);
        } catch (\Exception $e) {
            \Log::error('Błąd zmiany kolejności mediów: ' . $e->getMessage());
            return response()->json(['message' => 'Wystąpił błąd podczas zmiany kolejności mediów.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function updateCustomProperties(Request $request, Media $media): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'custom_properties' => 'required|array',
            // Możesz dodać walidację dla konkretnych kluczy w custom_properties
            // 'custom_properties.alt' => 'nullable|string|max:255',
            // 'custom_properties.is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Łączenie z istniejącymi custom properties, a nie nadpisywanie wszystkich
            $newCustomProperties = array_merge($media->custom_properties, $request->input('custom_properties'));
            $media->custom_properties = $newCustomProperties;
            $media->save();

            return response()->json([
                'message' => 'Właściwości niestandardowe medium zaktualizowane.',
                'data' => new MediaResource($media)
            ]);
        } catch (\Exception $e) {
            \Log::error('Błąd aktualizacji custom_properties media: ' . $e->getMessage());
            return response()->json(['message' => 'Wystąpił błąd podczas aktualizacji właściwości medium.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
