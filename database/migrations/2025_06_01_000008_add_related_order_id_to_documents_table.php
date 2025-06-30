<?php

// database/migrations/2024_06_01_000008_add_related_order_id_to_documents_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedBigInteger('related_order_id')->nullable()->after('related_document_id');
            $table->foreign('related_order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['related_order_id']);
            $table->dropColumn('related_order_id');
        });
    }
};
