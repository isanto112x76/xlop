<?php
// database/migrations/2024_06_01_000006_add_external_fields_to_order_items_table.php use
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->
                integer('product_baselinker_id')->nullable()->after('product_variant_id');
            $table->integer('order_product_id')->nullable()->after('product_baselinker_id');
            $table->integer('original_product_id')->nullable()->after('order_product_id');
        });
    }

    public function down()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_baselinker_id', 'order_product_id', 'original_product_id']);
        });
    }
};
