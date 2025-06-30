<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Import DB

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            // Dodajemy tax_rate_id, NOT NULL, z domyślną wartością 1.
            // Upewnij się, że rekord z ID=1 istnieje w tabeli 'tax_rates' i odpowiada 23%.
            $table->foreignId('tax_rate_id')
                ->default(1) // Domyślna stawka VAT (np. ID dla 23%)
                ->after('currency') // Opcjonalne, dla kolejności kolumn
                ->constrained('tax_rates')
                // onDelete('restrict') zapobiegnie usunięciu stawki VAT (np. tej z ID=1), jeśli jest używana.
                ->onDelete('restrict');
        });

        // Aktualizacja istniejących rekordów w product_prices, aby ustawi im tax_rate_id.
        // Ponieważ wszystkie Twoje produkty miały tax_rate_id = 1,
        // przypisujemy tę wartość wszystkim istniejącym cenom.
        // Jeśli tabela product_prices jest pusta, ten krok nic nie zrobi.
        // Jeśli są w niej ceny, a kolumna tax_rate_id została dodana z DEFAULT 1,
        // ten explicit UPDATE może być nadmiarowy, ale zapewnia spójność.
        DB::table('product_prices')
            ->join('product_variants', 'product_prices.variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('products.tax_rate_id', 1) // Pobieramy tylko te ceny, których produkty miały tax_rate_id=1
            ->update(['product_prices.tax_rate_id' => 1]);

        // Jeśli są jakieś ceny, których produkty nie miały tax_rate_id=1 (choć powiedziałeś, że wszystkie mają),
        // poniższy kod ustawi im domyślną stawkę 1 (dzięki DEFAULT 1 przy tworzeniu kolumny).
        // Alternatywnie, jeśli byłyby inne stawki na produktach, musiałbyś je zmapować.
        // DB::statement("UPDATE product_prices SET tax_rate_id = 1 WHERE tax_rate_id IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            // Nazwa klucza obcego jest generowana przez Laravel, np. product_prices_tax_rate_id_foreign
            // Możesz sprawdzić dokładną nazwę w swojej bazie danych lub pozwolić Laravelowi ją wykryć.
            if (DB::getDriverName() !== 'sqlite') { // SQLite nie wspiera dropForeign w ten sposób dla starszych wersji
                $foreignKeys = Schema::getConnection()->getDoctrineSchemaManager()->listTableForeignKeys('product_prices');
                foreach ($foreignKeys as $foreignKey) {
                    if ($foreignKey->getColumns() == ['tax_rate_id']) {
                        $table->dropForeign($foreignKey->getName());
                        break;
                    }
                }
            }
            $table->dropColumn('tax_rate_id');
        });
    }
};
