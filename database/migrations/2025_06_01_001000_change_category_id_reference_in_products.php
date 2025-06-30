<?php
// database/migrations/2024_06_01_001000_change_category_id_reference_in_products.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']); // usuń starą FK
            $table->unsignedInteger('category_id')->change(); // zmień na unsigned int (dopasowane do Baselinker)
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')->references('baselinker_category_id')->on('categories')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->unsignedBigInteger('category_id')->change(); // zmień z powrotem na BIGINT, jeśli był taki typ
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }
};
