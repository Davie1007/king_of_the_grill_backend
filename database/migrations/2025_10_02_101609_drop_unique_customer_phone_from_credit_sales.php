<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite requires raw SQL for index drops
            DB::statement('DROP INDEX IF EXISTS credit_sales_customer_phone_unique');
        } else {
            Schema::table('credit_sales', function (Blueprint $table) {
                $table->dropUnique('credit_sales_customer_phone_unique');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS credit_sales_customer_phone_unique ON credit_sales (customer_phone)');
        } else {
            Schema::table('credit_sales', function (Blueprint $table) {
                $table->unique('customer_phone');
            });
        }
    }
};
