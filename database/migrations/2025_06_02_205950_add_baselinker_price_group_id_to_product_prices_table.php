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
        Schema::table('product_prices', function (Blueprint $table) {
            // Dodajemy kolumnę po istniejącej kolumnie, np. 'valid_to'
            // Jeśli chcesz ją w innym miejscu, dostosuj 'after'.
            // Zakładamy, że ID grupy cenowej Baselinkera to integer.
            // Może być nullable, jeśli nie wszystkie ceny mają takie powiązanie.
            $table->integer('baselinker_price_group_id')->nullable()->index()->after('valid_to')->comment('ID grupy cenowej Baselinker');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            // Sprawdzenie, czy kolumna istnieje przed próbą jej usunięcia,
            // aby uniknąć błędów, jeśli migracja down jest uruchamiana wielokrotnie
            // lub jeśli kolumna została usunięta ręcznie.
            if (Schema::hasColumn('product_prices', 'baselinker_price_group_id')) {
                $table->dropColumn('baselinker_price_group_id');
            }
        });
    }
};
