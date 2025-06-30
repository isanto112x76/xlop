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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->enum('type', ['PZ', 'FVZ', 'WZ', 'FS', 'MM', 'PW', 'RW', 'ZW', 'ZRW', 'INW']);
            $table->date('document_date');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->unsignedBigInteger('customer_id')->nullable(); // Załóżmy, że klienci będą w przyszłości
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('related_document_id')->nullable()->constrained('documents');
            $table->decimal('total_net', 12, 2);
            $table->decimal('total_gross', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
