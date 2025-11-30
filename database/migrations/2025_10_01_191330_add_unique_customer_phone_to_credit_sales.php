<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 🔹 Step 1: Clean duplicates before adding unique index
        $duplicates = DB::table('credit_sales')
            ->select('customer_phone', DB::raw('COUNT(*) as cnt'))
            ->groupBy('customer_phone')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $ids = DB::table('credit_sales')
                ->where('customer_phone', $dup->customer_phone)
                ->orderBy('id')
                ->pluck('id')
                ->toArray();

            // keep the first record, delete the rest
            array_shift($ids);
            DB::table('credit_sales')->whereIn('id', $ids)->delete();
        }

        // 🔹 Step 2: Add unique index after cleanup
        Schema::table('credit_sales', function (Blueprint $table) {
            $table->unique('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('credit_sales', function (Blueprint $table) {
            $table->dropUnique(['customer_phone']);
        });
    }
};
