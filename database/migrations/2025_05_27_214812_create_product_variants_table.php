<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('name')->nullable()->comment('Np. Czerwony, XL');
            $table->string('sku', 100)->unique();
            $table->string('ean', 30)->nullable();

            $table->decimal('price_net', 10, 2);
            $table->decimal('price_gross', 10, 2);
            // DODANE POLA:
            $table->decimal('price_wholesale_net', 10, 2)->nullable()->comment('Cena hurtowa netto');
            $table->decimal('price_promo_gross', 10, 2)->nullable()->comment('Cena promocyjna brutto');

            $table->integer('baselinker_variant_id')->nullable()->index();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
