<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSaleIdToPaymentsTable extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->nullable()->after('cart_ref');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropColumn('sale_id');
        });
    }
}