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
        Schema::table('products', function (Blueprint $table) {
            // Dodaj kolumnę 'status' po kolumnie 'product_type' (lub innej odpowiedniej)
            // Upewnij się, że nazwy statusów są zgodne z tym, co masz w UpdateProductRequest
            $table->string('status')->default('draft')->after('product_type');
            // Możesz też użyć enum, jeśli Twoja baza danych to wspiera i wolisz to rozwiązanie:
            // $table->enum('status', ['active', 'inactive', 'draft', 'archived'])->default('draft')->after('product_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
