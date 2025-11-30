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
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('service_radius', 10, 5)->nullable()->comment('in meters or kilometers');
            $table->decimal('daily_target', 12, 2)->nullable();
            $table->decimal('weekly_target', 12, 2)->nullable();
            $table->decimal('monthly_target', 12, 2)->nullable();
            $table->decimal('yearly_target', 12, 2)->nullable();
        });
    }

    public function down()
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'latitude', 'longitude', 'service_radius',
                'daily_target', 'weekly_target', 'monthly_target', 'yearly_target'
            ]);
        });
    }
};
