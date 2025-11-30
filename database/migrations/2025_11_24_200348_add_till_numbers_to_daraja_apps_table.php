<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('daraja_apps', function (Blueprint $table) {
            $table->string('till_number_1')->nullable()->after('shortcode');
            $table->string('till_number_2')->nullable()->after('till_number_1');
            $table->string('till_number_3')->nullable()->after('till_number_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daraja_apps', function (Blueprint $table) {
            $table->dropColumn(['till_number_1', 'till_number_2', 'till_number_3']);
        });
    }
};
