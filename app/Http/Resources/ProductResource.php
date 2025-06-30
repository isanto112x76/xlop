<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'ean' => $this->ean,
            'pos_code' => $this->pos_code,
            'foreign_id' => $this->foreign_id,
            'description' => $this->description,
            'attributes' => $this->attributes,
            'status' => $this->status,
            'product_type' => $this->product_type,
            'is_bundle' => $this->is_bundle,
            'manage_stock' => (bool) $this->manage_stock,
            'variants_share_stock' => (bool) $this->variants_share_stock,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'manufacturer_id' => $this->manufacturer_id,
            'manufacturer' => new ManufacturerResource($this->whenLoaded('manufacturer')),
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'main_image_url' => $this->getFirstMediaUrl('product_images'),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'marketplace_attributes' => $this->marketplace_attributes,

            // Tagging & Links
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'tag_ids' => $this->tags ? $this->tags->pluck('id') : [],
            'product_links' => ProductLinkResource::collection($this->whenLoaded('links')),

            // Warianty produktu
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),

            // Domyślny wariant (jeśli potrzebujesz tego w oddzielu)
            'default_variant' => $this->whenLoaded('defaultVariant', function () {
                return new ProductVariantResource($this->defaultVariant);
            }),

            // Bundle
            'bundle_items' => ProductBundleItemResource::collection($this->whenLoaded('bundleItems')),

            // Stock summary (na głównym produkcie)
            'available_stock' => $this->available_stock,
            'total_stock' => $this->total_stock,
            'reserved_stock' => $this->reserved_stock,
            'incoming_stock' => $this->incoming_stock,

            // Ceny główne (jeśli liczysz na głównym produkcie, możesz je podać – np. z domyślnego wariantu)
            'base_price_net' => $this->defaultVariant?->prices?->firstWhere('type', 'base')?->price_net,
            'base_price_gross' => $this->defaultVariant?->prices?->firstWhere('type', 'base')?->price_gross,
            'retail_price_net' => $this->defaultVariant?->prices?->firstWhere('type', 'retail')?->price_net,
            'retail_price_gross' => $this->defaultVariant?->prices?->firstWhere('type', 'retail')?->price_gross,
            // Dodaj inne typy cen jeśli potrzebujesz

            // Baselinker/sync
            'baselinker_id' => $this->baselinker_id,
            'last_sync_at' => $this->last_sync_at,

            // Statystyki (opcjonalne)
            'warehouse_name' => $this->warehouse_name ?? null,
            'location_display' => $this->location_display ?? null,
            'variants_count' => $this->variants?->count() ?? 0,
            'media_count' => $this->media?->count() ?? 0,
            'links_count' => $this->links?->count() ?? 0,

            // Daty
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
