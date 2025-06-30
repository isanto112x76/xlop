<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTypeEnumInProductPricesTable extends Migration
{
    public function up()
    {
        // Uwaga: DB::statement wymagany dla zmian ENUM!
        \DB::statement("ALTER TABLE `product_prices` MODIFY `type` ENUM('retail', 'wholesale', 'purchase', 'promo', 'base') NOT NULL");
    }

    public function down()
    {
        // Cofnięcie zmian (jeśli chcesz usunąć 'base' – opcjonalnie!)
        \DB::statement("ALTER TABLE `product_prices` MODIFY `type` ENUM('retail', 'wholesale', 'purchase', 'promo') NOT NULL");
    }
}
