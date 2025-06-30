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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->text('description_override')->nullable()->after('barcode');
            $table->decimal('weight_override', 10, 3)->nullable()->after('description_override');
            $table->json('attributes_override')->nullable()->after('weight_override');
            $table->json('marketplace_attributes_override')->nullable()->after('attributes_override');

            $table->boolean('override_product_description')->default(false)->after('marketplace_attributes_override');
            $table->boolean('override_product_weight')->default(false)->after('override_product_description');
            $table->boolean('override_product_attributes')->default(false)->after('override_product_weight');
            $table->boolean('override_product_marketplace_attributes')->default(false)->after('override_product_attributes');
            $table->boolean('has_own_media')->default(false)->after('override_product_marketplace_attributes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'description_override',
                'weight_override',
                'attributes_override',
                'marketplace_attributes_override',
                'override_product_description',
                'override_product_weight',
                'override_product_attributes',
                'override_product_marketplace_attributes',
                'has_own_media',
            ]);
        });
    }
};
