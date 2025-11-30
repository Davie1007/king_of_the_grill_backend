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
        if (!Schema::hasColumn('credit_sales', 'sale_id')) {
            Schema::table('credit_sales', function (Blueprint $table) {
                $table->foreignId('sale_id')->nullable()->after('id')->constrained('sales')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('credit_sales', 'sale_id')) {
            Schema::table('credit_sales', function (Blueprint $table) {
                $table->dropForeign(['sale_id']);
                $table->dropColumn('sale_id');
            });
        }
    }
};
