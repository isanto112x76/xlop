<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // DODANE POLA:
            $table->foreignId('inventory_id')->constrained('inventories')->comment('Powiązanie z katalogiem Baselinker');
            $table->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->onDelete('set null');

            $table->enum('product_type', ['standard', 'bundle'])->default('standard');
            $table->string('name');
            $table->string('sku', 100)->unique();
            $table->string('ean', 30)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('tax_rate_id')->constrained();
            $table->decimal('weight', 10, 3)->nullable();
            $table->integer('baselinker_id')->nullable()->index()->comment('ID produktu w danym katalogu Baselinker');
            $table->json('marketplace_attributes')->nullable()->comment('Elastyczne atrybuty, flagi i parametry dla Allegro, itp.');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
