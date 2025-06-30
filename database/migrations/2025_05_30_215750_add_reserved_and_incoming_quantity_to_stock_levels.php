<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->decimal('incoming_quantity', 10, 2)->default(0)->after('reserved_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropColumn('incoming_quantity');
        });
    }
};
