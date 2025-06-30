<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->constrained();
            $table->decimal('quantity', 10, 2);
            $table->decimal('price_net', 10, 2);
            $table->decimal('price_gross', 10, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_items');
    }
};
