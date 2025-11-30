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
        Schema::create('daraja_apps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->string('name');
            $table->string('consumer_key');
            $table->string('consumer_secret');
            $table->string('shortcode');
            $table->string('passkey');
            $table->enum('environment', ['sandbox', 'live'])->default('sandbox');
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daraja_apps');
    }
};
