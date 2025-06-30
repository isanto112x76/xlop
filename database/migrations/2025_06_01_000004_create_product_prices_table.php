<?php
// database/migrations/2024_06_01_000004_create_product_prices_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->enum('type', ['retail', 'wholesale', 'purchase', 'promo'])->default('retail');
            $table->decimal('price_net', 10, 2);
            $table->decimal('price_gross', 10, 2);
            $table->char('currency', 3)->default('PLN');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index('variant_id');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_prices');
    }
};
