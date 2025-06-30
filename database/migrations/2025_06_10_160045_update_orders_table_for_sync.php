<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Uruchamia migracje, rozbudowując tabelę `orders`.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Usunięcie starych, zdenormalizowanych kolumn, jeśli istnieją
            if (Schema::hasColumn('orders', 'customer_details')) {
                $table->dropColumn('customer_details');
            }

            // Dodanie kluczy obcych do nowych tabel
            $table->foreignId('customer_id')->nullable()->after('order_date')->constrained()->onDelete('set null');
            $table->foreignId('billing_address_id')->nullable()->after('customer_id')->constrained('addresses')->onDelete('set null');
            $table->foreignId('shipping_address_id')->nullable()->after('billing_address_id')->constrained('addresses')->onDelete('set null');

            // Dodanie nowych, ustrukturyzowanych kolumn
            $table->string('customer_login')->nullable()->after('customer_id');
            $table->text('customer_comments')->nullable()->after('total_gross');
            $table->string('payment_method')->nullable()->after('total_gross');
            $table->boolean('is_cod')->default(false)->after('payment_method')->comment('Czy za pobraniem');
            $table->decimal('delivery_price', 10, 2)->default(0.00)->after('is_cod');
            $table->string('delivery_method')->nullable()->after('delivery_price');
            $table->string('delivery_tracking_number')->nullable()->after('delivery_method');
            $table->boolean('want_invoice')->default(false)->after('delivery_tracking_number');
            $table->timestamp('date_confirmed')->nullable()->after('order_date');
            $table->timestamp('date_in_status')->nullable()->after('date_confirmed');

            // Zmiana nazwy kolumny `order_date` na `date_add` dla spójności z Baselinkerem
            if (Schema::hasColumn('orders', 'order_date')) {
                $table->renameColumn('order_date', 'date_add');
            }

            // Zmiana nazwy i typu kolumny `status` na ID statusu
            if (Schema::hasColumn('orders', 'status')) {
                $table->renameColumn('status', 'baselinker_status_id');
            }
        });

        // Zmiana typu kolumny w osobnym kroku dla kompatybilności
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'baselinker_status_id')) {
                $table->integer('baselinker_status_id')->change();
            }
        });
    }

    /**
     * Odwraca migracje, przywracając poprzedni stan tabeli.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->longText('customer_details')->nullable();

            $table->dropForeign(['customer_id']);
            $table->dropForeign(['billing_address_id']);
            $table->dropForeign(['shipping_address_id']);

            $table->dropColumn([
                'customer_id',
                'billing_address_id',
                'shipping_address_id',
                'payment_method',
                'is_cod',
                'delivery_price',
                'delivery_method',
                'delivery_tracking_number',
                'want_invoice',
                'date_confirmed',
                'date_in_status',
                'customer_login',
                'customer_comments'
            ]);

            if (Schema::hasColumn('orders', 'baselinker_status_id')) {
                $table->renameColumn('baselinker_status_id', 'status');
            }
            if (Schema::hasColumn('orders', 'date_add')) {
                $table->renameColumn('date_add', 'order_date');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'status')) {
                $table->string('status')->change();
            }
        });
    }
};
