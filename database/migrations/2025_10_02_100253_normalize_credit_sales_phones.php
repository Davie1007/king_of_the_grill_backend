<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1️⃣ Load all rows into memory
        $rows = DB::table('credit_sales')->select('id', 'customer_phone')->get();

        $groups = [];

        foreach ($rows as $row) {
            $phone = preg_replace('/\D/', '', $row->customer_phone);

            if (str_starts_with($phone, '0')) {
                $phone = '254' . substr($phone, 1);
            } elseif (! str_starts_with($phone, '254')) {
                $phone = '254' . $phone;
            }

            $groups[$phone][] = $row->id;
        }

        // 2️⃣ For each normalized phone, keep one row, delete the rest
        foreach ($groups as $phone => $ids) {
            sort($ids);
            $keep = array_shift($ids);
            if (!empty($ids)) {
                DB::table('credit_sales')->whereIn('id', $ids)->delete();
            }
            DB::table('credit_sales')->where('id', $keep)->update(['customer_phone' => $phone]);
        }

        // 3️⃣ Add unique index (idempotent: drop first if exists)
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS credit_sales_customer_phone_unique');
            DB::statement('CREATE UNIQUE INDEX credit_sales_customer_phone_unique ON credit_sales (customer_phone)');
        } else {
            // For MySQL/Postgres: avoid duplicate unique key
            Schema::table('credit_sales', function (Blueprint $table) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('credit_sales');

                if (! array_key_exists('credit_sales_customer_phone_unique', $indexes)) {
                    $table->unique('customer_phone', 'credit_sales_customer_phone_unique');
                }
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS credit_sales_customer_phone_unique');
        } else {
            Schema::table('credit_sales', function (Blueprint $table) {
                $table->dropUnique('credit_sales_customer_phone_unique');
            });
        }
    }
};
