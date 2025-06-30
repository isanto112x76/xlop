<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->constrained();
            $table->decimal('expected_quantity', 10, 2);
            $table->decimal('counted_quantity', 10, 2);
            $table->decimal('difference', 10, 2)->storedAs('counted_quantity - expected_quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
