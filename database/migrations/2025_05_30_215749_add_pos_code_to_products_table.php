<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('pos_code')->nullable()->after('ean');
            $table->unique('pos_code'); // Jeśli chcesz mieć unikalny numer kasowy, usuń jeśli nie potrzebujesz
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['pos_code']);
            $table->dropColumn('pos_code');
        });
    }
};

