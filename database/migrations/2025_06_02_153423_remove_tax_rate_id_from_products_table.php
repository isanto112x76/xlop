<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// DB facade nie jest tu potrzebny, jeśli nie używamy Schema::getConnection()->getDoctrineSchemaManager()

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Laravel spróbuje usunąć klucz obcy na podstawie nazwy kolumny (konwencja: products_tax_rate_id_foreign)
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn('tax_rate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Przywracamy kolumnę i klucz obcy
            // Upewnij się, że nazwa tabeli 'tax_rates' jest poprawna
            $table->foreignId('tax_rate_id')
                ->nullable() // Jeśli wcześniej była nullable
                ->default(1) // Jeśli miała default
                ->after('supplier_id') // Dostosuj pozycję, jeśli to ważne
                ->constrained('tax_rates')
                ->onDelete('set null'); // Lub inną akcję, np. restrict
        });
    }
};
