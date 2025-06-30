<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('synchronization_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['to_baselinker', 'from_baselinker']);
            $table->string('resource_type')->comment('e.g. product, stock, order');
            $table->enum('status', ['success', 'failed', 'in_progress']);
            $table->text('message')->nullable();
            $table->unsignedBigInteger('local_id')->nullable()->comment('ID zasobu w lokalnej bazie');
            $table->string('external_id')->nullable()->comment('ID zasobu w Baselinkerze');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synchronization_logs');
    }
};
