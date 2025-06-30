<?php
// database/migrations/2024_06_01_000005_add_external_fields_to_orders_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('external_order_id')->nullable()->after('baselinker_order_id');
            $table->json('external_informations')->nullable()->after('external_order_id');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['external_order_id', 'external_informations']);
        });
    }
};
