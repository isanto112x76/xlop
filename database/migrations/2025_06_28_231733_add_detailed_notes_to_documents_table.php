<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->text('notes_internal')->nullable()->after('notes');
            $table->text('notes_print')->nullable()->after('notes_internal');
        });

        // Kopiujemy istniejące notatki do nowego pola
        DB::statement('UPDATE documents SET notes_internal = notes');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('paid_amount');
        });

        // Przywracamy dane do starej kolumny
        DB::statement('UPDATE documents SET notes = notes_internal');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['notes_internal', 'notes_print']);
        });
    }
};
