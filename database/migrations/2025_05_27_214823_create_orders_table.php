<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->integer('baselinker_order_id')->unique();
            $table->string('order_source')->nullable();
            $table->dateTime('order_date');
            $table->json('customer_details')->nullable();
            $table->string('status');
            $table->decimal('total_gross', 10, 2);
            $table->foreignId('related_wz_id')->nullable()->constrained('documents');
            $table->string('sync_status', 50)->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
