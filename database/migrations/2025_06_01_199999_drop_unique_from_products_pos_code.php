<?php
// database/migrations/2024_06_01_999999_drop_unique_from_products_pos_code.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_pos_code_unique'); // domyślna nazwa w Laravel
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unique('pos_code', 'products_pos_code_unique');
        });
    }
};

