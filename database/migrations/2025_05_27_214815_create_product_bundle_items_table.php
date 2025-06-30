<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_product_id')->comment('ID produktu typu "bundle"')->constrained('products')->onDelete('cascade');
            $table->foreignId('component_variant_id')->comment('ID wariantu wchodzącego w skład')->constrained('product_variants')->onDelete('cascade');
            $table->integer('quantity');
            $table->unique(['bundle_product_id', 'component_variant_id'], 'bundle_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
    }
};
