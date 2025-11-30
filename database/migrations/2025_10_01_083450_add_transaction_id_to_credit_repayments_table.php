<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credit_repayments', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_repayments', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->unique()->after('payment_method');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_repayments', function (Blueprint $table) {
            $table->dropColumn('transaction_id');
        });
    }
};
