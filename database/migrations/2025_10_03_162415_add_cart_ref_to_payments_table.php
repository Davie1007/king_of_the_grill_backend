<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCartRefToPaymentsTable extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('cart_ref')->nullable()->after('transaction_id');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('cart_ref');
        });
    }
}