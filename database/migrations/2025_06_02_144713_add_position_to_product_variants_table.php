<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Dodaj kolumnę 'position' po kolumnie 'is_default' (lub innej, według preferencji)
            // Ustawienie domyślnej wartości na 0 lub pozwolenie na NULL zależy od logiki aplikacji.
            // Jeśli zawsze chcesz mieć pozycję, możesz ustawić not nullable i default.
            $table->integer('position')->unsigned()->default(0)->after('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
