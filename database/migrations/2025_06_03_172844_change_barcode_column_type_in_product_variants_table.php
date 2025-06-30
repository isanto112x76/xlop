<?php
// YYYY_MM_DD_HHMMSS_change_barcode_column_type_in_product_variants_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Zmień na VARCHAR, dostosuj długość (np. 100)
            $table->string('barcode', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Przywrócenie poprzedniego typu INT - UWAGA: Może spowodować utratę danych, jeśli były tam stringi!
            // Lepiej dobrze przemyśleć `down()` lub zrobić backup.
            // $table->integer('barcode')->nullable()->unsigned()->change(); // Przykład, dostosuj do oryginału
        });
    }
};
