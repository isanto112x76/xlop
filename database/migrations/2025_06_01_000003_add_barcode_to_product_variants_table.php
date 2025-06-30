<?php
// database/migrations/2024_06_01_000003_add_barcode_to_product_variants_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->integer('barcode')->nullable()->after('ean');
        });
    }

    public function down()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('barcode');
        });
    }
};
