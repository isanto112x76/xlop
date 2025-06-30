<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('name')->nullable()->after('type');
            $table->string('foreign_number')->nullable()->after('number');
            $table->string('payment_method')->nullable()->after('total_gross');
            $table->boolean('paid')->default(false)->after('payment_method');
            $table->decimal('paid_amount', 12, 2)->default(0.00)->after('paid');
            $table->string('delivery_method')->nullable()->after('paid_amount');
            $table->string('delivery_tracking_number')->nullable()->after('delivery_method');
            $table->date('issue_date')->nullable()->after('document_date');
            $table->date('delivery_date')->nullable()->after('issue_date');
            $table->string('currency', 3)->default('PLN')->after('total_gross');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'foreign_number',
                'payment_method',
                'paid',
                'paid_amount',
                'delivery_method',
                'delivery_tracking_number',
                'issue_date',
                'delivery_date',
                'currency'
            ]);
        });
    }
};
