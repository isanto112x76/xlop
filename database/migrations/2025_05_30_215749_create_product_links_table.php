<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('url');
            $table->string('label')->nullable(); // np. "Dostawca A", "Oficjalny sklep"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_links');
    }
};
