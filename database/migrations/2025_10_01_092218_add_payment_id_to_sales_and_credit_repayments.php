<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'payment_id')) {
                $table->foreignId('payment_id')
                      ->nullable()
                      ->after('mpesa_amount')
                      ->constrained('payments')
                      ->nullOnDelete();
            }
        });        

        Schema::table('credit_repayments', function (Blueprint $table) {
            $table->foreignId('payment_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });

        Schema::table('credit_repayments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });
    }
};
