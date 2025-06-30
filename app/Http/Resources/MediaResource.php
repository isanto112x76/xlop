<?php
/**
 * Zasób dla modelu Media (Spatie MediaLibrary).
 * Plik: app/Http/Resources/MediaResource.php
 */
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;


class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Zakładamy, że model $this->resource to instancja Spatie\MediaLibrary\MediaCollections\Models\Media
        return [
            'id' => $this->id,
            'name' => $this->name, // Nazwa nadana w systemie
            'file_name' => $this->file_name, // Oryginalna nazwa pliku
            'mime_type' => $this->mime_type,
            'size' => $this->size, // Rozmiar w bajtach
            'original_url' => $this->getFullUrl(), // Pełny URL do oryginalnego pliku
            'thumb_url' => $this->hasGeneratedConversion('thumb') ? $this->getFullUrl('thumb') : $this->getFullUrl(),
            // Możesz dodać URL do konwersji, jeśli ich używasz, np. podglądu:
            // 'preview_url' => $this->when($this->hasGeneratedConversion('preview'), $this->getFullUrl('preview'), null),
            'custom_properties' => $this->custom_properties, // Własne właściwości, jeśli zdefiniowano
            'order_column' => $this->order_column, // Jeśli używasz sortowania
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
