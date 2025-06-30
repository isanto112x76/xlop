<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->decimal('quantity_total', 12, 2);
            $table->decimal('quantity_available', 12, 2);
            $table->decimal('purchase_price', 12, 2);
            $table->date('purchase_date');
            $table->string('source_document_type')->nullable();
            $table->unsignedBigInteger('source_document_id')->nullable();
            $table->timestamps();

            $table->foreign('product_variant_id')->references('id')->on('product_variants')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->index(['product_variant_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
