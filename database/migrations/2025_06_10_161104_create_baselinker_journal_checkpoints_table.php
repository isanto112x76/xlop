<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('baselinker_journal_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('last_log_id');
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baselinker_journal_checkpoints');
    }
};
