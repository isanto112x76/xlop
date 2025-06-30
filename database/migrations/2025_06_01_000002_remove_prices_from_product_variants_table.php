<?php
// database/migrations/2024_06_01_000002_remove_prices_from_product_variants_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'price_net',
                'price_gross',
                'price_wholesale_net',
                'price_promo_gross'
            ]);
        });
    }

    public function down()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('price_net', 10, 2)->default(0);
            $table->decimal('price_gross', 10, 2)->default(0);
            $table->decimal('price_wholesale_net', 10, 2)->nullable();
            $table->decimal('price_promo_gross', 10, 2)->nullable();
        });
    }
};
