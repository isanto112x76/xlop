<?php
// database/migrations/2024_06_01_000001_add_manage_stock_to_products_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('manage_stock')->default(false)->after('marketplace_attributes');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('manage_stock');
        });
    }
};
