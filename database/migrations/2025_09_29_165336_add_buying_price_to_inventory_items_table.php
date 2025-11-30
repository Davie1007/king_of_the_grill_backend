<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('buying_price', 10, 2)->after('price')->nullable();
        });
    }

    public function down()
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('buying_price');
        });
    }

};
